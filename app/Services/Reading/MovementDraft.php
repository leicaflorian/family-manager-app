<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\EventValuesData;
use App\Data\Reading\Extraction;
use App\Data\Reading\MovementValuesData;
use App\Enums\HealthEventType;
use App\Enums\IncomeType;
use App\Models\Employment;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\CurrentFamily;
use App\Support\Money;
use App\Support\Reading\Choices;
use App\Support\Reading\ReadingText;
use App\Support\Reading\ValidDate;
use Carbon\CarbonImmutable;

/**
 * What a read file proposes besides the document: the expense (or the income, for a payslip) and the health event,
 * each with whether it starts ticked. The `expense`, `income` and `health` profiles of the old reader and its
 * `movementSide` rules.
 */
final readonly class MovementDraft
{
    /** The kinds of paper that always propose an expense that starts ticked. */
    public const array EXPENSE_KINDS = ['utility_bill', 'receipt', 'invoice', 'payment_receipt', 'road_tax'];

    /**
     * @param  Matcher  $matcher  the exact searches of accounts and jobs
     * @param  LinkResolver  $links  what the identifiers on the paper point to
     * @param  MovementSearch  $search  the commitment a paper belongs to
     */
    public function __construct(private Matcher $matcher, private LinkResolver $links, private MovementSearch $search) {}

    /**
     * The expense or income and the event of a reading.
     *
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists the model chose from; its family is where the cards are searched
     * @param  string  $locale  the language of the titles
     * @return array{movement: ?MovementValuesData, movementSuggested: bool, event: ?EventValuesData, eventSuggested: bool}
     */
    public function of(Extraction $x, ReadingContext $context, string $locale): array
    {
        return $context->familyId === null
            ? $this->build($x, $context, $locale)
            : CurrentFamily::using($context->familyId, fn (): array => $this->build($x, $context, $locale));
    }

    /**
     * Builds both proposals in the current family.
     *
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists the model chose from
     * @param  string  $locale  the language of the titles
     * @return array{movement: ?MovementValuesData, movementSuggested: bool, event: ?EventValuesData, eventSuggested: bool}
     */
    private function build(Extraction $x, ReadingContext $context, string $locale): array
    {
        $links = $this->links->resolve($x, $context);
        $movement = $x->kind === 'payslip' ? $this->income($x, $context, $links, $locale) : $this->expense($x, $context, $links, $locale);
        $event = $this->event($x, $links, $locale);

        return [
            'movement' => $movement,
            'movementSuggested' => $x->kind === 'payslip'
                || in_array($x->kind, self::EXPENSE_KINDS, true)
                || ($x->kind === 'insurance' && $movement->lead !== null)
                || ($x->kind === 'medical' && $x->total_amount !== null),
            'event' => $event,
            'eventSuggested' => $event instanceof EventValuesData && $event->type !== HealthEventType::Prescription->value,
        ];
    }

    /**
     * The expense: dated on the deadline (on the day of the payment for a payment receipt), linked to the
     * commitment of the utility, policy, vehicle (road tax) or subscription when there is one.
     *
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists the model chose from
     * @param  ResolvedLinks  $links  what the paper points to
     * @param  string  $locale  the language of the title
     */
    private function expense(Extraction $x, ReadingContext $context, ResolvedLinks $links, string $locale): MovementValuesData
    {
        $paid = $x->kind === 'payment_receipt';
        $issue = ValidDate::of($x->issue_date);
        $due = ValidDate::of($x->due_date);
        $date = $paid ? $issue ?? $due : $due ?? $issue;

        $card = $links->utility ?? $links->policy ?? ($x->kind === 'road_tax' ? $links->vehicle : null) ?? $links->subscription;
        $planned = $this->search->planned('expense', $card, null, ValidDate::carbon($date));
        $planned = $planned instanceof PlannedExpense ? $planned : null;

        $total = $x->total_amount === null ? null : Money::cents($x->total_amount);
        $fee = $paid && $x->fee_amount !== null && $x->fee_amount > 0 ? Money::cents($x->fee_amount) : null;
        $amount = $total === null ? null : ($paid ? $total + ($fee ?? 0) : $total) / 100;

        return new MovementValuesData(
            kind: 'expense',
            title: $this->title($x, $planned?->title, $date, $links, $locale),
            amount: $amount,
            fee_amount: $fee === null ? null : $fee / 100,
            date: $date,
            due_date: $due,
            organization_id: $links->organizationId ?? $planned?->organization_id,
            category_id: $planned->category_id ?? Choices::pick($context->expenseCategories, $x->expense_category_id),
            financial_account_id: $this->accountId($x, $planned),
            person_id: $planned->person_id ?? $links->personId,
            income_type: null,
            employment_id: null,
            notes: $this->notes($x, $locale),
            lead: $this->lead($planned),
        );
    }

    /**
     * The income of a payslip: dated on its issue day, titled by the month it pays, linked to the job of the person.
     *
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists the model chose from
     * @param  ResolvedLinks  $links  what the paper points to
     * @param  string  $locale  the language of the title
     */
    private function income(Extraction $x, ReadingContext $context, ResolvedLinks $links, string $locale): MovementValuesData
    {
        $date = ValidDate::of($x->issue_date) ?? ValidDate::of($x->period_end);
        $month = ValidDate::of($x->period_start) ?? $date;

        $employment = $this->matcher->employment($links->personId, Choices::pick($context->organizations, $x->organization_id), ValidDate::carbon($month));
        $planned = $this->search->planned('income', null, $employment, ValidDate::carbon($date));
        $planned = $planned instanceof PlannedIncome ? $planned : null;

        return new MovementValuesData(
            kind: 'income',
            title: $this->title($x, $planned?->title, $month, $links, $locale),
            amount: $x->total_amount === null ? null : Money::cents($x->total_amount) / 100,
            fee_amount: null,
            date: $date,
            due_date: null,
            organization_id: $employment->organization_id ?? $links->organizationId ?? $planned?->organization_id,
            category_id: null,
            financial_account_id: $this->accountId($x, $planned),
            person_id: $planned->person_id ?? $links->personId ?? $employment?->person_id,
            income_type: $planned?->type->value ?? $x->income_type ?? IncomeType::Salary->value,
            employment_id: $employment instanceof Employment ? $employment->id : null,
            notes: $x->summary,
            lead: $this->lead($planned),
        );
    }

    /**
     * The health event of a medical paper, or of any paper with a health type. The patient, the clinic and the date
     * are those of the document the event belongs to.
     *
     * @param  Extraction  $x  what the model read
     * @param  ResolvedLinks  $links  what the paper points to
     * @param  string  $locale  the language of the title
     */
    private function event(Extraction $x, ResolvedLinks $links, string $locale): ?EventValuesData
    {
        if ($x->kind !== 'medical' && ($x->health_type === null || $x->health_type === '')) {
            return null;
        }

        return new EventValuesData(
            title: $x->title ?? ($x->health_type === null ? null : $this->say("enums.health_event_type.{$x->health_type}", [], $locale)) ?? $x->summary,
            type: $x->health_type,
            person_id: $links->personId,
            organization_id: $links->organizationId,
            date: ValidDate::of($x->issue_date) ?? ValidDate::of($x->due_date),
            next_checkup_date: ValidDate::of($x->next_checkup_date),
            outcome: $x->outcome,
            notes: $x->summary,
        );
    }

    /**
     * The title: the commitment's with the month and year, otherwise what the paper says it is, otherwise the issuer.
     *
     * @param  Extraction  $x  what the model read
     * @param  string|null  $plannedTitle  the title of the commitment found
     * @param  string|null  $date  `YYYY-MM-DD`, the day whose month is written
     * @param  ResolvedLinks  $links  what the paper points to
     * @param  string  $locale  the language of the title
     */
    private function title(Extraction $x, ?string $plannedTitle, ?string $date, ResolvedLinks $links, string $locale): string
    {
        $day = ValidDate::carbon($date);

        if ($plannedTitle !== null && $day instanceof CarbonImmutable) {
            return $this->say(':title :month :year', ['title' => $plannedTitle, 'month' => ReadingText::month($day, $locale), 'year' => $day->format('Y')], $locale);
        }

        return $x->title ?? $links->issuer ?? $this->say('Document', [], $locale);
    }

    /**
     * The account of a payment: the IBAN on the paper, then the commitment's, then the default one.
     *
     * @param  Extraction  $x  what the model read
     * @param  PlannedExpense|PlannedIncome|null  $planned  the commitment found
     */
    private function accountId(Extraction $x, PlannedExpense|PlannedIncome|null $planned): ?int
    {
        return $this->matcher->account($x->iban_last_four)->id ?? $planned->financial_account_id ?? $this->matcher->defaultAccount()?->id;
    }

    /**
     * The notes of an expense: the summary and the period the paper covers.
     *
     * @param  Extraction  $x  what the model read
     * @param  string  $locale  the language of the period line
     */
    private function notes(Extraction $x, string $locale): ?string
    {
        $start = ValidDate::carbon($x->period_start);
        $end = ValidDate::carbon($x->period_end);
        $period = $start instanceof CarbonImmutable && $end instanceof CarbonImmutable
            ? $this->say('Period: :from – :to', ['from' => $start->format('d/m/Y'), 'to' => $end->format('d/m/Y')], $locale)
            : null;

        $lines = array_filter([$x->summary, $period], static fn (?string $line): bool => $line !== null && $line !== '');

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * The commitment as the page shows it: what it is called and what it was planned at.
     *
     * @param  PlannedExpense|PlannedIncome|null  $planned  the commitment found
     * @return array{id: int, title: string, amount: float, is_estimated: bool}|null
     */
    private function lead(PlannedExpense|PlannedIncome|null $planned): ?array
    {
        return $planned === null
            ? null
            : ['id' => $planned->id, 'title' => $planned->title ?? '', 'amount' => (float) $planned->amount, 'is_estimated' => $planned->is_estimated];
    }

    /**
     * A translated text in the language of the proposal.
     *
     * @param  string  $key  the English text, or the key of an enum label
     * @param  array<string, string|int|null>  $replace  the placeholders
     * @param  string  $locale  the language
     */
    private function say(string $key, array $replace, string $locale): string
    {
        $text = __($key, $replace, $locale);

        return is_string($text) ? $text : $key;
    }
}
