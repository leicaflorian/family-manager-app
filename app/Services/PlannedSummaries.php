<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CardData;
use App\Data\CardValueData;
use App\Data\DeadlineData;
use App\Data\ExceptionData;
use App\Data\MovementData;
use App\Data\PaymentYearData;
use App\Data\PlannedDetailData;
use App\Data\PlannedRowData;
use App\Enums\ExceptionType;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Models\PlannedIncome;
use App\Models\PlannedIncomeException;
use App\Models\User;
use App\Support\Deadline;
use App\Support\DefaultAccount;
use App\Support\ModelId;
use App\Support\PlannedKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * What *Planned expenses* and *A planned expense* show: the rows with their next due date, the monthly averages,
 * the year of a planned item, its exceptions, its data and its payments. The due dates come from {@see Deadlines}.
 */
final readonly class PlannedSummaries
{
    /** How many due dates *A planned expense* lists. */
    private const int NEXT = 4;

    /** How many months back a due date still counts as late, as {@see Deadlines} does. */
    private const int MONTHS_BACK = 12;

    /** How many years ahead the next due date is looked for. */
    private const int YEARS_AHEAD = 2;

    public function __construct(private Deadlines $deadlines, private CardSummaries $cards) {}

    /**
     * *Planned expenses*: the active expenses and incomes by next due date, the ended ones apart, and what they
     * come to in a month on average (the sum of the due dates of the next 12 months, exceptions applied, over 12).
     *
     * @param  CarbonImmutable  $today  today in Rome
     * @return array{expenses: list<PlannedRowData>, incomes: list<PlannedRowData>, ended: list<PlannedRowData>, monthly_out: float, monthly_in: float}
     */
    public function index(CarbonImmutable $today): array
    {
        $planned = [
            ...PlannedExpense::query()->with(['category', 'person', 'financialAccount'])->get()->all(),
            ...PlannedIncome::query()->with(['person', 'financialAccount'])->get()->all(),
        ];
        $found = $this->deadlines->between($planned, $today, $today->addYears(self::YEARS_AHEAD));
        $default = DefaultAccount::model();
        $lastDay = $today->addYear()->subDay()->toDateString();
        $rows = ['expense' => [], 'income' => [], 'ended' => []];
        $totals = ['expense' => 0.0, 'income' => 0.0];

        foreach ($planned as $item) {
            $mine = array_filter($found, static fn (Deadline $deadline): bool => $deadline->planned->is($item));
            $next = array_find($mine, static fn (Deadline $deadline): bool => ! $deadline->isPaid());
            $row = PlannedRowData::fromPlanned($item, $next?->occurrence->date, $default, $today);

            if ($row->ended) {
                $rows['ended'][] = $row;

                continue;
            }

            $rows[$row->kind][] = $row;
            $totals[$row->kind] += array_sum(array_map(
                static fn (Deadline $deadline): float => $deadline->occurrence->date->toDateString() <= $lastDay ? $deadline->occurrence->amount : 0.0,
                $mine,
            ));
        }

        return [
            'expenses' => $this->byNextDate($rows['expense']),
            'incomes' => $this->byNextDate($rows['income']),
            'ended' => $this->byEndDate($rows['ended']),
            'monthly_out' => round($totals['expense'] / 12, 2),
            'monthly_in' => round($totals['income'] / 12, 2),
        ];
    }

    /**
     * The row of one planned item, with its next due date.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     * @param  CarbonImmutable  $today  today in Rome
     */
    public function row(PlannedExpense|PlannedIncome $planned, CarbonImmutable $today): PlannedRowData
    {
        $next = array_find(
            $this->deadlines->between([$planned], $today, $today->addYears(self::YEARS_AHEAD)),
            static fn (Deadline $deadline): bool => ! $deadline->isPaid(),
        );

        return PlannedRowData::fromPlanned($planned, $next?->occurrence->date, DefaultAccount::model(), $today);
    }

    /**
     * *A planned expense*: the head, the year, the next due dates, the exceptions, the data and the payments.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     * @param  User  $user  whoever looks at it, for the permissions
     * @param  CarbonImmutable  $today  today in Rome
     */
    public function detail(PlannedExpense|PlannedIncome $planned, User $user, CarbonImmutable $today): PlannedDetailData
    {
        $planned->loadMissing($planned instanceof PlannedExpense ? ['category', 'organization', 'person'] : ['organization', 'person']);
        $found = $this->deadlines->between([$planned], $today->subMonthsNoOverflow(self::MONTHS_BACK), $today->addYears(self::YEARS_AHEAD));
        $late = array_values(array_filter($found, static fn (Deadline $deadline): bool => $deadline->isLate($today)));
        $coming = array_values(array_filter($found, static fn (Deadline $deadline): bool => $deadline->occurrence->date->toDateString() >= $today->toDateString()));
        $lastDay = $today->addYear()->subDay();
        $next = array_find($coming, static fn (Deadline $deadline): bool => ! $deadline->isPaid());
        $default = DefaultAccount::model();

        return new PlannedDetailData(
            row: PlannedRowData::fromPlanned($planned, $next?->occurrence->date, $default, $today),
            year_total: round(array_sum(array_map(
                static fn (Deadline $deadline): float => $deadline->occurrence->date <= $lastDay ? $deadline->occurrence->amount : 0.0,
                $coming,
            )), 2),
            skipped_months: $this->skippedMonths($planned, $today, $lastDay),
            next: array_map(
                static fn (Deadline $deadline): DeadlineData => DeadlineData::fromDeadline($deadline, $today),
                array_slice([...$late, ...$coming], 0, self::NEXT),
            ),
            exceptions: $this->exceptions($planned, $today),
            values: $this->values($planned, $default),
            payments: $this->paymentsByYear([$planned], $user),
            can_edit: $user->can('update', $planned),
            can_pay: $user->can('create', $planned instanceof PlannedExpense ? Expense::class : Income::class),
        );
    }

    /**
     * The payments of some planned items by year, the latest year first, each year with its total and its payments
     * latest first. With a card, the expenses linked to the card count too, each one once even when it is also a
     * payment of one of the planned items.
     *
     * @param  iterable<PlannedExpense|PlannedIncome>  $planned  the planned items
     * @param  User  $user  whoever looks at them, for the permissions of each payment
     * @param  Model|null  $card  the card whose linked expenses count too, null for none
     * @return list<PaymentYearData>
     */
    public function paymentsByYear(iterable $planned, User $user, ?Model $card = null): array
    {
        $expenses = [];
        $incomes = [];

        foreach ($planned as $item) {
            if ($item instanceof PlannedExpense) {
                $expenses[] = $item->id;
            } else {
                $incomes[] = $item->id;
            }
        }

        $with = ['financialAccount', 'organization', 'documents.person', 'documents.organization', 'documents.file'];
        $movements = Expense::query()->with([...$with, 'category'])
            ->where(static fn (Builder $query): Builder => $card instanceof Model
                ? $query->whereIn('planned_expense_id', $expenses)->orWhereMorphedTo('card', $card)
                : $query->whereIn('planned_expense_id', $expenses))
            ->get()
            ->concat(Income::query()->with($with)->whereIn('planned_income_id', $incomes)->get())
            ->sortByDesc(static fn (Expense|Income $movement): string => $movement->date?->toDateString().sprintf('%010d', $movement->id));
        $paths = $movements->contains(static fn (Expense|Income $movement): bool => $movement->documents->isNotEmpty()) ? resolve(CategoryTree::class)->paths() : [];
        $years = [];

        foreach ($movements as $movement) {
            $years[$movement->date instanceof CarbonImmutable ? $movement->date->year : 0][] = $movement;
        }

        return array_map(
            static fn (int $year, array $inYear): PaymentYearData => new PaymentYearData(
                year: $year,
                total: round(array_sum(array_map(static fn (Expense|Income $movement): float => (float) $movement->amount, $inYear)), 2),
                count: count($inYear),
                payments: array_map(static fn (Expense|Income $movement): MovementData => MovementData::fromMovement($movement, $user, $paths), $inYear),
            ),
            array_keys($years),
            $years,
        );
    }

    /**
     * The exceptions, the latest due date first, each with the due date it changes (skipped ones included),
     * all read with one lookup.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item, with its exceptions loaded
     * @param  CarbonImmutable  $today  today in Rome
     * @return list<ExceptionData>
     */
    private function exceptions(PlannedExpense|PlannedIncome $planned, CarbonImmutable $today): array
    {
        $dates = array_values($planned->exceptions
            ->map(static fn (PlannedExpenseException|PlannedIncomeException $exception): ?CarbonImmutable => $exception->occurrence_date)
            ->filter()
            ->all());
        $deadlines = $this->deadlines->findMany($planned, $dates, withSkipped: true);

        return array_values($planned->exceptions
            ->sortByDesc(static fn (PlannedExpenseException|PlannedIncomeException $exception): string => $exception->occurrence_date?->toDateString().sprintf('%010d', $exception->id))
            ->map(static function (PlannedExpenseException|PlannedIncomeException $exception) use ($deadlines, $today): ExceptionData {
                $deadline = $deadlines[$exception->occurrence_date?->toDateString() ?? ''] ?? null;

                return ExceptionData::fromException($exception, $deadline === null ? null : DeadlineData::fromDeadline($deadline, $today));
            })
            ->all());
    }

    /**
     * The months skipped in the next 12 months, in the order they come.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item, with its exceptions loaded
     * @param  CarbonImmutable  $today  the first day
     * @param  CarbonImmutable  $lastDay  the last day
     * @return list<int> the numbers of the months, 1 to 12
     */
    private function skippedMonths(PlannedExpense|PlannedIncome $planned, CarbonImmutable $today, CarbonImmutable $lastDay): array
    {
        $first = $today->toDateString();
        $last = $lastDay->toDateString();
        $skipped = $planned->exceptions
            ->filter(static fn (PlannedExpenseException|PlannedIncomeException $exception): bool => $exception->type === ExceptionType::Skip
                && $exception->occurrence_date !== null && $exception->occurrence_date->toDateString() >= $first && $exception->occurrence_date->toDateString() <= $last)
            ->sortBy(static fn (PlannedExpenseException|PlannedIncomeException $exception): string => $exception->occurrence_date?->toDateString() ?? '')
            ->map(static fn (PlannedExpenseException|PlannedIncomeException $exception): int => $exception->occurrence_date instanceof CarbonImmutable ? $exception->occurrence_date->month : 0);

        return array_values($skipped->unique()->all());
    }

    /**
     * The lines of the box *Data* that the row does not say: category, organization, account, holder, type and card.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item, with its category, organization and person loaded
     * @param  FinancialAccount|null  $default  the default account of the family
     * @return list<CardValueData>
     */
    private function values(PlannedExpense|PlannedIncome $planned, ?FinancialAccount $default): array
    {
        $category = $planned instanceof PlannedExpense ? $planned->category : null;
        $card = PlannedKind::card($planned);
        $row = $card instanceof Model ? $this->cards->row($card) : null;
        $account = $planned->financialAccount ?? $default;
        $person = $planned->person;
        $values = [
            $planned instanceof PlannedIncome ? new CardValueData(__('Type'), $planned->type->label()) : null,
            $category === null ? null : new CardValueData(__('Category'), ExpenseCategoryPaths::all()[$category->id] ?? $category->name),
            $planned->organization === null ? null : new CardValueData(__('Organization'), $planned->organization->name, route('organizations.show', $planned->organization)),
            $account === null ? null : new CardValueData(__('Bank account'), $account->name),
            $person === null
                ? new CardValueData(__('Holder'), __('Whole family'))
                : new CardValueData(__('Holder'), $person->first_name, route('people.show', $person)),
            $card instanceof Model && $row instanceof CardData ? new CardValueData(__('Refers to'), $row->label, route('cards.show', ['type' => $row->type, 'id' => ModelId::of($card)])) : null,
        ];

        return array_values(array_filter($values));
    }

    /**
     * The rows by next due date, the ones without any last, then by title.
     *
     * @param  list<PlannedRowData>  $rows  the rows
     * @return list<PlannedRowData>
     */
    private function byNextDate(array $rows): array
    {
        usort($rows, static fn (PlannedRowData $a, PlannedRowData $b): int => [$a->next_date === null, $a->next_date, mb_strtolower($a->title)] <=> [$b->next_date === null, $b->next_date, mb_strtolower($b->title)]);

        return $rows;
    }

    /**
     * The ended rows, the one that ended last first.
     *
     * @param  list<PlannedRowData>  $rows  the rows
     * @return list<PlannedRowData>
     */
    private function byEndDate(array $rows): array
    {
        usort($rows, static fn (PlannedRowData $a, PlannedRowData $b): int => [$b->end_date, mb_strtolower($a->title)] <=> [$a->end_date, mb_strtolower($b->title)]);

        return $rows;
    }
}
