<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\CardType;
use App\Data\CardBudgetData;
use App\Data\CardNumberData;
use App\Data\DeadlineData;
use App\Data\PaymentYearData;
use App\Data\PlannedRowData;
use App\Enums\IntervalUnit;
use App\Models\Employment;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Home;
use App\Models\Income;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Support\Deadline;
use App\Support\DefaultAccount;
use App\Support\ModelId;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The payments of a card, as its page shows them: the next due date at the top, the figures of its type, the open
 * due dates, the planned expenses (or incomes) linked to it and their payments by year. One case per card type,
 * as the analysis lists them; the cards without payments have none. The due dates come from {@see Deadlines}, the
 * payments from {@see PlannedSummaries}.
 */
final readonly class CardBudgets
{
    /** How many months back a due date still counts as late, as {@see Deadlines} does. */
    private const int MONTHS_BACK = 12;

    /** How many months ahead the due dates are read. */
    private const int MONTHS_AHEAD = 24;

    /** How many days ahead an open due date is shown as *To pay*. */
    private const int DUE_DAYS = 30;

    /** The column of a planned expense (of a planned income for an employment) that names a card of each model. */
    private const array COLUMNS = [
        Utility::class => 'utility_id',
        InsurancePolicy::class => 'insurance_policy_id',
        Loan::class => 'loan_id',
        Enrollment::class => 'enrollment_id',
        Home::class => 'home_id',
        Vehicle::class => 'vehicle_id',
        Subscription::class => 'subscription_id',
        Employment::class => 'employment_id',
    ];

    public function __construct(
        private Deadlines $deadlines,
        private PlannedSummaries $summaries,
        private Movements $movements,
        private LoanSchedule $loans,
    ) {}

    /**
     * The payments of a card, or null for the types that have none (belongings, filings, health events,
     * maintenance jobs).
     *
     * @param  Model  $card  a record of one of the 13 models
     * @param  User  $user  whoever looks at the card, for the permissions
     * @param  CarbonImmutable  $today  today in Rome
     */
    public function of(Model $card, User $user, CarbonImmutable $today): ?CardBudgetData
    {
        return match (true) {
            $card instanceof InsurancePolicy => $this->policy($card, $user, $today),
            $card instanceof Utility => $this->utility($card, $user, $today),
            $card instanceof Loan => $this->loan($card, $user, $today),
            $card instanceof Subscription => $this->subscription($card, $user, $today),
            $card instanceof Enrollment => $this->enrollment($card, $user, $today),
            $card instanceof Employment => $this->employment($card, $user, $today),
            $card instanceof Vehicle => $this->vehicle($card, $user, $today),
            $card instanceof Home => $this->home($card, $user, $today),
            $card instanceof FinancialAccount => $this->account($card, $user, $today),
            default => null,
        };
    }

    /**
     * For the rows of *An area*: the open due date of each card that is late or falls in the next 30 days, with one
     * query of planned items per type, whatever the number of cards. The oldest one wins.
     *
     * @param  CardType  $type  the type of the cards
     * @param  iterable<Model>  $cards  the cards of that type
     * @param  CarbonImmutable  $today  today in Rome
     * @return array<int, array{amount: float, late: bool}> by id of the card; the cards without one are missing
     */
    public function due(CardType $type, iterable $cards, CarbonImmutable $today): array
    {
        $column = self::COLUMNS[$type->model()] ?? null;
        $ids = [];

        foreach ($cards as $card) {
            $ids[] = ModelId::of($card);
        }

        if ($column === null || $ids === []) {
            return [];
        }

        $planned = $type->model() === Employment::class
            ? PlannedIncome::query()->whereIn($column, $ids)->get()->all()
            : PlannedExpense::query()->whereIn($column, $ids)->get()->all();
        $due = [];

        foreach ($this->deadlines->unpaidUntil($planned, $today->addDays(self::DUE_DAYS), $today) as $deadline) {
            $id = $deadline->planned->getAttribute($column);

            if (is_int($id) && ! isset($due[$id])) {
                $due[$id] = ['amount' => $deadline->occurrence->amount, 'late' => $deadline->isLate($today)];
            }
        }

        return $due;
    }

    /**
     * The words of the number of payments of a card in a year, as the payments of a utility count bills.
     *
     * @param  Model  $card  the card
     * @param  int  $count  how many payments
     * @return string for example «9 bills»
     */
    public function countText(Model $card, int $count): string
    {
        return match (true) {
            $card instanceof Utility => trans_choice('{1} :count bill|[2,*] :count bills', $count),
            $card instanceof InsurancePolicy => trans_choice('{1} :count premium|[2,*] :count premiums', $count),
            $card instanceof Loan => trans_choice('{1} :count instalment|[2,*] :count instalments', $count),
            $card instanceof Employment => trans_choice('{1} :count salary received|[2,*] :count salaries received', $count),
            default => trans_choice('{1} :count payment|[2,*] :count payments', $count),
        };
    }

    /**
     * The figures of *Payments of a card*: what was paid this year, the average payment with what is planned, and
     * the words of the number of payments of each year.
     *
     * @param  Model  $card  the card
     * @param  CardBudgetData  $budget  its payments, from `of()`
     * @param  CarbonImmutable  $today  today in Rome
     * @return array{numbers: list<CardNumberData>, counts: array<int, string>}
     */
    public function paymentFigures(Model $card, CardBudgetData $budget, CarbonImmutable $today): array
    {
        $year = array_find($budget->payments, static fn (PaymentYearData $data): bool => $data->year === $today->year);
        $planned = count($budget->planned) === 1 ? $budget->planned[0]->amount : null;
        $bill = $card instanceof Utility;
        $hint = match (true) {
            $planned === null => $bill ? __('per bill') : __('per payment'),
            $budget->kind === 'income' => __('per payment, :amount expected'),
            default => $bill ? __('per bill, :amount planned') : __('per payment, :amount planned'),
        };
        $counts = [];

        foreach ($budget->payments as $data) {
            $counts[$data->year] = $this->countText($card, $data->count);
        }

        return [
            'numbers' => [
                ...$this->paidIn($card, $budget->payments, $today->year, $this->paidLabel($card, $today->year)),
                ...($year === null || $year->count === 0 ? [] : [new CardNumberData(__('On average'), round($year->total / $year->count, 2), hint: $hint, hint_amount: $planned)]),
            ],
            'counts' => $counts,
        ];
    }

    /**
     * The label of what was paid (or received) in a year.
     *
     * @param  Model  $card  the card
     * @param  int  $year  the year
     */
    private function paidLabel(Model $card, int $year): string
    {
        return $card instanceof Employment ? __('In :year', ['year' => $year]) : __('Paid in :year', ['year' => $year]);
    }

    /**
     * A policy: the renewal at the top, the premium, the planned expenses and the premiums paid.
     *
     * @param  InsurancePolicy  $policy  the policy
     * @param  User  $user  whoever looks at the card, for the permissions
     * @param  CarbonImmutable  $today  today in Rome
     * @return CardBudgetData its payments
     */
    private function policy(InsurancePolicy $policy, User $user, CarbonImmutable $today): CardBudgetData
    {
        $planned = $this->expensesOf(fn (Builder $query): Builder => $query->where('insurance_policy_id', $policy->id));
        $first = $this->running($planned, $today);

        return $this->budget($planned, $user, $today,
            nextLabel: __('Renewal'),
            numbers: $first === null ? [] : [new CardNumberData(__('Premium'), $this->amountOf($first), hint: $this->every($first, withDay: false))],
            plannedLabel: __('Expected expenses'),
            paymentsLabel: __('Premiums paid'),
            paymentsMore: __('See all the premiums, year by year'),
        );
    }

    /**
     * A utility: what is planned and what was paid this year, the bills to pay, the planned expenses and the bills paid.
     *
     * @param  Utility  $utility  the utility
     * @param  User  $user  whoever looks at the card, for the permissions
     * @param  CarbonImmutable  $today  today in Rome
     * @return CardBudgetData its payments
     */
    private function utility(Utility $utility, User $user, CarbonImmutable $today): CardBudgetData
    {
        $planned = $this->expensesOf(fn (Builder $query): Builder => $query->where('utility_id', $utility->id));
        $first = $this->running($planned, $today);
        $numbers = fn (array $found, array $payments): array => [
            ...($first === null ? [] : [new CardNumberData(__('Planned'), $this->amountOf($first), hint: $this->perUnit($first))]),
            ...$this->paidIn($utility, $payments, $today->year, $this->paidLabel($utility, $today->year)),
        ];

        return $this->budget($planned, $user, $today,
            numbers: $numbers,
            dueLabel: __('To pay'),
            due: fn (array $found): array => $this->openUntil($found, $today->addDays(self::DUE_DAYS)),
            plannedLabel: __('Expected expenses'),
            paymentsLabel: __('Bills paid'),
            paymentsMore: __('See all the bills, year by year'),
        );
    }

    /**
     * A loan: the next instalment with its number, the instalment, the total and the cost, and the instalments paid.
     * Its planned expense is made by the loan itself, so it is not listed.
     *
     * @param  Loan  $loan  the loan
     * @param  User  $user  whoever looks at the card, for the permissions
     * @param  CarbonImmutable  $today  today in Rome
     * @return CardBudgetData its payments
     */
    private function loan(Loan $loan, User $user, CarbonImmutable $today): CardBudgetData
    {
        $planned = $this->expensesOf(fn (Builder $query): Builder => $query->where('loan_id', $loan->id));
        $first = $this->running($planned, $today);
        $total = $loan->total_to_pay === null ? null : round((float) $loan->total_to_pay, 2);
        $left = $loan->residual_amount === null ? null : round((float) $loan->residual_amount, 2);
        $installment = $loan->installment_amount ?? $first?->amount;
        $financed = $loan->total_amount === null ? null : round((float) $loan->total_amount, 2);
        $numbers = [
            $installment === null ? null : new CardNumberData(__('Instalment'), round((float) $installment, 2), hint: $first === null ? null : $this->every($first, withDay: true)),
            match (true) {
                $total === null => null,
                $financed === null => new CardNumberData(__('Total to pay'), $total),
                default => new CardNumberData(__('Total to pay'), $total, hint: __('of :amount financed'), hint_amount: $financed),
            },
            $loan->cost === null ? null : new CardNumberData(__('Cost'), round((float) $loan->cost, 2), hint: __('interest and fees')),
        ];

        return $this->budget($planned, $user, $today,
            nextLabel: __('Next instalment'),
            numbers: array_values(array_filter($numbers)),
            paymentsLabel: __('Instalments paid'),
            paymentsMore: __('See all the instalments, year by year'),
            loan: [
                'count' => $loan->installments_count,
                'paid' => $loan->installments_paid,
                'paid_amount' => $total === null || $left === null ? null : round($total - $left, 2),
                'left_amount' => $left,
            ],
        );
    }

    /**
     * A subscription: the first (or next) payment, the fee and what it comes to in a year, the planned expenses and the payments.
     *
     * @param  Subscription  $subscription  the subscription
     * @param  User  $user  whoever looks at the card, for the permissions
     * @param  CarbonImmutable  $today  today in Rome
     * @return CardBudgetData its payments
     */
    private function subscription(Subscription $subscription, User $user, CarbonImmutable $today): CardBudgetData
    {
        $planned = $this->expensesOf(fn (Builder $query): Builder => $query->where('subscription_id', $subscription->id));
        $first = $this->running($planned, $today);

        return $this->budget($planned, $user, $today,
            nextLabel: fn (array $payments): string => $payments === [] ? __('First payment') : __('Next payment'),
            numbers: fn (array $found): array => $first === null ? [] : [
                new CardNumberData(__('Subscription fee'), $this->amountOf($first), hint: $this->every($first, withDay: false)),
                $this->inYear($found, $today, __('In a year'), fn (int $count): string => trans_choice('{1} :count renewal|[2,*] :count renewals', $count)),
            ],
            plannedLabel: __('Expected expenses'),
            paymentsLabel: __('Payments'),
            paymentsMore: __('See all the payments, year by year'),
        );
    }

    /**
     * An enrollment: the next due date, the fee and the fees of the school year (September to August), the planned
     * expenses and the payments.
     *
     * @param  Enrollment  $enrollment  the enrollment
     * @param  User  $user  whoever looks at the card, for the permissions
     * @param  CarbonImmutable  $today  today in Rome
     * @return CardBudgetData its payments
     */
    private function enrollment(Enrollment $enrollment, User $user, CarbonImmutable $today): CardBudgetData
    {
        $planned = $this->expensesOf(fn (Builder $query): Builder => $query->where('enrollment_id', $enrollment->id));
        $first = $this->running($planned, $today);
        $start = $today->setDate($today->month >= 9 ? $today->year : $today->year - 1, 9, 1)->startOfDay();
        $end = $start->addYear()->subDay()->toDateString();

        return $this->budget($planned, $user, $today,
            nextLabel: __('Next due date'),
            numbers: function (array $found) use ($first, $start, $end): array {
                if ($first === null) {
                    return [];
                }

                $inYear = array_filter($found, static fn (Deadline $deadline): bool => $deadline->occurrence->date->toDateString() >= $start->toDateString() && $deadline->occurrence->date->toDateString() <= $end);

                return [
                    new CardNumberData(__('Tuition fee'), $this->amountOf($first), hint: $this->perUnit($first)),
                    new CardNumberData(__('This year'), $this->sum($inYear), hint: trans_choice('{1} :count fee|[2,*] :count fees', count($inYear))),
                ];
            },
            plannedLabel: __('Expected expenses'),
            paymentsLabel: __('Payments'),
            paymentsMore: __('See all the payments, year by year'),
        );
    }

    /**
     * An employment: the salary coming in, the salary and the salaries of this year, the planned incomes and the
     * salaries received.
     *
     * @param  Employment  $employment  the employment
     * @param  User  $user  whoever looks at the card, for the permissions
     * @param  CarbonImmutable  $today  today in Rome
     * @return CardBudgetData its payments
     */
    private function employment(Employment $employment, User $user, CarbonImmutable $today): CardBudgetData
    {
        $planned = array_values(PlannedIncome::query()->where('employment_id', $employment->id)->oldest('start_date')->orderBy('id')->get()->all());
        $first = $this->running($planned, $today);

        return $this->budget($planned, $user, $today,
            kind: 'income',
            nextLabel: __('Arriving'),
            numbers: fn (array $found, array $payments): array => [
                ...($first === null ? [] : [new CardNumberData(__('Salary'), $this->amountOf($first), hint: $this->every($first, withDay: true))]),
                ...$this->paidIn($employment, $payments, $today->year, $this->paidLabel($employment, $today->year)),
            ],
            plannedLabel: __('Expected income'),
            paymentsLabel: __('Salaries received'),
            paymentsMore: __('See all the salaries, year by year'),
        );
    }

    /**
     * A vehicle: the next due date and what it costs in the next 12 months, with the ones of its policies.
     *
     * @param  Vehicle  $vehicle  the vehicle
     * @param  User  $user  whoever looks at the card, for the permissions
     * @param  CarbonImmutable  $today  today in Rome
     * @return CardBudgetData its payments
     */
    private function vehicle(Vehicle $vehicle, User $user, CarbonImmutable $today): CardBudgetData
    {
        $planned = $this->expensesOf(fn (Builder $query): Builder => $query->where('vehicle_id', $vehicle->id)
            ->orWhereIn('insurance_policy_id', InsurancePolicy::query()->where('vehicle_id', $vehicle->id)->select('id')));

        return $this->budget($planned, $user, $today,
            nextLabel: __('Next due date'),
            numbers: fn (array $found): array => [$this->whatYouPay($found, $today)],
        );
    }

    /**
     * A home: what it costs in the next 12 months with its utilities, its utilities and the next due dates. A planned
     * expense has no belonging or maintenance job, so only the ones of the home and of its utilities count.
     *
     * @param  Home  $home  the home
     * @param  User  $user  whoever looks at the card, for the permissions
     * @param  CarbonImmutable  $today  today in Rome
     * @return CardBudgetData its payments
     */
    private function home(Home $home, User $user, CarbonImmutable $today): CardBudgetData
    {
        $utilities = Utility::query()->where('home_id', $home->id)->get();
        $planned = $this->expensesOf(fn (Builder $query): Builder => $query->where('home_id', $home->id)
            ->orWhereIn('utility_id', $utilities->modelKeys()));
        $names = $utilities->map(static fn (Utility $utility): string => mb_strtolower($utility->type->label()))->implode(', ');

        return $this->budget($planned, $user, $today,
            numbers: fn (array $found): array => [
                $this->whatYouPay($found, $today),
                new CardNumberData(__('Utilities'), null, count: $utilities->count(), hint: $names === '' ? null : $names),
            ],
            dueLabel: __('Next due dates'),
            due: fn (array $found): array => $this->openUntil($found, $today->addDays(self::DUE_DAYS)),
        );
    }

    /**
     * An account or card: the income and the spending of this month with it, and the due dates of the next 30 days
     * paid from it. A planned expense without an account is paid from the default one.
     *
     * @param  FinancialAccount  $account  the account or card
     * @param  User  $user  whoever looks at the card, for the permissions
     * @param  CarbonImmutable  $today  today in Rome
     * @return CardBudgetData its payments
     */
    private function account(FinancialAccount $account, User $user, CarbonImmutable $today): CardBudgetData
    {
        $isDefault = DefaultAccount::id() === $account->id;
        $planned = $this->expensesOf(fn (Builder $query): Builder => $isDefault
            ? $query->where('financial_account_id', $account->id)->orWhereNull('financial_account_id')
            : $query->where('financial_account_id', $account->id));
        $summary = $this->movements->month($today, null, $account->id, $user)['summary'];
        // Carbon follows the language of the app
        $month = $today->translatedFormat('F');
        $last = $today->addDays(self::DUE_DAYS)->toDateString();

        return $this->budget($planned, $user, $today,
            numbers: [
                new CardNumberData(__('Income in :month', ['month' => $month]), $summary->incomes, hint: $summary->salaries_expected === 0 ? null : trans_choice(
                    '{0} :count salaries of :expected|{1} :count salary of :expected|[2,*] :count salaries of :expected',
                    $summary->salaries_in,
                    ['expected' => $summary->salaries_expected],
                )),
                new CardNumberData(__('Spending in :month', ['month' => $month]), $summary->expenses, hint: trans_choice('{0} no payments|{1} :count payment|[2,*] :count payments', $summary->payments)),
            ],
            dueLabel: __('Going out from here'),
            due: fn (array $found): array => array_values(array_filter(
                $found,
                static fn (Deadline $deadline): bool => ! $deadline->isPaid() && $deadline->account?->id === $account->id
                    && $deadline->occurrence->date->toDateString() >= $today->toDateString() && $deadline->occurrence->date->toDateString() <= $last,
            )),
        );
    }

    /**
     * Puts the parts of a card together. The numbers and the open due dates can be closures, which receive the due
     * dates read once for the whole card (and the payments by year), so that every part reads the same.
     *
     * @param  list<PlannedExpense|PlannedIncome>  $planned  the planned items of the card
     * @param  User  $user  whoever looks at the card
     * @param  CarbonImmutable  $today  today in Rome
     * @param  string  $kind  `expense`, or `income` for an employment: what its planned items are, even when it has none
     * @param  string|callable(list<PaymentYearData>): string  $nextLabel  the name of the box at the top, or picks it
     *                                                                     from the payments; empty for no box
     * @param  list<CardNumberData>|callable(list<Deadline>, list<PaymentYearData>): list<CardNumberData>  $numbers  the figures
     * @param  string|null  $dueLabel  the title of the open due dates; null for no list
     * @param  callable(list<Deadline>): list<Deadline>|null  $due  picks the open due dates to list
     * @param  string|null  $plannedLabel  the title of the planned items; null for no section
     * @param  string|null  $paymentsLabel  the title of the payments; null for no section
     * @param  string  $paymentsMore  the link to all the payments
     * @param  array{count: ?int, paid: ?int, paid_amount: ?float, left_amount: ?float}|null  $loan  the figures of a loan
     * @return CardBudgetData the payments of the card
     */
    private function budget(
        array $planned,
        User $user,
        CarbonImmutable $today,
        string $kind = 'expense',
        string|callable $nextLabel = '',
        array|callable $numbers = [],
        ?string $dueLabel = null,
        ?callable $due = null,
        ?string $plannedLabel = null,
        ?string $paymentsLabel = null,
        string $paymentsMore = '',
        ?array $loan = null,
    ): CardBudgetData {
        $found = $this->deadlines->between($planned, $today->subMonthsNoOverflow(self::MONTHS_BACK), $today->addMonthsNoOverflow(self::MONTHS_AHEAD));
        $open = array_values(array_filter($found, static fn (Deadline $deadline): bool => ! $deadline->isPaid()));
        $payments = $paymentsLabel === null ? [] : $this->summaries->paymentsByYear($planned, $user);
        $nextLabel = is_callable($nextLabel) ? $nextLabel($payments) : $nextLabel;
        $next = $nextLabel === '' ? null : ($open[0] ?? null);
        $income = $kind === 'income';
        $data = static fn (Deadline $deadline): DeadlineData => DeadlineData::fromDeadline($deadline, $today);

        return new CardBudgetData(
            kind: $kind,
            next: $next === null ? null : $data($next),
            next_label: $nextLabel,
            installment_number: $loan === null || ! $next instanceof Deadline || $next->planned->start_date === null ? null
                : $this->loans->installmentsBetween($next->planned->start_date, $next->occurrence->originalDate),
            installments_count: $loan['count'] ?? null,
            installments_paid: $loan['paid'] ?? null,
            paid_amount: $loan['paid_amount'] ?? null,
            left_amount: $loan['left_amount'] ?? null,
            numbers: is_callable($numbers) ? $numbers($found, $payments) : $numbers,
            due_label: $dueLabel,
            due: $due === null ? [] : array_map($data, $due($found)),
            planned_label: $plannedLabel,
            planned: $plannedLabel === null ? [] : $this->rows($planned, $found, $today),
            can_add_planned: $plannedLabel !== null && $user->can('create', $income ? PlannedIncome::class : PlannedExpense::class),
            payments_label: $paymentsLabel,
            payments_more: $paymentsMore,
            payments: $payments,
            pay: $paymentsLabel === null ? [] : array_map($data, $this->firstOpenOfEach($planned, $open)),
            can_pay: $paymentsLabel !== null && $planned !== [] && $user->can('create', $income ? Income::class : Expense::class),
        );
    }

    /**
     * The planned item the figures of a card read: the one still running (not ended) that started last, else the one
     * that started last.
     *
     * @param  list<PlannedExpense|PlannedIncome>  $planned  the planned items, oldest first
     * @param  CarbonImmutable  $today  today in Rome
     * @return PlannedExpense|PlannedIncome|null null when the card has no planned item
     */
    private function running(array $planned, CarbonImmutable $today): PlannedExpense|PlannedIncome|null
    {
        $day = $today->toDateString();
        $running = array_filter($planned, static fn (PlannedExpense|PlannedIncome $item): bool => $item->end_date === null || $item->end_date->toDateString() >= $day);

        return array_last($running) ?? array_last($planned);
    }

    /**
     * The planned expenses that a condition picks, oldest first, with what their rows read.
     *
     * @param  Closure(Builder<PlannedExpense>): Builder<PlannedExpense>  $where  the condition, kept in its own brackets
     * @return list<PlannedExpense>
     */
    private function expensesOf(Closure $where): array
    {
        return array_values(PlannedExpense::query()->with('category')->where($where)->oldest('start_date')->orderBy('id')->get()->all());
    }

    /**
     * The rows of the planned items, each with its next due date that is not late and not paid.
     *
     * @param  list<PlannedExpense|PlannedIncome>  $planned  the planned items
     * @param  list<Deadline>  $found  their due dates
     * @param  CarbonImmutable  $today  today in Rome
     * @return list<PlannedRowData>
     */
    private function rows(array $planned, array $found, CarbonImmutable $today): array
    {
        $default = DefaultAccount::model();
        $day = $today->toDateString();

        return array_map(static function (PlannedExpense|PlannedIncome $item) use ($found, $default, $today, $day): PlannedRowData {
            $next = array_find($found, static fn (Deadline $deadline): bool => $deadline->planned->is($item) && ! $deadline->isPaid() && $deadline->occurrence->date->toDateString() >= $day);

            return PlannedRowData::fromPlanned($item, $next?->occurrence->date, $default, $today);
        }, $planned);
    }

    /**
     * The oldest open due date of each planned item, the one *Record payment* opens.
     *
     * @param  list<PlannedExpense|PlannedIncome>  $planned  the planned items
     * @param  list<Deadline>  $open  their open due dates, by date
     * @return list<Deadline>
     */
    private function firstOpenOfEach(array $planned, array $open): array
    {
        $first = [];

        foreach ($planned as $item) {
            $deadline = array_find($open, static fn (Deadline $deadline): bool => $deadline->planned->is($item));

            if ($deadline instanceof Deadline) {
                $first[] = $deadline;
            }
        }

        return $first;
    }

    /**
     * The open due dates up to a day, the late ones too.
     *
     * @param  list<Deadline>  $found  the due dates
     * @param  CarbonImmutable  $until  the last day
     * @return list<Deadline>
     */
    private function openUntil(array $found, CarbonImmutable $until): array
    {
        $last = $until->toDateString();

        return array_values(array_filter($found, static fn (Deadline $deadline): bool => ! $deadline->isPaid() && $deadline->occurrence->date->toDateString() <= $last));
    }

    /**
     * *What you pay*: the due dates of the next 12 months, exceptions applied.
     *
     * @param  list<Deadline>  $found  the due dates
     * @param  CarbonImmutable  $today  today in Rome
     */
    private function whatYouPay(array $found, CarbonImmutable $today): CardNumberData
    {
        return $this->inYear($found, $today, __('What you pay'), static fn (int $count): string => __('in the next 12 months'));
    }

    /**
     * A figure of the due dates of the next 12 months, from today to the same day a year later less one day.
     *
     * @param  list<Deadline>  $found  the due dates
     * @param  CarbonImmutable  $today  today in Rome
     * @param  string  $label  the label of the figure
     * @param  callable(int): string  $hint  the line below, from the number of due dates
     */
    private function inYear(array $found, CarbonImmutable $today, string $label, callable $hint): CardNumberData
    {
        $first = $today->toDateString();
        $last = $today->addYear()->subDay()->toDateString();
        $inYear = array_filter($found, static fn (Deadline $deadline): bool => $deadline->occurrence->date->toDateString() >= $first && $deadline->occurrence->date->toDateString() <= $last);

        return new CardNumberData($label, $this->sum($inYear), hint: $hint(count($inYear)));
    }

    /**
     * The figure of what was paid in a year, with the number of payments; none when nothing was paid.
     *
     * @param  Model  $card  the card, for the words of the number
     * @param  array<PaymentYearData>  $payments  the payments by year
     * @param  int  $year  the year
     * @param  string  $label  the label of the figure
     * @return list<CardNumberData>
     */
    private function paidIn(Model $card, array $payments, int $year, string $label): array
    {
        $paid = array_find($payments, static fn (PaymentYearData $data): bool => $data->year === $year);

        return [new CardNumberData($label, $paid->total ?? 0.0, hint: $this->countText($card, $paid->count ?? 0))];
    }

    /**
     * The sum of the amounts of some due dates.
     *
     * @param  array<Deadline>  $deadlines  the due dates
     */
    private function sum(array $deadlines): float
    {
        return round(array_sum(array_map(static fn (Deadline $deadline): float => $deadline->occurrence->amount, $deadlines)), 2);
    }

    /**
     * The amount of every due date of a planned item.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     * @return float the amount, rounded to the cent
     */
    private function amountOf(PlannedExpense|PlannedIncome $planned): float
    {
        return round((float) $planned->amount, 2);
    }

    /**
     * How often a planned item repeats, as «every year» or, with the day, «every month, on day 10».
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     * @param  bool  $withDay  whether to say the day
     */
    private function every(PlannedExpense|PlannedIncome $planned, bool $withDay): string
    {
        if (! $planned->is_recurring) {
            return $this->once($planned);
        }

        $interval = max(1, (int) $planned->interval);
        $params = ['day' => $planned->start_date->day ?? 1, 'date' => $planned->start_date?->format('d/m') ?? ''];

        return match ($planned->interval_unit) {
            IntervalUnit::Week => trans_choice('{1} every week|[2,*] every :count weeks', $interval),
            IntervalUnit::Month => $withDay
                ? trans_choice('{1} every month, on day :day|[2,*] every :count months, on day :day', $interval, $params)
                : trans_choice('{1} every month|[2,*] every :count months', $interval),
            IntervalUnit::Year => $withDay
                ? trans_choice('{1} every year, on :date|[2,*] every :count years, on :date', $interval, $params)
                : trans_choice('{1} every year|[2,*] every :count years', $interval),
        };
    }

    /**
     * How often a planned item repeats, as an amount reads it: «per month», «per month, estimated».
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     */
    private function perUnit(PlannedExpense|PlannedIncome $planned): string
    {
        $interval = max(1, (int) $planned->interval);
        $period = ! $planned->is_recurring ? $this->once($planned) : match ($planned->interval_unit) {
            IntervalUnit::Week => trans_choice('{1} per week|[2,*] every :count weeks', $interval),
            IntervalUnit::Month => trans_choice('{1} per month|[2,*] every :count months', $interval),
            IntervalUnit::Year => trans_choice('{1} per year|[2,*] every :count years', $interval),
        };

        return $planned->is_estimated ? __(':period, estimated', ['period' => $period]) : $period;
    }

    /**
     * The day of a planned item that happens once.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     * @return string for example «once, on 21/11/2026»
     */
    private function once(PlannedExpense|PlannedIncome $planned): string
    {
        return __('once, on :date', ['date' => $planned->start_date?->format('d/m/Y') ?? '']);
    }
}
