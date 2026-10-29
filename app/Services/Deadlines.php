<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\CurrentFamily;
use App\Support\Deadline;
use App\Support\DefaultAccount;
use App\Support\Occurrence;
use App\Support\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The due dates of planned expenses and incomes and what settles them: the
 * {@see Occurrences} rules joined to the expenses and incomes that name each
 * due date (`occurrence_date`) and to the documents waiting to be paid.
 * Exceptions, payments and documents are read with one query per table, always
 * through the family scope; planned items of another family are ignored.
 */
final readonly class Deadlines
{
    /** How many months back a due date still counts as late. */
    private const int MONTHS_BACK = 12;

    /** How many months ahead the next due date is looked for. */
    private const int MONTHS_AHEAD = 24;

    /** How many years around a date `find()` looks, because an exception can move a due date. */
    private const int FIND_YEARS = 2;

    public function __construct(private Occurrences $occurrences) {}

    /**
     * The due dates whose real date falls between two days, both included, in order of date.
     *
     * @param  iterable<PlannedExpense|PlannedIncome>  $planned  The planned items to look at.
     * @param  CarbonImmutable  $from  The first day.
     * @param  CarbonImmutable  $to  The last day.
     * @return list<Deadline>
     */
    public function between(iterable $planned, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $expenses = new Collection;
        $incomes = new Collection;

        foreach ($planned as $item) {
            if ($item->family_id !== CurrentFamily::id()) {
                continue;
            }

            if ($item instanceof PlannedExpense) {
                $expenses->push($item);
            } else {
                $incomes->push($item);
            }
        }

        // Everything `DeadlineData::fromDeadline` reads is loaded here, once per collection
        $expenses->loadMissing(['exceptions', 'financialAccount', 'person', ...$this->cardRelations(new PlannedExpense, ['utility', 'insurancePolicy', 'loan', 'enrollment', 'home', 'vehicle', 'subscription'])]);
        $incomes->loadMissing(['exceptions', 'financialAccount', 'person', ...$this->cardRelations(new PlannedIncome, ['employment'])]);

        $payments = $this->groupBy(Expense::query()->with('financialAccount')->whereIn('planned_expense_id', $expenses->modelKeys())->orderBy('date')->get(), 'planned_expense_id')
            + $this->groupBy(Income::query()->with('financialAccount')->whereIn('planned_income_id', $incomes->modelKeys())->orderBy('date')->get(), 'planned_income_id', 'income');
        $documents = $this->groupBy(Document::query()->whereIn('planned_expense_id', $expenses->modelKeys())->get(), 'planned_expense_id');

        // The default account is looked up once for the whole collection, and only if some planned item has no account
        $default = null;
        $defaultLoaded = false;
        $found = [];

        foreach ([...$expenses->all(), ...$incomes->all()] as $item) {
            $key = $this->key($item);
            $schedule = Schedule::of($item);

            if ($item->financialAccount === null && ! $defaultLoaded) {
                $default = DefaultAccount::model();
                $defaultLoaded = true;
            }

            $account = $item->financialAccount ?? $default;

            foreach ($this->occurrences->between($schedule, $from, $to) as $occurrence) {
                $found[] = new Deadline(
                    $item,
                    $occurrence,
                    $this->payment($schedule, $occurrence, $payments[$key] ?? []),
                    $this->title($item, $schedule, $occurrence, $documents[$key] ?? []),
                    $account,
                );
            }
        }

        usort($found, static fn (Deadline $a, Deadline $b): int => $a->occurrence->date <=> $b->occurrence->date);

        return $found;
    }

    /**
     * The relations to the cards of a planned item, each with what the row of that card reads
     * (`CardType::listRelations()`), so a new card type cannot bring back a query per card.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  A planned item, to find the card model of each relation.
     * @param  list<string>  $relations  The relations of the planned item that lead to a card.
     * @return list<string> For example `utility`, `utility.organization`.
     */
    private function cardRelations(PlannedExpense|PlannedIncome $planned, array $relations): array
    {
        $loads = [];

        foreach ($relations as $relation) {
            $loads[] = $relation;
            $model = $planned->{$relation}()->getRelated()::class;
            $type = array_find(CardTypes::all(), static fn (CardType $type): bool => $type->model() === $model);

            foreach ($type?->listRelations() ?? [] as $nested) {
                $loads[] = $relation.'.'.$nested;
            }
        }

        return $loads;
    }

    /**
     * The due date of a planned item that the rules give on a day.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     * @param  CarbonImmutable  $originalDate  The date the rules give, before any exception moves it.
     *
     * @throws NotFoundHttpException if the day is not a due date of the planned item
     */
    public function find(PlannedExpense|PlannedIncome $planned, CarbonImmutable $originalDate): Deadline
    {
        $day = $originalDate->toDateString();

        foreach ($this->between([$planned], $originalDate->subYears(self::FIND_YEARS), $originalDate->addYears(self::FIND_YEARS)) as $deadline) {
            if ($deadline->occurrence->originalDate->toDateString() === $day) {
                return $deadline;
            }
        }

        throw new NotFoundHttpException;
    }

    /**
     * The due date to show first: the oldest one still open, which is a late one if there is any, otherwise the next.
     * It looks from 12 months before today to 24 months after.
     *
     * @param  iterable<PlannedExpense|PlannedIncome>  $planned  The planned items to look at.
     * @param  CarbonImmutable  $today  Today.
     */
    public function next(iterable $planned, CarbonImmutable $today): ?Deadline
    {
        $from = $today->subMonthsNoOverflow(self::MONTHS_BACK);
        $to = $today->addMonthsNoOverflow(self::MONTHS_AHEAD);

        return array_find($this->between($planned, $from, $to), static fn (Deadline $deadline): bool => ! $deadline->isPaid());
    }

    /**
     * The sum of the due dates of the next 12 months, from today to the same day a year later minus one day, exceptions applied.
     *
     * @param  iterable<PlannedExpense|PlannedIncome>  $planned  The planned items to look at.
     * @param  CarbonImmutable  $today  Today.
     */
    public function yearTotal(iterable $planned, CarbonImmutable $today): float
    {
        $total = array_sum(array_map(
            static fn (Deadline $deadline): float => $deadline->occurrence->amount,
            $this->between($planned, $today, $today->addYear()->subDay()),
        ));

        return round($total, 2);
    }

    /**
     * The open due dates: the late ones (up to 12 months back) and those up to a day.
     *
     * @param  iterable<PlannedExpense|PlannedIncome>  $planned  The planned items to look at.
     * @param  CarbonImmutable  $until  The last day.
     * @param  CarbonImmutable  $today  Today.
     * @return list<Deadline>
     */
    public function unpaidUntil(iterable $planned, CarbonImmutable $until, CarbonImmutable $today): array
    {
        return array_values(array_filter(
            $this->between($planned, $today->subMonthsNoOverflow(self::MONTHS_BACK), $until),
            static fn (Deadline $deadline): bool => ! $deadline->isPaid(),
        ));
    }

    /**
     * The expense or income that settles an occurrence: the one that names its original date.
     * A one-off planned item is settled by any payment linked to it.
     *
     * @param  Schedule  $schedule  The recurrence.
     * @param  Occurrence  $occurrence  The occurrence.
     * @param  list<Expense|Income>  $payments  The payments of the planned item, by date.
     */
    private function payment(Schedule $schedule, Occurrence $occurrence, array $payments): Expense|Income|null
    {
        $day = $occurrence->originalDate->toDateString();

        return array_find(
            $payments,
            static fn (Expense|Income $payment): bool => ! $schedule->recurring || $payment->occurrence_date?->toDateString() === $day,
        );
    }

    /**
     * What to call an occurrence: the title of the closest document to pay whose date is in its window, else the planned item's.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     * @param  Schedule  $schedule  The recurrence.
     * @param  Occurrence  $occurrence  The occurrence.
     * @param  list<Document>  $documents  The documents to pay of the planned item.
     */
    private function title(PlannedExpense|PlannedIncome $planned, Schedule $schedule, Occurrence $occurrence, array $documents): string
    {
        $best = null;
        $bestGap = INF;

        foreach ($documents as $document) {
            if (! $document->date instanceof CarbonImmutable || ! $this->occurrences->inWindow($schedule, $occurrence, $document->date)) {
                continue;
            }

            $gap = abs($occurrence->date->startOfDay()->diffInDays($document->date->startOfDay(), false));

            if ($gap < $bestGap) {
                $best = $document;
                $bestGap = $gap;
            }
        }

        if ($best instanceof Document) {
            return $best->title;
        }

        return $planned instanceof PlannedExpense ? $planned->title : $planned->title ?? $planned->type->label();
    }

    /**
     * The rows grouped by the planned item they belong to.
     *
     * @template T of Expense|Income|Document
     *
     * @param  Collection<int, T>  $rows  The rows.
     * @param  string  $column  The column that holds the planned item's id.
     * @param  string  $kind  `expense` or `income`: ids of the two tables can be the same number.
     * @return array<string, list<T>>
     */
    private function groupBy(Collection $rows, string $column, string $kind = 'expense'): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $id = $row->getAttribute($column);

            if (is_int($id)) {
                $grouped["{$kind}:{$id}"][] = $row;
            }
        }

        return $grouped;
    }

    /**
     * The key of a planned item among the groups: its kind and id, since the ids of the two tables can be the same number.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     */
    private function key(PlannedExpense|PlannedIncome $planned): string
    {
        return ($planned instanceof PlannedExpense ? 'expense' : 'income').":{$planned->id}";
    }
}
