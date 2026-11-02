<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Data\CardNoticeData;
use App\Enums\DocumentStatus;
use App\Enums\NoticeTone;
use App\Models\Document;
use App\Models\Filing;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Models\User;
use App\Support\ItalianArticle;
use App\Support\ModelId;
use App\Support\Reminders\Notices;
use App\Support\Translated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The notices the reminders give at the top of a card: the deadline that is near or past, the planned expense that
 * is missing, a linked document that expires and the documents the card still expects. The notices that the card type
 * writes from the saved data come from `CardType::notices()`.
 */
final readonly class CardNotices
{
    /** How many days ahead a deadline or a document is worth a notice. */
    private const int DAYS_AHEAD = 60;

    public function __construct(private MissingExpenses $missing, private ExpectedDocuments $expected) {}

    /**
     * The notices of a card.
     *
     * @param  Model  $card  a card of the current family
     * @param  CarbonImmutable  $today  today in Rome
     * @param  User|null  $user  whoever looks: without the right to create a planned expense the notice has no *Add*; null for everybody
     * @return list<CardNoticeData>
     */
    public function for(Model $card, CarbonImmutable $today, ?User $user = null): array
    {
        $today = $today->startOfDay();
        $reminders = $this->openReminders($card);
        $near = $reminders->first(fn (Reminder $reminder): bool => $this->due($reminder, $today)->lessThanOrEqualTo($today->addDays(self::DAYS_AHEAD)));
        $notices = [];

        if ($near instanceof Reminder) {
            $notices[] = $this->deadline($near, $today);
        }

        if ($this->missing->of($reminders, $today) !== []) {
            $notices[] = $this->missingExpense($card, $user);
        }

        array_push($notices, ...$this->expiringDocuments($card, $today, $near));
        array_push($notices, ...$this->expectedDocuments($card, $today));

        return $notices;
    }

    /**
     * The open automatic reminders linked to the card, the nearest first.
     *
     * @return Collection<int, Reminder>
     */
    private function openReminders(Model $card): Collection
    {
        return Reminder::query()
            ->open()
            ->where('is_automatic', true)
            ->whereHas('link', static fn (Builder $link): Builder => $link->where('remindable_type', $card->getMorphClass())->where('remindable_id', ModelId::of($card)))
            ->with('link')
            ->oldest('due_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * The deadline that is near or past: how far it is, when the notices go out and what the kind of deadline adds.
     */
    private function deadline(Reminder $reminder, CarbonImmutable $today): CardNoticeData
    {
        $due = $this->due($reminder, $today);
        $days = (int) $today->diffInDays($due, false);
        $title = match (true) {
            $days > 0 => trans_choice('{1} Expires in :count day|[2,*] Expires in :count days', $days),
            $days === 0 => Translated::text('Expires today'),
            default => trans_choice('{1} Overdue by :count day|[2,*] Overdue by :count days', -$days),
        };

        return new CardNoticeData(
            'deadline',
            $days < 0 ? NoticeTone::Danger : NoticeTone::Warning,
            $title,
            ItalianArticle::elide(implode(' ', array_filter([$this->noticeText($reminder, $due, $today), $this->sentence($reminder, $due)]))) ?: null,
        );
    }

    /**
     * When the first notice went out, or when the next one will.
     */
    private function noticeText(Reminder $reminder, CarbonImmutable $due, CarbonImmutable $today): ?string
    {
        $prefix = $due->toDateString().':';
        $sent = [];

        foreach ($reminder->notifications_sent ?? [] as $key) {
            if (is_string($key) && str_starts_with($key, $prefix) && is_numeric($offset = substr($key, strlen($prefix)))) {
                $sent[] = $due->addDays((int) $offset);
            }
        }

        if ($sent !== []) {
            return Translated::text('The reminder went out on :date.', ['date' => min($sent)->format('d/m/Y')]);
        }

        $next = Notices::next($reminder, Notices::rulesFor($reminder, ReminderGroup::query()->get()), $today);

        return $next instanceof CarbonImmutable ? Translated::text('We will let you know on :date.', ['date' => $next->format('d/m/Y')]) : null;
    }

    /**
     * What the kind of deadline adds, if anything.
     */
    private function sentence(Reminder $reminder, CarbonImmutable $due): ?string
    {
        $date = $due->format('d/m/Y');

        return match ($reminder->source_field) {
            'insurance_policies.expiry_date' => Translated::text('If you change company, mark this one as cancelled and create the new one on the same car.'),
            'belongings.warranty_expiry_date' => Translated::text('If something is wrong, have it checked by :date.', ['date' => $date]),
            'subscriptions.trial_end_date' => Translated::text('The free trial ends on :date. If you do not need it, cancel before.', ['date' => $date]),
            'filings.due_date' => Translated::text('When you submit it, mark it as sent: the reminder goes away by itself.'),
            default => null,
        };
    }

    /**
     * The planned expense that the card should have for the payment of its deadline.
     */
    private function missingExpense(Model $card, ?User $user): CardNoticeData
    {
        $canAdd = ! $user instanceof User || Gate::forUser($user)->allows('create', PlannedExpense::class);

        return new CardNoticeData(
            'missing-expense',
            NoticeTone::Warning,
            Translated::text('The planned expense is missing'),
            Translated::text('This payment is not in the budget.'),
            $canAdd ? Translated::text('Add') : null,
            $canAdd ? route('budget.planned.create', ['kind' => 'expense', 'card' => $card->getMorphClass().':'.ModelId::of($card)], false) : null,
        );
    }

    /**
     * The documents linked to the card that expire within 60 days or have expired. One that expires on the day of the
     * deadline already announced is left out: renewing the card renews it.
     *
     * @param  Reminder|null  $near  the reminder of the deadline notice, if there is one
     * @return list<CardNoticeData>
     */
    private function expiringDocuments(Model $card, CarbonImmutable $today, ?Reminder $near): array
    {
        if (! method_exists($card, 'documents')) {
            return [];
        }

        $notices = [];
        $documents = $card->documents()
            ->whereIn('status', [DocumentStatus::Active->value, DocumentStatus::Expired->value])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $today->addDays(self::DAYS_AHEAD)->toDateString())
            ->orderBy('expiry_date')
            ->get();

        foreach ($documents as $document) {
            if (! $document instanceof Document || ! $document->expiry_date instanceof CarbonImmutable || ($near instanceof Reminder && $document->expiry_date->isSameDay($near->due_date))) {
                continue;
            }

            $date = $document->expiry_date->format('d/m/Y');
            $expired = $document->expiry_date->lessThan($today);

            // The title of the document stays a title: a sentence built around its name would need its gender
            $notices[] = new CardNoticeData(
                'document:'.$document->id,
                $expired ? NoticeTone::Danger : NoticeTone::Warning,
                $document->title,
                ItalianArticle::elide(Translated::text($expired ? 'Expired on :date.' : 'Expires on :date.', ['date' => $date])),
                Translated::text('Upload the new one'),
                ExpectedDocuments::uploadHref($card),
            );
        }

        return $notices;
    }

    /**
     * What a vehicle or an ISEE file still lacks, with how many documents are collected for the file.
     *
     * @return list<CardNoticeData>
     */
    private function expectedDocuments(Model $card, CarbonImmutable $today): array
    {
        $found = $this->expected->for($card, $today);
        $notices = [];

        if ($card instanceof Filing && $found['expected'] > 0) {
            $notices[] = new CardNoticeData('documents-count', NoticeTone::Info, Translated::text('Documents :collected of :expected collected', ['collected' => $found['expected'] - count($found['missing']), 'expected' => $found['expected']]));
        }

        foreach ($found['missing'] as $position => $document) {
            $notices[] = new CardNoticeData('missing-document:'.$position, NoticeTone::Warning, Translated::text('Missing · :document', ['document' => $document]), null, Translated::text('Upload it'), ExpectedDocuments::uploadHref($card));
        }

        return $notices;
    }

    /**
     * A day of the reminder in Rome.
     */
    private function due(Reminder $reminder, CarbonImmutable $today): CarbonImmutable
    {
        return CarbonImmutable::parse($reminder->due_date->toDateString(), $today->getTimezone())->startOfDay();
    }
}
