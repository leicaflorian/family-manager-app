<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\MonthSummaryData;
use App\Data\MovementBankData;
use App\Data\MovementData;
use App\Data\MovementMonthData;
use App\Data\MovementSearchData;
use App\Data\OptionData;
use App\Enums\Feature;
use App\Enums\IncomeType;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Support\Deadline;
use App\Support\MovementFilters;
use App\Support\Occurrence;
use App\Support\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The expenses and incomes of a month, with the figures of *Movements*, and the due dates that a movement can settle.
 */
final readonly class Movements
{
    /** The relations that the rows of the list read: the documents are only sent with the page of a movement. */
    private const array LIST_RELATIONS = ['financialAccount', 'organization'];

    /** The relations that the page of a movement reads. */
    private const array RELATIONS = [...self::LIST_RELATIONS, 'documents.file', 'documents.person', 'documents.organization'];

    /** How many movements a search shows at most. */
    public const int SEARCH_LIMIT = 200;

    /** How many due dates the menu of a form offers at most. */
    private const int MENU = 36;

    public function __construct(private Deadlines $deadlines, private Occurrences $occurrences) {}

    /**
     * The line «Registrata dalla banca» of a movement: the bank transaction linked to it, while the link to the bank
     * is on. The undo is offered to who can delete the movement.
     *
     * @param  Expense|Income  $movement  the expense or income
     * @param  User  $user  who looks at it
     */
    public function bank(Expense|Income $movement, User $user): ?MovementBankData
    {
        if (! resolve(FamilyFeatures::class)->on(Feature::BankFeed)) {
            return null;
        }

        $transaction = BankTransaction::query()
            ->with('financialAccount')
            ->where($movement instanceof Expense ? 'expense_id' : 'income_id', $movement->id)
            ->first();

        return $transaction instanceof BankTransaction ? MovementBankData::fromTransaction($transaction, $user->can('delete', $movement)) : null;
    }

    /**
     * The expense or income of an address, inside the current family.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  string  $id  the id of the movement
     *
     * @throws NotFoundHttpException if the kind is not `expense` or `income`
     * @throws ModelNotFoundException if the family has no such movement (Laravel answers 404)
     */
    public static function find(string $kind, string $id): Expense|Income
    {
        return match ($kind) {
            'expense' => Expense::query()->with([...self::RELATIONS, 'category'])->findOrFail($id),
            'income' => Income::query()->with(self::RELATIONS)->findOrFail($id),
            default => throw new NotFoundHttpException,
        };
    }

    /**
     * The model class of a kind.
     *
     * @param  string  $kind  `expense` or `income`
     * @return class-string<Expense|Income>
     *
     * @throws NotFoundHttpException if the kind is not `expense` or `income`
     */
    public static function modelClass(string $kind): string
    {
        return match ($kind) {
            'expense' => Expense::class,
            'income' => Income::class,
            default => throw new NotFoundHttpException,
        };
    }

    /**
     * The month of *Movements*: its figures and its expenses and incomes, the most recent first. The account narrows
     * both; the kind narrows the list only, because the figures are the month's.
     *
     * @param  CarbonImmutable  $month  Any day of the month.
     * @param  string|null  $kind  `expense` or `income` to show only those, null for both.
     * @param  int|null  $accountId  The account to show, null for all.
     * @param  User  $user  Who looks at it, for the permissions of each movement.
     * @return array{summary: MonthSummaryData, movements: list<MovementData>}
     */
    public function month(CarbonImmutable $month, ?string $kind, ?int $accountId, User $user): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();
        $expenses = $this->inMonth(Expense::query()->with([...self::LIST_RELATIONS, 'category']), $from, $to, $accountId);
        $incomes = $this->inMonth(Income::query()->with(self::LIST_RELATIONS), $from, $to, $accountId);

        $salariesIn = count(array_filter($incomes, static fn (Expense|Income $income): bool => $income instanceof Income && $income->type === IncomeType::Salary));
        $total = static fn (array $movements): float => round(array_sum(array_map(static fn (Expense|Income $movement): float => (float) $movement->amount, $movements)), 2);
        $summary = new MonthSummaryData(
            month: $from->format('Y-m'),
            incomes: $total($incomes),
            salaries_in: $salariesIn,
            salaries_expected: max($salariesIn, $this->salaryDeadlines($from, $to, $accountId)),
            expenses: $total($expenses),
            payments: count($expenses),
            balance: round($total($incomes) - $total($expenses), 2),
        );

        $shown = match ($kind) {
            'expense' => $expenses,
            'income' => $incomes,
            default => [...$expenses, ...$incomes],
        };
        $shown = $this->newestFirst($shown);

        $paths = resolve(CategoryTree::class)->paths();

        return [
            'summary' => $summary,
            'movements' => array_map(static fn (Expense|Income $movement): MovementData => MovementData::fromMovement($movement, $user, $paths, withDocuments: false), $shown),
        ];
    }

    /**
     * The search of *Movements*: the expenses and incomes that match the filters in every month, grouped by month, the
     * most recent month first and the most recent movement first. At most {@see self::SEARCH_LIMIT} movements are
     * returned; `count` and `total` are those of all the matching ones. With both kinds the incomes count positive and
     * the expenses negative in every total; with one kind the sums are plain. The total of a month is that of the
     * movements listed in it.
     *
     * @param  MovementFilters  $filters  What to look for.
     * @param  User  $user  Who looks at it, for the permissions of each movement.
     */
    public function search(MovementFilters $filters, User $user): MovementSearchData
    {
        $signed = $filters->kind === null;
        $sign = static fn (Expense|Income $movement): int => $signed && $movement instanceof Expense ? -1 : 1;

        $count = 0;
        $total = 0.0;
        $found = [];

        if ($filters->kind !== 'income') {
            $query = $this->matching(Expense::query()->with([...self::LIST_RELATIONS, 'category']), $filters);
            $count += $query->count();
            $total += ($signed ? -1 : 1) * (float) $query->sum('amount');
            array_push($found, ...$query->orderByDesc('date')->orderByDesc('id')->limit(self::SEARCH_LIMIT)->get()->all());
        }

        if ($filters->kind !== 'expense') {
            $query = $this->matching(Income::query()->with(self::LIST_RELATIONS), $filters);
            $count += $query->count();
            $total += (float) $query->sum('amount');
            array_push($found, ...$query->orderByDesc('date')->orderByDesc('id')->limit(self::SEARCH_LIMIT)->get()->all());
        }

        $found = $this->newestFirst($found);
        $paths = resolve(CategoryTree::class)->paths();

        $groups = [];

        foreach (array_slice($found, 0, self::SEARCH_LIMIT) as $movement) {
            $groups[$movement->date?->format('Y-m') ?? ''][] = $movement;
        }

        $months = [];

        foreach ($groups as $month => $movements) {
            $months[] = new MovementMonthData(
                month: (string) $month,
                total: round(array_sum(array_map(static fn (Expense|Income $movement): float => $sign($movement) * (float) $movement->amount, $movements)), 2),
                movements: array_map(static fn (Expense|Income $movement): MovementData => MovementData::fromMovement($movement, $user, $paths, withDocuments: false), $movements),
            );
        }

        return new MovementSearchData(months: $months, count: $count, total: round($total, 2));
    }

    /**
     * Sorts movements the most recent first: by day, then the incomes before the expenses of the same day, then the
     * last added first.
     *
     * @param  array<int, Expense|Income>  $movements  The movements, in any order.
     * @return list<Expense|Income>
     */
    private function newestFirst(array $movements): array
    {
        usort($movements, static fn (Expense|Income $a, Expense|Income $b): int => [$b->date, $b instanceof Income, $b->id] <=> [$a->date, $a instanceof Income, $a->id]);

        return $movements;
    }

    /**
     * The expenses or incomes of the family that match the filters of a search (the kind is chosen by the caller).
     *
     * @template TModel of Expense|Income
     *
     * @param  Builder<TModel>  $query  The query of the model.
     * @param  MovementFilters  $filters  What to look for.
     * @return Builder<TModel>
     */
    private function matching(Builder $query, MovementFilters $filters): Builder
    {
        return $query
            // backslash is the escape of LIKE, so a % or _ typed by the person is a plain character
            ->when($filters->search !== null, static fn (Builder $query) => $query->where('title', 'like', '%'.addcslashes((string) $filters->search, '\\%_').'%'))
            ->when($filters->account !== null, static fn (Builder $query) => $query->where('financial_account_id', $filters->account))
            ->when($filters->min !== null, static fn (Builder $query) => $query->where('amount', '>=', $filters->min))
            ->when($filters->max !== null, static fn (Builder $query) => $query->where('amount', '<=', $filters->max))
            ->when($filters->from !== null, static fn (Builder $query) => $query->whereDate('date', '>=', $filters->from))
            ->when($filters->to !== null, static fn (Builder $query) => $query->whereDate('date', '<=', $filters->to));
    }

    /**
     * The due dates of a planned item that a movement of a day can settle: the ones nothing pays, and the one the
     * movement being changed pays. The one that the day registers comes first, the others follow by date.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     * @param  CarbonImmutable  $date  The day of the movement.
     * @param  Expense|Income|null  $except  The movement being changed, whose own due date stays on offer.
     * @return list<OptionData> The original day of each due date as value, and its real day as text.
     */
    public function openOccurrences(PlannedExpense|PlannedIncome $planned, CarbonImmutable $date, Expense|Income|null $except = null): array
    {
        $open = array_values(array_filter(
            $this->deadlines->between([$planned], $date->subYear(), $date->addYear()),
            static fn (Deadline $deadline): bool => ! $deadline->isPaid() || $deadline->payment?->is($except) === true,
        ));
        $chosen = $this->occurrences->at(Schedule::of($planned), $date)?->originalDate->toDateString();
        // false sorts before true: the chosen one first, the others keep their order by date
        usort($open, static fn (Deadline $a, Deadline $b): int => ($a->occurrence->originalDate->toDateString() !== $chosen) <=> ($b->occurrence->originalDate->toDateString() !== $chosen));

        return array_map(
            static fn (Deadline $deadline): OptionData => new OptionData($deadline->occurrence->originalDate->toDateString(), $deadline->occurrence->date->format('d/m/Y')),
            array_slice($open, 0, self::MENU),
        );
    }

    /**
     * Whether the due date that a day registers (the closest one, see `Occurrences::at()`) can be settled: it is open,
     * or paid by the movement being changed, or the day registers none.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     * @param  CarbonImmutable  $date  The day of the movement.
     * @param  Expense|Income|null  $except  The movement being changed.
     */
    public function registeredIsOpen(PlannedExpense|PlannedIncome $planned, CarbonImmutable $date, Expense|Income|null $except = null): bool
    {
        $occurrence = $this->occurrences->at(Schedule::of($planned), $date);

        return ! $occurrence instanceof Occurrence || $this->isOpen($planned, $occurrence->originalDate, $except);
    }

    /**
     * Whether a day is a due date of the planned item that nothing pays, or that the movement being changed pays.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  The planned item.
     * @param  CarbonImmutable  $originalDay  The day the rules give.
     * @param  Expense|Income|null  $except  The movement being changed.
     */
    public function isOpen(PlannedExpense|PlannedIncome $planned, CarbonImmutable $originalDay, Expense|Income|null $except = null): bool
    {
        try {
            $deadline = $this->deadlines->find($planned, $originalDay);
        } catch (NotFoundHttpException) {
            return false;
        }

        return ! $deadline->isPaid() || $deadline->payment?->is($except) === true;
    }

    /**
     * The expenses or incomes of a month, narrowed to an account.
     *
     * @param  Builder<Expense>|Builder<Income>  $query  The query of the model, with its relations.
     * @param  CarbonImmutable  $from  The first day.
     * @param  CarbonImmutable  $to  The last day.
     * @param  int|null  $accountId  The account, null for all.
     * @return list<Expense|Income>
     */
    private function inMonth(Builder $query, CarbonImmutable $from, CarbonImmutable $to, ?int $accountId): array
    {
        return array_values($query
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($accountId !== null, static fn ($query) => $query->where('financial_account_id', $accountId))
            ->get()
            ->all());
    }

    /**
     * How many salaries the planned incomes give in a period, paid or not. With an account, only those that come
     * to it: the account of the planned income, else the default one, as {@see Deadline::$account} says.
     *
     * @param  CarbonImmutable  $from  The first day.
     * @param  CarbonImmutable  $to  The last day.
     * @param  int|null  $accountId  The account shown, null for all.
     */
    private function salaryDeadlines(CarbonImmutable $from, CarbonImmutable $to, ?int $accountId): int
    {
        $found = $this->deadlines->between(PlannedIncome::query()->where('type', IncomeType::Salary)->get(), $from, $to);

        return count($accountId === null ? $found : array_filter($found, static fn (Deadline $deadline): bool => $deadline->account?->id === $accountId));
    }
}
