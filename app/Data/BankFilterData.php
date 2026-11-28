<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\BankFilterAction;
use App\Enums\FinancialAccountStatus;
use App\Models\BankFilter;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankFilterDraft;
use App\Support\PresetLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A filter of the bank, saved or proposed: what it takes, what it does, and what the tab *Filters* and its form show
 * about it (the account, the planned item, how many movements it settled, what is missing for it to work).
 */
#[TypeScript]
final class BankFilterData extends Data
{
    /**
     * @param  int|null  $id  the filter, null for one proposed and not saved
     * @param  string  $name  its name
     * @param  list<string>  $contains  the words or phrases the text must all have
     * @param  list<string>  $excludes  the words or phrases the text must not have
     * @param  string  $direction  `expense` or `income`
     * @param  int|null  $financial_account_id  the account it reads, null for all of them
     * @param  float|null  $amount_min  the smallest amount, without sign
     * @param  float|null  $amount_max  the largest amount, without sign
     * @param  string|null  $pattern  the expression of an advanced filter, which changes only as text
     * @param  string  $fee_mode  how the commission is found (a `BankFilterFee` value)
     * @param  list<string>  $fee_contains  the words of the commission
     * @param  list<string>  $fee_excludes  the words the commission must not have
     * @param  string|null  $action  what it does (a `BankFilterAction` value), null while a proposal has none
     * @param  int|null  $planned_expense_id  the planned expense a filter that pays settles
     * @param  int|null  $planned_income_id  the planned income a filter that pays settles
     * @param  string|null  $title  the title of the movement a filter that creates writes
     * @param  bool  $title_with_month  whether that title ends with the month
     * @param  int|null  $category_id  the category of the expense
     * @param  int|null  $organization_id  the organization of the movement
     * @param  int|null  $person_id  the person of the movement
     * @param  string|null  $card  the card of the expense, `type:id`
     * @param  string|null  $notes  the notes of the movement
     * @param  int|null  $transfer_account_id  the account on the other side of a transfer
     * @param  string|null  $account  the account it reads, as «Account name ·· 1234»; null for all of them
     * @param  array{kind: string, id: int, title: string}|null  $planned  the planned item a filter that pays settles
     * @param  int  $taken  how many movements it settled
     * @param  string|null  $missing  what it lacks to work (the planned item ended, the account of the transfer is gone), null when nothing
     * @param  array{update: bool, delete: bool}  $can  what the person may do with it
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly array $contains,
        public readonly array $excludes,
        public readonly string $direction,
        public readonly ?int $financial_account_id,
        public readonly ?float $amount_min,
        public readonly ?float $amount_max,
        public readonly ?string $pattern,
        public readonly string $fee_mode,
        public readonly array $fee_contains,
        public readonly array $fee_excludes,
        public readonly ?string $action,
        public readonly ?int $planned_expense_id,
        public readonly ?int $planned_income_id,
        public readonly ?string $title,
        public readonly bool $title_with_month,
        public readonly ?int $category_id,
        public readonly ?int $organization_id,
        public readonly ?int $person_id,
        public readonly ?string $card,
        public readonly ?string $notes,
        public readonly ?int $transfer_account_id,
        public readonly ?string $account,
        public readonly ?array $planned,
        public readonly int $taken,
        public readonly ?string $missing,
        public readonly array $can,
    ) {}

    /**
     * The data of a saved filter of the current family.
     *
     * @param  BankFilter  $filter  the filter, with `financialAccount`, `plannedExpense`, `plannedIncome` and `transferAccount` loaded, and `transactions_count` when it is known
     */
    public static function fromFilter(BankFilter $filter): self
    {
        $planned = $filter->plannedExpense ?? $filter->plannedIncome;

        return new self(
            id: $filter->id,
            name: $filter->name,
            contains: self::words($filter->contains),
            excludes: self::words($filter->excludes),
            direction: $filter->direction,
            financial_account_id: $filter->financial_account_id,
            amount_min: $filter->amount_min === null ? null : (float) $filter->amount_min,
            amount_max: $filter->amount_max === null ? null : (float) $filter->amount_max,
            pattern: $filter->pattern,
            fee_mode: $filter->fee_mode->value,
            fee_contains: self::words($filter->fee_contains),
            fee_excludes: self::words($filter->fee_excludes),
            action: $filter->action->value,
            planned_expense_id: $filter->planned_expense_id,
            planned_income_id: $filter->planned_income_id,
            title: $filter->title,
            title_with_month: $filter->title_with_month,
            category_id: $filter->category_id,
            organization_id: $filter->organization_id,
            person_id: $filter->person_id,
            card: PresetLink::key($filter->card_type, $filter->card_id),
            notes: $filter->notes,
            transfer_account_id: $filter->transfer_account_id,
            account: $filter->financialAccount?->bankLabel(),
            planned: self::planned($planned),
            taken: (int) ($filter->transactions_count ?? $filter->transactions()->count()),
            missing: self::missing($filter, $planned),
            can: ['update' => Gate::allows('update', $filter), 'delete' => Gate::allows('delete', $filter)],
        );
    }

