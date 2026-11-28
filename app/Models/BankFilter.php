<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankFilterAction;
use App\Enums\BankFilterFee;
use App\Support\Banks\BankFilterCriteria;
use Carbon\CarbonImmutable;
use Closure;
use Database\Factories\BankFilterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A filter of the bank (`bank_filters`): what it takes (words, direction, account, amounts or a pattern) and what it
 * does with the transactions it takes (pays a due date, creates an expense, marks a transfer or ignores them).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property array<array-key, mixed> $contains Words or phrases the text must all have
 * @property array<array-key, mixed> $excludes Words or phrases the text must not have
 * @property string $direction expense or income
 * @property int|null $financial_account_id
 * @property numeric|null $amount_min
 * @property numeric|null $amount_max
 * @property string|null $pattern The expression of an advanced filter, without delimiters
 * @property BankFilterFee $fee_mode
 * @property array<array-key, mixed>|null $fee_contains
 * @property array<array-key, mixed>|null $fee_excludes
 * @property BankFilterAction $action
 * @property int|null $planned_expense_id
 * @property int|null $planned_income_id
 * @property string|null $title
 * @property bool $title_with_month
 * @property int|null $category_id
 * @property int|null $organization_id
 * @property int|null $person_id
 * @property string|null $card_type
 * @property int|null $card_id
 * @property string|null $notes
 * @property int|null $transfer_account_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Model|null $card
 * @property-read ExpenseCategory|null $category
 * @property-read Family $family
 * @property-read FinancialAccount|null $financialAccount
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 * @property-read PlannedExpense|null $plannedExpense
 * @property-read PlannedIncome|null $plannedIncome
 * @property-read FinancialAccount|null $transferAccount
 * @property-read Collection<int, BankTransaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read int|null $taken_count The movements it settled booked in the last 90 days, loaded by `forPage()`
 *
 * @method static BankFilterFactory factory($count = null, $state = [])
 * @method static Builder<static>|BankFilter newModelQuery()
 * @method static Builder<static>|BankFilter newQuery()
 * @method static Builder<static>|BankFilter query()
 * @method static Builder<static>|BankFilter forPage()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'contains', 'excludes', 'direction', 'financial_account_id', 'amount_min', 'amount_max', 'pattern', 'fee_mode', 'fee_contains', 'fee_excludes', 'action', 'planned_expense_id', 'planned_income_id', 'title', 'title_with_month', 'category_id', 'organization_id', 'person_id', 'card_type', 'card_id', 'notes', 'transfer_account_id'])]
final class BankFilter extends FamilyModel
{
    /** @use HasFactory<BankFilterFactory> */
    use HasFactory;

    /**
     * The values a new filter has before they are set.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'contains' => '[]',
        'excludes' => '[]',
        'direction' => 'expense',
        'fee_mode' => 'none',
        'title_with_month' => false,
    ];

    /**
     * What the filter takes.
     */
    public function criteria(): BankFilterCriteria
    {
        return BankFilterCriteria::fromFilter($this);
    }

    /** The relations the pages of the filters read (`BankFilterData`). */
    private const array PAGE_RELATIONS = ['financialAccount', 'plannedExpense', 'plannedIncome', 'transferAccount', 'category', 'organization'];

    /**
     * The filters as the pages read them: with the relations `BankFilterData` names and `taken_count`, the movements
     * each settled that were booked in the last days of a first read (90, the window of the preview).
     *
     * @param  Builder<self>  $query  the query
     */
    #[Scope]
    protected function forPage(Builder $query): void
    {
        $query->with(self::PAGE_RELATIONS)->withCount($this->takenCount());
    }

    /**
     * Loads on one filter what `forPage()` loads on a query.
     */
    public function loadForPage(): self
    {
        return $this->load(self::PAGE_RELATIONS)->loadCount($this->takenCount());
    }

    /**
     * The count of the movements a filter settled that were booked in the last days of a first read, as `taken_count`.
     *
     * @return array<string, Closure(Builder<BankTransaction>): void>
     */
    private function takenCount(): array
    {
        $since = CarbonImmutable::today('Europe/Rome')->subDays(config()->integer('bank_feed.first_days'))->toDateString();

        return ['transactions as taken_count' => static function (Builder $query) use ($since): void {
            $query->where('booked_on', '>=', $since);
        }];
    }

    /**
     * The transactions of the bank the filter settled (`bank_transactions.bank_filter_id`).
     *
     * @return HasMany<BankTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    /**
     * The account the filter reads.
     *
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /**
     * The account of the other side of a transfer.
     *
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function transferAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'transfer_account_id');
    }

    /**
     * The planned expense a filter that pays a due date settles.
     *
     * @return BelongsTo<PlannedExpense, $this>
     */
    public function plannedExpense(): BelongsTo
    {
        return $this->belongsTo(PlannedExpense::class);
    }

    /**
     * The planned income a filter that pays a due date settles.
     *
     * @return BelongsTo<PlannedIncome, $this>
     */
    public function plannedIncome(): BelongsTo
    {
        return $this->belongsTo(PlannedIncome::class);
    }

    /**
     * The category of the expense a filter creates.
     *
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * The organization of the expense a filter creates.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The person of the expense a filter creates.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The card of the expense a filter creates, stored as an expense stores it: the type of the morph map and the id.
     *
     * @return MorphTo<Model, $this>
     */
    public function card(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contains' => 'array',
            'excludes' => 'array',
            'amount_min' => 'decimal:2',
            'amount_max' => 'decimal:2',
            'fee_mode' => BankFilterFee::class,
            'fee_contains' => 'array',
            'fee_excludes' => 'array',
            'action' => BankFilterAction::class,
            'title_with_month' => 'boolean',
            'card_id' => 'integer',
        ];
    }
}
