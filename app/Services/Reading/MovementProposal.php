<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Data\Reading\DueData;
use App\Data\Reading\Extraction;
use App\Data\Reading\ImportChoices;
use App\Data\Reading\MovementProposalData;
use App\Data\Reading\MovementValuesData;
use App\Enums\DueMode;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\Deadline;
use App\Support\Money;
use App\Support\Reading\ValidDate;
use App\Support\Translated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The movement side of a file (`movementSide` of the old reader): the expense or income with the corrections of the
 * person, what is missing to create it, the movement already recorded it is linked to instead, "already paid" or
 * "to pay" and the due date a payment receipt pays. It runs in the family of the file and writes nothing; its texts
 * are in the language of the app, which {@see Proposal} sets.
 */
final readonly class MovementProposal
{
    /**
     * @param  MovementSearch  $search  the duplicates and the payments
     * @param  DueSearch  $dues  "to pay" and the unpaid due dates
     * @param  ExpenseChanges  $changes  what the expense found takes from the file
     */
    public function __construct(private MovementSearch $search, private DueSearch $dues, private ExpenseChanges $changes) {}

    /**
     * The proposal of the movement, in the order of `movementSide`.
     *
     * @param  Extraction  $x  what the model read
     * @param  ReadingContext  $context  the lists the model chose from
     * @param  MovementValuesData  $draft  the movement the reading proposes ({@see MovementDraft})
     * @param  bool  $suggested  whether its box starts ticked
     * @param  ImportChoices  $choices  the choices of the person
     * @param  CarbonImmutable|null  $documentDate  the date of the document as it will be saved: the movement takes it when it has none
     */
    public function of(Extraction $x, ReadingContext $context, MovementValuesData $draft, bool $suggested, ImportChoices $choices, ?CarbonImmutable $documentDate = null): MovementProposalData
    {
        $kind = $draft->kind;
        $expense = $kind === 'expense';
        $edits = $choices->movementOverrides($kind);

        // 2. The deadline is the one written by the person, otherwise the one read; emptied, the read one comes back
        $dueDate = $expense ? ($this->date($edits['due_date'] ?? null) ?? ValidDate::of($x->due_date)) : null;
        $values = MovementValuesData::from([...$draft->toArray(), ...$edits, 'due_date' => $dueDate]);
        $dueAuto = $expense && DueSearch::suggested($x->kind, $suggested, ValidDate::carbon($dueDate), DueSearch::today());
        $wantsDue = $expense && ($choices->due ?? $dueAuto);

        // 3. The date: the movement's, or the deadline of one to pay, otherwise the document's (the person may have written it there)
        $date = $values->date ?? ($wantsDue ? $dueDate : $documentDate?->toDateString());
        $values = $values->date === null && ! $wantsDue && $date !== null ? MovementValuesData::from([...$values->toArray(), 'date' => $date]) : $values;
        $day = ValidDate::carbon($date);
        $chosen = $this->chosen($kind, $choices->targets['movement']);
        $target = $chosen instanceof Model || ! is_int($choices->targets['movement']) ? $choices->targets['movement'] : 'auto';
        $lead = $this->lead($kind, $values);

        // 4. The one picked is linked as it is: only a new movement needs an amount and a date
        $blocked = ! $chosen instanceof Model && ($values->amount === null || ! $day instanceof CarbonImmutable)
            ? ($wantsDue && $values->amount !== null ? $this->noDueDate() : $this->noAmount($kind))
            : null;

        // 5. The movement already recorded; the fee is inside the amount of an expense, the paper says the amount without it
        $net = (Money::cents($values->amount) - Money::cents($values->fee_amount)) / 100;
        $duplicate = $chosen;
        $others = 0;
        if (! $chosen instanceof Model && $blocked === null && $target !== 'new' && $day instanceof CarbonImmutable) {
            $duplicate = $lead instanceof Model ? $this->search->duplicate($kind, $lead, $day) : null;
            if (! $duplicate instanceof Model && $expense && $x->kind === 'payment_receipt') {
                ['match' => $duplicate, 'others' => $others] = $this->search->paid($net, $day, $values->organization_id);
            } elseif (! $duplicate instanceof Model) {
                $duplicate = $this->search->similar($kind, $net, $day, $values->organization_id);
            }
        }

        // 6. "To pay" is offered for an expense not recorded yet: the one found is linked as today
        $due = null;
        if ($expense && ! $duplicate instanceof Model) {
            [$due, $blocked] = $this->due($dueAuto, $wantsDue, $dueDate, $blocked, $lead, $values);
        }

        // 7. A payment receipt that pays no expense: the unpaid due date it pays
        $pays = ! $chosen instanceof Model && $blocked === null && ! $duplicate instanceof Model && (! $due instanceof DueData || ! $wantsDue)
            && $target !== 'new' && $expense && $x->kind === 'payment_receipt' && $day instanceof CarbonImmutable
            ? $this->pays($net, $day, $values->organization_id, $lead)
            : null;

        // 8. What a new movement still needs, and where the person writes it
        $new = ! $duplicate instanceof Model;
        $fill = $new ? array_values(array_filter([
            $values->amount === null ? 'amount' : null,
            $wantsDue && ! ValidDate::carbon($dueDate) instanceof CarbonImmutable ? 'due_date' : null,
        ])) : [];
        $complete = $new && ! $wantsDue && ! $day instanceof CarbonImmutable ? ['date'] : [];

        // 9. What the expense found takes from the file
        $changes = $duplicate instanceof Expense ? $this->changes->of($duplicate, $values, $x, $context) : ['changes' => [], 'warnings' => []];

        return new MovementProposalData(
            values: $values,
            suggested: $suggested,
            duplicate: $duplicate instanceof Model ? $this->existing($duplicate) : null,
            changes: $changes['changes'],
            warnings: $changes['warnings'],
            others: $others,
            blocked: $blocked,
            due: $due,
            pays: $pays,
            candidates: $this->search->near($kind, $day ?? DueSearch::today()),
            fill: $fill,
            complete: $complete,
        );
    }

    /**
     * "Already paid" or "to pay" and, with "to pay" in effect and the deadline there, what it does with it.
     *
     * @param  bool  $suggested  the automatic choice
     * @param  bool  $wanted  "to pay" is in effect
     * @param  string|null  $dueDate  the deadline
     * @param  string|null  $blocked  what is missing so far
     * @param  PlannedExpense|PlannedIncome|null  $lead  the commitment of the paper
     * @param  MovementValuesData  $values  the expense
     * @return array{DueData, ?string} the side and what is missing, with the deadline if it is that
     */
    private function due(bool $suggested, bool $wanted, ?string $dueDate, ?string $blocked, PlannedExpense|PlannedIncome|null $lead, MovementValuesData $values): array
    {
        $day = ValidDate::carbon($dueDate);

        if (! $wanted || $blocked !== null || ! $day instanceof CarbonImmutable) {
            return [
                new DueData($suggested, $dueDate, null, null, DueMode::New),
                $wanted && ! $day instanceof CarbonImmutable ? $blocked ?? $this->noDueDate() : $blocked,
            ];
        }

        ['mode' => $mode, 'plan' => $plan, 'occurrence' => $occurrence] = $this->dues->branch(
            $lead instanceof PlannedExpense ? $lead : null,
            $day,
            (float) $values->amount,
            $values->organization_id,
        );
        $planDate = $occurrence ?? $plan?->start_date;

        return [
            new DueData(
                suggested: $suggested,
                date: $dueDate,
                plan: $plan instanceof PlannedExpense ? ['id' => $plan->id, 'title' => $plan->title, 'date' => (string) $planDate?->toDateString()] : null,
                occurrence: $occurrence?->toDateString(),
                mode: $mode,
            ),
            null,
        ];
    }

    /**
     * The unpaid due date a payment receipt pays; a receipt with a commitment pays only a due date of that one.
     *
     * @param  float  $net  the amount paid without the fee
     * @param  CarbonImmutable  $day  the day of the payment
     * @param  int|null  $organizationId  who was paid
     * @param  PlannedExpense|PlannedIncome|null  $lead  the commitment of the paper
     * @return array{planned: int, title: string, date: string, occurrence: string, others: int}|null
     */
    private function pays(float $net, CarbonImmutable $day, ?int $organizationId, PlannedExpense|PlannedIncome|null $lead): ?array
    {
        ['match' => $match, 'others' => $others] = $this->dues->unpaid($net, $day, $organizationId, $lead instanceof PlannedExpense ? $lead : null);

        return $match instanceof Deadline ? [
            'planned' => $match->planned->id,
            'title' => (string) $match->planned->title,
            'date' => $match->occurrence->date->toDateString(),
            'occurrence' => $match->occurrence->originalDate->toDateString(),
            'others' => $others,
        ] : null;
    }

    /**
     * The movement picked by hand, if it is still there in the family; one gone is looked for again (`auto`), and
     * the confirmation tells the person.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  'auto'|'new'|int  $target  the choice of the person
     */
    private function chosen(string $kind, string|int $target): Expense|Income|null
    {
        if (! is_int($target)) {
            return null;
        }

        return $kind === 'income' ? Income::query()->find($target) : Expense::query()->find($target);
    }

    /**
     * The commitment the movement belongs to.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  MovementValuesData  $values  the movement, whose `lead` names it
     */
    private function lead(string $kind, MovementValuesData $values): PlannedExpense|PlannedIncome|null
    {
        $id = $values->lead['id'] ?? null;

        if ($id === null) {
            return null;
        }

        return $kind === 'income' ? PlannedIncome::query()->find($id) : PlannedExpense::query()->find($id);
    }

    /**
     * A movement as the page names it.
     *
     * @param  Expense|Income  $movement  the movement
     * @return array{id: int, title: string, date: string}
     */
    private function existing(Expense|Income $movement): array
    {
        return ['id' => $movement->id, 'title' => (string) $movement->title, 'date' => (string) $movement->date?->toDateString()];
    }

    /**
     * A date corrected by the person.
     *
     * @param  mixed  $value  the cleaned correction
     */
    private function date(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * What blocks "to pay" with no deadline.
     */
    private function noDueDate(): string
    {
        return Translated::text('Deadline not found: to create the expense to pay it must be entered.');
    }

    /**
     * What blocks a movement with no amount or date.
     *
     * @param  string  $kind  `expense` or `income`
     */
    private function noAmount(string $kind): string
    {
        return $kind === 'income'
            ? Translated::text('Amount or date not found: to create the income they must be entered.')
            : Translated::text('Amount or date not found: to create the expense they must be entered.');
    }
}