    /**
     * The data of a filter proposed and not saved (from a movement, a planned item or a group): it settled nothing,
     * misses nothing and cannot be changed or deleted, only saved.
     *
     * @param  BankFilterDraft  $draft  the proposal
     */
    public static function fromDraft(BankFilterDraft $draft): self
    {
        $planned = match (true) {
            $draft->plannedExpenseId !== null => PlannedExpense::query()->find($draft->plannedExpenseId),
            $draft->plannedIncomeId !== null => PlannedIncome::query()->find($draft->plannedIncomeId),
            default => null,
        };

        return new self(
            id: null,
            name: $draft->name,
            contains: $draft->contains,
            excludes: $draft->excludes,
            direction: $draft->direction,
            financial_account_id: $draft->accountId,
            amount_min: $draft->amountMin === null ? null : (float) $draft->amountMin,
            amount_max: $draft->amountMax === null ? null : (float) $draft->amountMax,
            pattern: $draft->pattern,
            fee_mode: $draft->feeMode->value,
            fee_contains: $draft->feeContains ?? [],
            fee_excludes: $draft->feeExcludes ?? [],
            action: $draft->action?->value,
            planned_expense_id: $draft->plannedExpenseId,
            planned_income_id: $draft->plannedIncomeId,
            title: $draft->title,
            title_with_month: $draft->titleWithMonth,
            category_id: $draft->categoryId,
            organization_id: $draft->organizationId,
            person_id: $draft->personId,
            card: null,
            notes: null,
            transfer_account_id: $draft->transferAccountId,
            account: $draft->accountId === null ? null : FinancialAccount::query()->find($draft->accountId)?->bankLabel(),
            planned: self::planned($planned),
            taken: 0,
            missing: null,
            can: ['update' => false, 'delete' => false],
        );
    }

    /**
     * The planned item a filter that pays settles, as the page names it.
     *
     * @param  PlannedExpense|PlannedIncome|null  $planned  the planned item
     * @return array{kind: string, id: int, title: string}|null
     */
    private static function planned(PlannedExpense|PlannedIncome|null $planned): ?array
    {
        return $planned === null ? null : [
            'kind' => $planned instanceof PlannedExpense ? 'expense' : 'income',
            'id' => $planned->id,
            'title' => $planned->displayTitle(),
        ];
    }

    /**
     * What a saved filter lacks to work, in words: criteria that would take everything (they take nothing), a planned
     * item gone or ended, an account of the transfer gone or closed. The filter never fails a read of the bank for
     * these: the movements it would take stay to sort.
     *
     * @param  BankFilter  $filter  the filter
     * @param  PlannedExpense|PlannedIncome|null  $planned  its planned item
     */
    private static function missing(BankFilter $filter, PlannedExpense|PlannedIncome|null $planned): ?string
    {
        $today = CarbonImmutable::today('Europe/Rome')->toDateString();

        if (! BankFilterCriteria::fromFilter($filter)->isSafe()) {
            return (string) __('Its words would take every movement, so it takes none: add a word of three letters or more.');
        }

        $missing = match ($filter->action) {
            BankFilterAction::Pay => match (true) {
                $planned === null => __('The planned item it pays is gone.'),
                $planned->end_date !== null && $planned->end_date->toDateString() < $today => $planned instanceof PlannedExpense
                    ? __('The planned expense ended on :date.', ['date' => $planned->end_date->format('d/m/Y')])
                    : __('The planned income ended on :date.', ['date' => $planned->end_date->format('d/m/Y')]),
                default => null,
            },
            BankFilterAction::Transfer => match (true) {
                ! $filter->transferAccount instanceof FinancialAccount => __('The account of the transfer is gone.'),
                $filter->transferAccount->status !== FinancialAccountStatus::Active => __('The account of the transfer is closed.'),
                default => null,
            },
            default => null,
        };

        return is_string($missing) ? $missing : null;
    }

    /**
     * A list of words as the page reads it: texts only.
     *
     * @param  array<array-key, mixed>|null  $words  the words of a column
     * @return list<string>
     */
    private static function words(?array $words): array
    {
        return array_values(array_filter($words ?? [], is_string(...)));
    }
}
