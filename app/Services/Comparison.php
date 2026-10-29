<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CategoryComparisonData;
use App\Data\ComparisonData;
use App\Data\DeadlineData;
use App\Data\MonthBarData;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * *Planned and spent*: what the due dates of a month add up to against what was really spent, by main category, the
 * last six months, and the due dates still waiting for a payment. The due dates come from {@see Deadlines}.
 */
final readonly class Comparison
{
    /** How many months the chart shows, the month asked included. */
    private const int MONTHS = 6;

    public function __construct(private Deadlines $deadlines) {}

    /**
     * The comparison of a month.
     *
     * @param  CarbonImmutable  $month  Any day of the month.
     * @param  CarbonImmutable  $today  Today in Rome: it marks the month in progress and the late due dates.
     */
    public function of(CarbonImmutable $month, CarbonImmutable $today): ComparisonData
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();
        $month = $from->format('Y-m');
        $chartFrom = $from->subMonthsNoOverflow(self::MONTHS - 1);

        // One pass over the whole chart range gives the planned side of every month
        $deadlines = $this->deadlines->between(PlannedExpense::query()->get(), $chartFrom, $to);
        $expenses = Expense::query()->whereBetween('date', [$chartFrom->toDateString(), $to->toDateString()])->get(['id', 'amount', 'date', 'category_id']);

        $monthDeadlines = array_values(array_filter($deadlines, static fn (Deadline $deadline): bool => $deadline->occurrence->date->format('Y-m') === $month));
        $monthExpenses = $expenses->filter(static fn (Expense $expense): bool => $expense->date?->format('Y-m') === $month);
        $incomeDeadlines = $this->deadlines->between(PlannedIncome::query()->get(), $from, $to);

        return new ComparisonData(
            month: $from->format('Y-m'),
            planned: $this->sum(array_map(static fn (Deadline $deadline): float => $deadline->occurrence->amount, $monthDeadlines)),
            spent: $this->sum($monthExpenses->map(static fn (Expense $expense): float => (float) $expense->amount)->values()->all()),
            incomes: $this->sum(Income::query()->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get(['id', 'amount'])->map(static fn (Income $income): float => (float) $income->amount)->values()->all()),
            incomes_planned: $this->sum(array_map(static fn (Deadline $deadline): float => $deadline->occurrence->amount, $incomeDeadlines)),
            days_left: $from->isSameMonth($today) ? (int) $today->startOfDay()->diffInDays($today->endOfMonth()->startOfDay()) : null,
            categories: $this->categories($monthDeadlines, $monthExpenses, $today),
            months: $this->months($from, $today, $deadlines, $expenses),
            unpaid: $this->unpaid($monthDeadlines, $today),
        );
    }

    /**
     * One row per main category that has a due date or an expense in the month, in the order of the categories, and
     * the expenses without a category at the end.
     *
     * @param  list<Deadline>  $deadlines  The expense due dates of the month.
     * @param  Collection<int, Expense>  $expenses  The expenses of the month.
     * @param  CarbonImmutable  $today  Today in Rome.
     * @return list<CategoryComparisonData>
     */
    private function categories(array $deadlines, Collection $expenses, CarbonImmutable $today): array
    {
        $categories = ExpenseCategory::query()->orderBy('sort')->orderBy('name')->get()->keyBy('id');
        $mainOf = fn (?int $id): int => $this->mainCategory($categories, $id);

        $rows = [];

        foreach ($deadlines as $deadline) {
            $rows[$mainOf($deadline->planned instanceof PlannedExpense ? $deadline->planned->category_id : null)]['deadlines'][] = $deadline;
        }

        foreach ($expenses as $expense) {
            $rows[$mainOf($expense->category_id)]['expenses'][] = $expense;
        }

        $result = [];

        // The main categories come in their own order; 0 is «without a category» and stays last
        foreach ([...$categories->filter(static fn (ExpenseCategory $category): bool => $category->parent_id === null)->keys()->all(), 0] as $id) {
            if (! isset($rows[$id])) {
                continue;
            }

            /** @var list<Deadline> $planned */
            $planned = $rows[$id]['deadlines'] ?? [];
            $late = array_find($planned, static fn (Deadline $deadline): bool => $deadline->isLate($today));
            $category = $categories->get($id);

            $result[] = new CategoryComparisonData(
                name: $category instanceof ExpenseCategory ? $category->name : __('No category'),
                icon: $category?->icon,
                color: $category?->color,
                spent: $this->sum(array_map(static fn (Expense $expense): float => (float) $expense->amount, $rows[$id]['expenses'] ?? [])),
                planned: $this->sum(array_map(static fn (Deadline $deadline): float => $deadline->occurrence->amount, $planned)),
                estimated: $planned !== [] && array_all($planned, static fn (Deadline $deadline): bool => $deadline->occurrence->estimated),
                late_title: $late?->planned->title,
            );
        }

        return $result;
    }

    /**
     * The id of the main category of a category: itself when it has no parent. A loop in the data stops at the
     * first category seen twice.
     *
     * @param  Collection<int, ExpenseCategory>  $categories  All the categories, by id.
     * @param  int|null  $id  The category, null for none.
     * @return int The id of the main category, 0 when there is no category.
     */
    private function mainCategory(Collection $categories, ?int $id): int
    {
        $seen = [];

        while ($id !== null && ! isset($seen[$id])) {
            $seen[$id] = true;
            $parent = $categories->get($id)?->parent_id;

            if ($parent === null || ! $categories->has($parent)) {
                return $categories->has($id) ? $id : 0;
            }

            $id = $parent;
        }

        return $id ?? 0;
    }

    /**
     * The six months of the chart, ending with the month asked.
     *
     * @param  CarbonImmutable  $last  The first day of the last month.
     * @param  CarbonImmutable  $today  Today in Rome.
     * @param  list<Deadline>  $deadlines  The expense due dates of the six months.
     * @param  Collection<int, Expense>  $expenses  The expenses of the six months.
     * @return list<MonthBarData>
     */
    private function months(CarbonImmutable $last, CarbonImmutable $today, array $deadlines, Collection $expenses): array
    {
        $bars = [];

        for ($i = self::MONTHS - 1; $i >= 0; $i--) {
            $month = $last->subMonthsNoOverflow($i)->format('Y-m');
            $bars[] = new MonthBarData(
                month: $month,
                spent: $this->sum($expenses->filter(static fn (Expense $expense): bool => $expense->date?->format('Y-m') === $month)->map(static fn (Expense $expense): float => (float) $expense->amount)->values()->all()),
                planned: $this->sum(array_values(array_map(
                    static fn (Deadline $deadline): float => $deadline->occurrence->amount,
                    array_filter($deadlines, static fn (Deadline $deadline): bool => $deadline->occurrence->date->format('Y-m') === $month),
                ))),
                current: $month === $today->format('Y-m'),
            );
        }

        return $bars;
    }

    /**
     * The due dates nothing has settled, the late ones first, each group by date.
     *
     * @param  list<Deadline>  $deadlines  The expense due dates of the month, by date.
     * @param  CarbonImmutable  $today  Today in Rome.
     * @return list<DeadlineData>
     */
    private function unpaid(array $deadlines, CarbonImmutable $today): array
    {
        $open = array_values(array_filter($deadlines, static fn (Deadline $deadline): bool => ! $deadline->isPaid()));
        // A stable sort: the late ones move up and keep the order of their dates
        usort($open, static fn (Deadline $a, Deadline $b): int => $b->isLate($today) <=> $a->isLate($today));

        return array_map(static fn (Deadline $deadline): DeadlineData => DeadlineData::fromDeadline($deadline, $today), $open);
    }

    /**
     * A sum of amounts, rounded to the cent.
     *
     * @param  array<int, float>  $amounts  The amounts.
     */
    private function sum(array $amounts): float
    {
        return round(array_sum($amounts), 2);
    }
}
