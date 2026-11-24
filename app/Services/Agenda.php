<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\CardTypes;
use App\Data\AgendaData;
use App\Data\AgendaEntryData;
use App\Data\AgendaReminderData;
use App\Enums\AgendaReminderState;
use App\Enums\Feature;
use App\Enums\ReminderStatus;
use App\Models\Document;
use App\Models\InsurancePolicy;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Models\User;
use App\Queries\CardDeadlines;
use App\Support\CardDeadline;
use App\Support\Deadline;
use App\Support\ModelId;
use App\Support\PlannedKind;
use App\Support\PolicyReplacement;
use App\Support\Reminders\Delivery;
use App\Support\Reminders\Notices;
use App\Support\Reminders\NotificationRule;
use App\Support\Reminders\Renewals;
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
        private CardBudgets $budgets,
        private FamilyFeatures $features,
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
        $dueDates = $this->dueDates($today->subDays(max($deadlinesBack, $paymentsBack)), $until);
        $paymentsFrom = $today->subDays($paymentsBack)->toDateString();
        $payments = array_values(array_filter(
            $dueDates,
            static fn (Deadline $due): bool => $due->isDue() && $due->occurrence->date->toDateString() >= $paymentsFrom,
        ));
        $paid = $this->paidByCard($dueDates);
        $deadlines = $this->cardDeadlines->between($today->subDays($deadlinesBack), $until, $today);
        $this->loadLabels($deadlines);
        $groups = ReminderGroup::query()->get();
        $accounts = $this->accounts();
        $reminder = fn (?CardDeadline $deadline): ?AgendaReminderData => $deadline?->reminder instanceof Reminder ? $this->reminderOf($deadline->reminder, $today, $groups, $accounts) : null;

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
            // Paid by the payment of its card on that day: it leaves with the payment, as paid due dates do
            if ($deadline->source->hasExpense && isset($paid[$this->sameCard($deadline->card, $deadline->date)])) {
                continue;
            }

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
     * The payments already made of the planned expenses between two days, by date and then by title, as the entries
     * of the list shows them. Incomes are not things to do, so the ones that arrived are not here. A done automatic
     * reminder of the same card on the same day, that a payment pays (`hasExpense`) or confirms (the renewal of a
     * subscription, marked done by `ExpenseRenewalObserver`), is the same thing: the payment carries it as
     * `reminder_done` and its id is returned, so the done reminders leave it out, as the list merges them.
     *
     * @param  CarbonImmutable  $from  the first day
     * @param  CarbonImmutable  $to  the last day
     * @return array{entries: list<AgendaEntryData>, reminders: list<int>} the payments, and the ids of the done reminders they carry
     */
    public function paidBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        // Without the Budget there are no payments to show
        if (! $this->features->on(Feature::Budget)) {
            return ['entries' => [], 'reminders' => []];
        }

        $paid = array_values(array_filter(
            $this->deadlines->between(PlannedExpense::query()->with('organization')->get()->all(), $from->startOfDay(), $to),
            static fn (Deadline $deadline): bool => $deadline->isPaid(),
        ));
        $done = $this->doneByCard($from, $to);
        $carried = [];
        $entries = [];

        foreach ($paid as $payment) {
            $card = PlannedKind::card($payment->planned);
            $reminder = $card instanceof Model ? ($done[$this->sameCard($card, $payment->occurrence->date)] ?? null) : null;

            if ($reminder !== null && ! in_array($reminder, $carried, true)) {
                $carried[] = $reminder;
                $entries[] = $this->paymentEntry($payment, reminderDone: true);

                continue;
            }

            $entries[] = $this->paymentEntry($payment);
        }

        usort($entries, static fn (AgendaEntryData $a, AgendaEntryData $b): int => [$a->date, mb_strtolower($a->title)] <=> [$b->date, mb_strtolower($b->title)]);

        return ['entries' => $entries, 'reminders' => $carried];
    }

    /**
     * The done automatic reminders between two days that a payment of their card pays or confirms, by card and day.
     *
     * @param  CarbonImmutable  $from  the first day
     * @param  CarbonImmutable  $to  the last day
     * @return array<string, int> the id of the reminder, by `sameCard` key
     */
    private function doneByCard(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $reminders = Reminder::query()
            ->where('status', ReminderStatus::Done)
            ->where('is_automatic', true)
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->with('link.subject')
            ->orderBy('id')
            ->get();
        $byCard = [];

        foreach ($reminders as $reminder) {
            $source = $reminder->deadlineSource();
            $card = $reminder->link?->subject;

            if ($card instanceof Model && ($source?->hasExpense === true || $reminder->source_field === Renewals::FIELD)) {
                $byCard[$this->sameCard($card, $reminder->due_date)] ??= $reminder->id;
            }
        }

        return $byCard;
    }

    /**
     * The due dates of the planned expenses and incomes of the family between two days, paid or not; the skipped
     * ones are not there. With the Budget off there are none.
     *
     * @param  CarbonImmutable  $from  the first day
     * @param  CarbonImmutable  $to  the last day
     * @return list<Deadline>
     */
    private function dueDates(CarbonImmutable $from, CarbonImmutable $to): array
    {
        // Without the Budget nothing is to pay or to receive, and no entry may link to a page of the Budget
        if (! $this->features->on(Feature::Budget)) {
            return [];
        }

        $planned = [
            ...PlannedExpense::query()->with('organization')->get()->all(),
            ...PlannedIncome::query()->with('organization')->get()->all(),
        ];

        return $this->deadlines->between($planned, $from, $to);
    }

    /**
     * The cards and days that a payment of a planned expense of the card has already paid.
     *
     * @param  list<Deadline>  $dueDates  the due dates of the period
     * @return array<string, true> by `sameCard` key
     */
    private function paidByCard(array $dueDates): array
    {
        $paid = [];

        foreach ($dueDates as $due) {
            $card = $due->isPaid() && $due->planned instanceof PlannedExpense ? PlannedKind::card($due->planned) : null;

            if ($card instanceof Model) {
                $paid[$this->sameCard($card, $due->occurrence->date)] = true;
            }
        }

        return $paid;
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
            replace_url: $card instanceof InsurancePolicy && $deadline->source->field === 'insurance_policies.expiry_date' && Gate::allows('update', $card) && Gate::allows('create', InsurancePolicy::class)
                ? PolicyReplacement::createUrl($card)
                : null,
        );
    }

    /**
     * The entry of a payment or an income; with the deadline it pays, the title and the reminder are the deadline's.
     *
     * @param  Deadline  $payment  the open due date
     * @param  CardDeadline|null  $deadline  the deadline of the same card on the same day, if this payment pays it
     * @param  AgendaReminderData|null  $reminder  the automatic reminder of that deadline
     * @param  bool  $reminderDone  whether a done automatic reminder of the same card and day goes with this paid payment
     */
    private function paymentEntry(Deadline $payment, ?CardDeadline $deadline = null, ?AgendaReminderData $reminder = null, bool $reminderDone = false): AgendaEntryData
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
            detail: $card instanceof Model ? $this->paymentDetail($title, $subtitle, $kind) : $subtitle,
            area: $card instanceof Model ? CardTypes::for($card)->area()->value : AgendaData::GENERAL_AREA,
            person_id: $person?->id,
            person: $person?->first_name,
            amount: $payment->occurrence->amount,
            estimated: $payment->occurrence->estimated,
            reminder_done: $reminderDone || ($deadline?->reminderDone() ?? false),
            reminder: $reminder,
            href: route('budget.deadlines.show', ['kind' => $payment->kind(), 'id' => $planned->id, 'date' => $originalDate]).'?pay=1',
            account: $payment->account?->name,
            planned: __(':title, :repeats', ['title' => $planned->displayTitle(), 'repeats' => $this->budgets->every($planned, false)]),
        );
    }

    /**
     * The line below the name of a payment of a card: its title, or what it is when the title only repeats the card's name («Netflix» paid to Netflix).
     *
     * @param  string  $title  the title of the payment
     * @param  string  $name  the name of the card
     * @param  'payment'|'income'  $kind  payment or income
     */
    private function paymentDetail(string $title, string $name, string $kind): string
    {
        if (mb_strtolower(trim($title)) !== mb_strtolower(trim($name))) {
            return $title;
        }

        return $kind === 'payment' ? __('Payment') : __('Income entry');
    }

    /**
     * How the Agenda names an account: the first name of its person, the account name when it has none.
     *
     * @param  User  $user  the account, with `person` loaded
     */
    private function shortName(User $user): string
    {
        return $user->person instanceof Person ? $user->person->first_name : $user->name;
    }

    /**
     * A reminder as the Agenda shows it: to do, late or done, with its next notice, the days and the recipients of
     * all its notices, who marked it done and what whoever looks may do with it.
     *
     * @param  Reminder  $reminder  the reminder
     * @param  CarbonImmutable  $today  today in Rome
     * @param  Collection<int, ReminderGroup>  $groups  the groups of notices of the family
     * @param  Collection<int, User>  $accounts  the accounts of the family, with `person` loaded
     */
    public function reminderOf(Reminder $reminder, CarbonImmutable $today, Collection $groups, Collection $accounts): AgendaReminderData
    {
        $done = $reminder->status === ReminderStatus::Done;
        $rules = Notices::rulesFor($reminder, $groups);
        $day = CarbonImmutable::parse($reminder->day()->toDateString(), $today->timezone);
        $days = array_map(static fn (NotificationRule $rule): string => $day->addDays($rule->offset)->toDateString(), $rules);
        sort($days);
        $recipients = array_values(array_unique(array_map(
            fn (Delivery $delivery): string => $this->shortName($delivery->user),
            Notices::deliveriesOf($rules, $accounts),
        )));
        $late = $reminder->day()->toDateString() < $today->toDateString();

        return new AgendaReminderData(
            state: match (true) {
                $done => AgendaReminderState::Done,
                $late => AgendaReminderState::Late,
                default => AgendaReminderState::Open,
            },
            next_notice: $done ? null : Notices::next($reminder, $rules, $today)?->toDateString(),
            id: $reminder->id,
            notice_days: $done ? [] : $days,
            recipients: $done ? [] : $recipients,
            done_on: $done ? $reminder->updated_at?->setTimezone($today->timezone)->toDateString() : null,
            done_by: $done && ($closer = $accounts->firstWhere('id', $reminder->updated_by)) instanceof User ? $this->shortName($closer) : null,
            can_edit: Gate::allows('update', $reminder),
            can_delete: Gate::allows('delete', $reminder),
        );
    }

    /**
     * The accounts of the family with their person, who receive the notices.
     *
     * @return Collection<int, User>
     */
    public function accounts(): Collection
    {
        return User::ofCurrentFamily();
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
