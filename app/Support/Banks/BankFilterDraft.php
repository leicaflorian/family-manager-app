<?php

declare(strict_types=1);

namespace App\Support\Banks;

use App\Data\BankFilterData;
use App\Enums\BankFilterAction;
use App\Enums\BankFilterFee;
use App\Models\BankTransaction;
use App\Models\Expense;

/**
 * A bank filter that is not saved yet: what the system proposes for a group of transactions, ready to fill the form
 * of a new filter. It has the fields of a row of `bank_filters`; the person checks them and saves.
 */
final readonly class BankFilterDraft
{
    /**
     * @param  string  $name  the name of the filter
     * @param  list<string>  $contains  the words the text must all have
     * @param  list<string>  $excludes  the words the text must not have
     * @param  string  $direction  `expense` or `income`
     * @param  int|null  $accountId  the account to read
     * @param  numeric-string|null  $amountMin  the smallest amount
     * @param  numeric-string|null  $amountMax  the largest amount
     * @param  string|null  $pattern  the expression of an advanced filter
     * @param  BankFilterFee  $feeMode  how the commission is found
     * @param  list<string>|null  $feeContains  the words of the commission
     * @param  list<string>|null  $feeExcludes  the words the commission must not have
     * @param  BankFilterAction|null  $action  what the filter does, null while nobody proposes one
     * @param  int|null  $plannedExpenseId  the planned expense a filter that pays settles
     * @param  int|null  $plannedIncomeId  the planned income a filter that pays settles
     * @param  string|null  $title  the title of the expense or income the filter creates
     * @param  bool  $titleWithMonth  whether the title ends with the month
     * @param  int|null  $categoryId  the category of the expense
     * @param  int|null  $organizationId  the organization of the expense or income
     * @param  int|null  $personId  the person of the expense or income
     * @param  int|null  $transferAccountId  the account on the other side of a transfer
     * @param  string|null  $detail  what the proposal does, in words for the group («Varie · Satispay»); not a column
     */
    public function __construct(
        public string $name = '',
        public array $contains = [],
        public array $excludes = [],
        public string $direction = 'expense',
        public ?int $accountId = null,
        public ?string $amountMin = null,
        public ?string $amountMax = null,
        public ?string $pattern = null,
        public BankFilterFee $feeMode = BankFilterFee::Auto,
        public ?array $feeContains = null,
        public ?array $feeExcludes = null,
        public ?BankFilterAction $action = null,
        public ?int $plannedExpenseId = null,
        public ?int $plannedIncomeId = null,
        public ?string $title = null,
        public bool $titleWithMonth = false,
        public ?int $categoryId = null,
        public ?int $organizationId = null,
        public ?int $personId = null,
        public ?int $transferAccountId = null,
        public ?string $detail = null,
    ) {}

    /**
     * The filter that takes a group: the words every transaction has (the mandate when there is one), without the
     * commissions, in the direction and on the account of the group, the commission found by itself. The action and
     * what it needs come from the proposal of the group, when it has one.
     *
     * @param  BankGroup  $group  the group
     * @param  self|null  $proposal  what `BankGroupProposals::for()` gave, whose action fields are taken
     */
    public static function fromGroup(BankGroup $group, ?self $proposal): self
    {
        return new self(
            name: $group->name,
            contains: self::words($group),
            excludes: ['COMMISSIONI'],
            direction: $group->kind,
            accountId: $group->accountId,
            feeMode: BankFilterFee::Auto,
            action: $proposal?->action,
            plannedExpenseId: $proposal?->plannedExpenseId,
            plannedIncomeId: $proposal?->plannedIncomeId,
            title: $proposal?->title,
            titleWithMonth: $proposal instanceof self && $proposal->titleWithMonth,
            categoryId: $proposal?->categoryId,
            organizationId: $proposal?->organizationId,
            personId: $proposal?->personId,
            transferAccountId: $proposal?->transferAccountId,
            detail: $proposal?->detail,
        );
    }

    /**
     * The filter for the movements like one the person has just sorted by hand: its words (`BankText::words()`),
     * direction and account, with `COMMISSIONI` among the words it must not have, and the action read from what
     * happened to it: a due date paid pays that planned item, an expense or income recorded creates one with its title
     * (the title as it is, without the month), category, organization and person, a transfer is a transfer to its
     * account. A movement still to sort has no action. The expense, income and transfer account must be loaded or are read here.
     *
     * @param  BankTransaction  $transaction  the transaction, sorted
     * @param  bool  $mayPay  whether the person may create a filter that pays; without it a due date paid leaves no action
     */
    public static function fromLink(BankTransaction $transaction, bool $mayPay = true): self
    {
        $expense = $transaction->expense;
        $income = $transaction->income;
        $planned = match (true) {
            $expense !== null => $expense->plannedExpense,
            $income !== null => $income->plannedIncome,
            default => null,
        };
        $counterparty = mb_substr(trim((string) $transaction->counterparty), 0, 100);

        $action = match (true) {
            $transaction->transfer_account_id !== null => BankFilterAction::Transfer,
            $planned !== null => $mayPay ? BankFilterAction::Pay : null,
            $expense !== null || $income !== null => BankFilterAction::Create,
            default => null,
        };
        $creates = $action === BankFilterAction::Create;
        $movement = $expense ?? $income;

        return new self(
            name: $planned?->displayTitle() ?? $counterparty,
            contains: BankText::words($transaction->text),
            excludes: ['COMMISSIONI'],
            direction: $transaction->kind(),
            accountId: $transaction->financial_account_id,
            action: $action,
            plannedExpenseId: $action === BankFilterAction::Pay ? $expense?->planned_expense_id : null,
            plannedIncomeId: $action === BankFilterAction::Pay ? $income?->planned_income_id : null,
            title: $creates && $movement !== null ? ($movement instanceof Expense ? $movement->title : $movement->label()) : null,
            categoryId: $creates ? $expense?->category_id : null,
            organizationId: $creates ? $movement?->organization_id : null,
            personId: $creates ? $movement?->person_id : null,
            transferAccountId: $action === BankFilterAction::Transfer ? $transaction->transfer_account_id : null,
        );
    }

    /**
     * The proposal as the form of a new filter receives it (`draft`).
     */
    public function toData(): BankFilterData
    {
        return BankFilterData::fromDraft($this);
    }

    /**
     * The fields as the columns of `bank_filters` have them (enums as their values), without the detail.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'contains' => $this->contains,
            'excludes' => $this->excludes,
            'direction' => $this->direction,
            'financial_account_id' => $this->accountId,
            'amount_min' => $this->amountMin,
            'amount_max' => $this->amountMax,
            'pattern' => $this->pattern,
            'fee_mode' => $this->feeMode->value,
            'fee_contains' => $this->feeContains,
            'fee_excludes' => $this->feeExcludes,
            'action' => $this->action?->value,
            'planned_expense_id' => $this->plannedExpenseId,
            'planned_income_id' => $this->plannedIncomeId,
            'title' => $this->title,
            'title_with_month' => $this->titleWithMonth,
            'category_id' => $this->categoryId,
            'organization_id' => $this->organizationId,
            'person_id' => $this->personId,
            'transfer_account_id' => $this->transferAccountId,
        ];
    }

    /**
     * The words that every transaction of the group has, as `BankText::words()` proposes them for each (so the
     * mandate, when there is one, stands alone); when they share none, the counterparty the bank gives, else the words of the newest movement.
     *
     * @param  BankGroup  $group  the group
     * @return list<string>
     */
    private static function words(BankGroup $group): array
    {
        $lists = $group->transactions->map(static fn ($transaction): array => BankText::words($transaction->text))->values()->all();
        $first = $lists[0] ?? [];
        $shared = array_values(array_filter($first, static fn (string $word): bool => array_all($lists, static fn (array $words): bool => in_array($word, $words, true))));

        if ($shared !== []) {
            return $shared;
        }

        $newest = $group->transactions->first();
        $counterparty = BankMatch::normalize((string) $newest?->counterparty);

        if (mb_strlen($counterparty) >= BankFilterCriteria::MIN_WORD) {
            return [$counterparty];
        }

        // A filter with no words would take everything: the words of the newest movement are the last resort
        return $newest === null ? [] : BankText::words($newest->text);
    }
}
