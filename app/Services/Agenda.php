<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\CardTypes;
use App\Data\AgendaData;
use App\Data\AgendaEntryData;
use App\Data\AgendaReminderData;
use App\Enums\AgendaReminderState;
use App\Enums\ReminderStatus;
use App\Models\Document;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Queries\CardDeadlines;
use App\Support\CardDeadline;
use App\Support\Deadline;
use App\Support\ModelId;
use App\Support\PlannedKind;
use App\Support\Reminders\Notices;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The entries of a period, for the Agenda, the Home and the calendar: the
 * deadlines of cards and documents, the unpaid due dates of planned expenses and
 * the due dates of planned incomes that have not arrived. Paid and skipped due
 * dates never appear. A deadline that a planned expense of the same card pays on
 * the same day is one entry with the payment (answer 5 of the analysis).
 */
final readonly class Agenda
{
    public function __construct(
        private CardDeadlines $cardDeadlines,
        private Deadlines $deadlines,
        private CardSummaries $cards,
    ) {}

    /**
     * The last day of a period of some months from today: the same day, or the last of a shorter month.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @param  int  $months  how many months
     */
    public function period(CarbonImmutable $today, int $months): CarbonImmutable
    {
        return $today->addMonthsNoOverflow($months);
    }

    /**
     * The entries up to a day, by date and then by title.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @param  CarbonImmutable  $until  the last day, included
     * @param  int  $deadlinesBack  how many days back the deadlines of cards and documents go
     * @param  int  $paymentsBack  how many days back the payments and the incomes go: 0 from today
     * @return list<AgendaEntryData>
     */
    public function entries(CarbonImmutable $today, CarbonImmutable $until, int $deadlinesBack = 90, int $paymentsBack = 0): array
    {
        return array_column($this->build($today, $until, $deadlinesBack, $paymentsBack), 'entry');
    }

    /**
     * The entries up to a day, like `entries()`, without those of a deadline that has its automatic reminder, open
     * or done: for the calendar, where the open reminder is the event and a done one leaves nothing. A payment merged
     * with such a deadline goes too, because the reminder already stands for that day.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @param  CarbonImmutable  $until  the last day, included
     * @param  int  $deadlinesBack  how many days back the deadlines of cards and documents go
     * @param  int  $paymentsBack  how many days back the payments and the incomes go: 0 from today
     * @return list<AgendaEntryData>
     */
    public function entriesWithoutReminders(CarbonImmutable $today, CarbonImmutable $until, int $deadlinesBack = 90, int $paymentsBack = 0): array
    {
        return array_column(array_values(array_filter(
            $this->build($today, $until, $deadlinesBack, $paymentsBack),
            static fn (array $item): bool => ! $item['deadline']?->reminder instanceof Reminder,
        )), 'entry');
    }

    /**
     * The entries up to a day, by date and then by title, each with the deadline it shows, if any.
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @param  CarbonImmutable  $until  the last day, included
     * @param  int  $deadlinesBack  how many days back the deadlines of cards and documents go
     * @param  int  $paymentsBack  how many days back the payments and the incomes go: 0 from today
     * @return list<array{entry: AgendaEntryData, deadline: CardDeadline|null}>
     */
    private function build(CarbonImmutable $today, CarbonImmutable $until, int $deadlinesBack, int $paymentsBack): array
    {
        $today = $today->startOfDay();
        $payments = $this->openDueDates($today->subDays($paymentsBack), $until);
        $deadlines = $this->cardDeadlines->between($today->subDays($deadlinesBack), $until, $today);
        $this->loadLabels($deadlines);
        $groups = ReminderGroup::query()->get();
        $reminder = fn (?CardDeadline $deadline): ?AgendaReminderData => $this->reminderOf($deadline, $today, $groups);

        $byCard = [];

        foreach ($payments as $index => $payment) {
            $card = $payment->planned instanceof PlannedExpense ? PlannedKind::card($payment->planned) : null;

            if (! $card instanceof Model) {
                continue;
            }

            $key = $this->sameCard($card, $payment->occurrence->date);

            // The payment with the lowest planned id merges, whatever order the due dates come in
            if (! isset($byCard[$key]) || $payment->planned->id < $payments[$byCard[$key]]->planned->id) {
                $byCard[$key] = $index;
            }
        }

        $entries = [];
        $merged = [];

        foreach ($deadlines as $deadline) {
            $index = $deadline->source->hasExpense ? ($byCard[$this->sameCard($deadline->card, $deadline->date)] ?? null) : null;

            if ($index === null || isset($merged[$index])) {
                $entries[] = ['entry' => $this->deadlineEntry($deadline, $reminder($deadline)), 'deadline' => $deadline];

                continue;
            }

            $merged[$index] = true;
            $entries[] = ['entry' => $this->paymentEntry($payments[$index], $deadline, $reminder($deadline)), 'deadline' => $deadline];
        }

        foreach ($payments as $index => $payment) {
            if (! isset($merged[$index])) {
                $entries[] = ['entry' => $this->paymentEntry($payment), 'deadline' => null];
            }
        }

        usort($entries, static fn (array $a, array $b): int => [$a['entry']->date, mb_strtolower($a['entry']->title)] <=> [$b['entry']->date, mb_strtolower($b['entry']->title)]);

        return $entries;
    }

    /**
     * The due dates of the planned expenses and incomes of the family between two days that nothing settles.
     *
     * @param  CarbonImmutable  $from  the first day
     * @param  CarbonImmutable  $to  the last day
     * @return list<Deadline>
     */
    private function openDueDates(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $planned = [
            ...PlannedExpense::query()->with('organization')->get()->all(),
            ...PlannedIncome::query()->with('organization')->get()->all(),
        ];

        return array_values(array_filter(
            $this->deadlines->between($planned, $from, $to),
            static fn (Deadline $deadline): bool => ! $deadline->isPaid(),
        ));
    }

    /**
     * Loads what the names of the cards read, one query per relation and card type.
     *
     * @param  list<CardDeadline>  $deadlines  the deadlines
     */
    private function loadLabels(array $deadlines): void
    {
        $byClass = [];

        foreach ($deadlines as $deadline) {
            $byClass[$deadline->card::class][] = $deadline->card;
        }

        foreach ($byClass as $cards) {
            if (! $cards[0] instanceof Document) {
                new Collection($cards)->loadMissing(CardTypes::for($cards[0])->listRelations());
            }
        }
    }

    /**
     * The entry of a deadline of a card or a document.
     *
     * @param  CardDeadline  $deadline  the deadline
     * @param  AgendaReminderData|null  $reminder  its automatic reminder
     */
    private function deadlineEntry(CardDeadline $deadline, ?AgendaReminderData $reminder): AgendaEntryData
    {
        $card = $deadline->card;
        $date = $deadline->date->toDateString();
        $document = $card instanceof Document;

        return new AgendaEntryData(
            key: "deadline:{$deadline->source->field}:".ModelId::of($card).":{$date}",
            kind: 'deadline',
            date: $date,
            title: $this->deadlineTitle($deadline),
            subtitle: $this->deadlineSubtitle($deadline),
            name: $document ? $this->deadlineTitle($deadline) : $this->cards->label($card),
            detail: $document ? __('Document expiry') : $this->deadlineTitle($deadline),
            area: $deadline->area,
            person_id: $deadline->person?->id,
            person: $deadline->person?->first_name,
            amount: null,
            estimated: false,
            reminder_done: $deadline->reminderDone(),
            reminder: $reminder,
            href: $document
                ? route('documents.show', $card)
                : route('cards.show', ['type' => CardTypes::for($card)->type(), 'id' => ModelId::of($card)]),
            update_url: Gate::allows('update', $card) ? route('agenda.deadlines.update', ['field' => $deadline->source->field, 'id' => ModelId::of($card)]) : null,
        );
    }

    /**
     * The entry of a payment or an income; with the deadline it pays, the title and the reminder are the deadline's.
     *
     * @param  Deadline  $payment  the open due date
     * @param  CardDeadline|null  $deadline  the deadline of the same card on the same day, if this payment pays it
     * @param  AgendaReminderData|null  $reminder  the automatic reminder of that deadline
     */
    private function paymentEntry(Deadline $payment, ?CardDeadline $deadline = null, ?AgendaReminderData $reminder = null): AgendaEntryData
    {
        $planned = $payment->planned;
        $card = PlannedKind::card($planned);
        $kind = $planned instanceof PlannedExpense ? 'payment' : 'income';
        $originalDate = $payment->occurrence->originalDate->toDateString();
        $person = $planned->person ?? $deadline?->person;

        return new AgendaEntryData(
            key: "{$kind}:{$planned->id}:{$originalDate}",
            kind: $kind,
            date: $payment->occurrence->date->toDateString(),
            title: $title = $deadline instanceof CardDeadline ? $this->deadlineTitle($deadline) : $payment->title,
            subtitle: $subtitle = $card instanceof Model ? $this->cards->label($card) : ($planned->organization->name ?? ''),
            name: $card instanceof Model ? $subtitle : $title,
            detail: $card instanceof Model ? $title : $subtitle,
            area: $card instanceof Model ? CardTypes::for($card)->area()->value : AgendaData::GENERAL_AREA,
            person_id: $person?->id,
            person: $person?->first_name,
            amount: $payment->occurrence->amount,
            estimated: $payment->occurrence->estimated,
            reminder_done: $deadline?->reminderDone() ?? false,
            reminder: $reminder,
            href: route('budget.deadlines.show', ['kind' => $payment->kind(), 'id' => $planned->id, 'date' => $originalDate]).'?pay=1',
        );
    }

    /**
     * The automatic reminder of a deadline as the Agenda shows it: to do, late or done, with its next notice.
     *
     * @param  CardDeadline|null  $deadline  the deadline, null for a payment that pays none
     * @param  CarbonImmutable  $today  today in Rome
     * @param  Collection<int, ReminderGroup>  $groups  the groups of notices of the family
     */
    private function reminderOf(?CardDeadline $deadline, CarbonImmutable $today, Collection $groups): ?AgendaReminderData
    {
        $reminder = $deadline?->reminder;

        if (! $reminder instanceof Reminder) {
            return null;
        }

        if ($reminder->status === ReminderStatus::Done) {
            return new AgendaReminderData(AgendaReminderState::Done, null);
        }

        $late = $reminder->day()->toDateString() < $today->toDateString();

        return new AgendaReminderData(
            $late ? AgendaReminderState::Late : AgendaReminderState::Open,
            Notices::next($reminder, Notices::rulesFor($reminder, $groups), $today)?->toDateString(),
        );
    }

    /**
     * What expires: the label of the kind of deadline, the title for a document.
     *
     * @param  CardDeadline  $deadline  the deadline
     */
    private function deadlineTitle(CardDeadline $deadline): string
    {
        $title = $deadline->source->label ?? $deadline->card->getAttribute('title');

        return is_string($title) ? $title : '';
    }

    /**
     * What holds the deadline: the name of the card, or «Document of Marco».
     *
     * @param  CardDeadline  $deadline  the deadline
     */
    private function deadlineSubtitle(CardDeadline $deadline): string
    {
        if (! $deadline->card instanceof Document) {
            return $this->cards->label($deadline->card);
        }

        return $deadline->person instanceof Person ? __('Document of :name', ['name' => $deadline->person->first_name]) : __('Document');
    }

    /**
     * The key that pairs a deadline of a card with a payment of the same card on the same day.
     *
     * @param  Model  $card  the card
     * @param  CarbonImmutable  $date  the day
     */
    private function sameCard(Model $card, CarbonImmutable $date): string
    {
        return $card->getMorphClass().'|'.ModelId::of($card).'|'.$date->toDateString();
    }
}
