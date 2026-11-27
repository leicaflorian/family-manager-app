<?php

declare(strict_types=1);

namespace App\Support\Banks;

use App\Enums\BankFilterAction;
use App\Enums\BankFilterFee;

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
     * mandate, when there is one, stands alone); when they share none, the counterparty the bank gives.
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

        $counterparty = BankMatch::normalize((string) $group->transactions->first()?->counterparty);

        return mb_strlen($counterparty) >= BankFilterCriteria::MIN_WORD ? [$counterparty] : [];
    }
}
