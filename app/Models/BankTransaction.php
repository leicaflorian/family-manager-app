<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankMatchSource;
use App\Enums\BankTransactionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BankTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;

/**
 * A transaction read from the bank (`bank_transactions`), waiting to be sorted or already linked to an expense or income.
 *
 * @property int $id
 * @property int $family_id
 * @property int|null $bank_connection_id
 * @property int $financial_account_id
 * @property string $provider_id
 * @property CarbonImmutable|null $booked_on
 * @property CarbonImmutable|null $value_on
 * @property numeric $amount With the sign: negative is an outgoing payment
 * @property string $currency
 * @property string $text
 * @property string|null $counterparty
 * @property BankTransactionStatus $status
 * @property int|null $expense_id
 * @property int|null $income_id
 * @property BankMatchSource|null $matched_by
 * @property CarbonImmutable|null $ignored_at
 * @property CarbonImmutable|null $declined_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read BankConnection|null $connection
 * @property-read Expense|null $expense
 * @property-read Family $family
 * @property-read FinancialAccount $financialAccount
 * @property-read Income|null $income
 *
 * @method static BankTransactionFactory factory($count = null, $state = [])
 * @method static Builder<static>|BankTransaction ignored()
 * @method static Builder<static>|BankTransaction newModelQuery()
 * @method static Builder<static>|BankTransaction newQuery()
 * @method static Builder<static>|BankTransaction query()
 * @method static Builder<static>|BankTransaction unsorted()
 *
 * @mixin \Eloquent
 */
#[Fillable(['bank_connection_id', 'financial_account_id', 'provider_id', 'booked_on', 'value_on', 'amount', 'currency', 'text', 'counterparty', 'status', 'expense_id', 'income_id', 'matched_by', 'ignored_at', 'declined_at'])]
final class BankTransaction extends FamilyModel
{
    /** @use HasFactory<BankTransactionFactory> */
    use HasFactory;

    use MassPrunable;

    /**
     * The transactions not linked to an expense or an income and older than the months the app keeps them, of every
     * family: pruning runs without a signed-in family, so the scope is off.
     *
     * @return Builder<BankTransaction>
     */
    public function prunable(): Builder
    {
        $limit = now()->subMonths(Config::integer('bank_feed.kept_months'));

        return self::query()->withoutGlobalScopes()
            ->whereNull('expense_id')
            ->whereNull('income_id')
            ->whereRaw('coalesce(booked_on, created_at) < ?', [$limit]);
    }

    /**
     * The transactions still to sort: booked, in euros, not linked and not ignored.
     *
     * @param  Builder<BankTransaction>  $query
     */
    #[Scope]
    protected function unsorted(Builder $query): void
    {
        $query->where('status', BankTransactionStatus::Booked)
            ->whereNull('expense_id')
            ->whereNull('income_id')
            ->whereNull('ignored_at')
            ->where('currency', 'EUR');
    }

    /**
     * The transactions the person put aside.
     *
     * @param  Builder<BankTransaction>  $query
     */
    #[Scope]
    protected function ignored(Builder $query): void
    {
        $query->whereNotNull('ignored_at');
    }

    /**
     * What the transaction is: an `expense` when the money left the account, otherwise an `income`.
     */
    public function kind(): string
    {
        return (float) $this->amount < 0 ? 'expense' : 'income';
    }

    /**
     * The expense or the income the transaction is linked to.
     */
    public function movement(): Expense|Income|null
    {
        return $this->expense ?? $this->income;
    }

    /**
     * Links the transaction to the expense or income it belongs to and saves it. The caller has the row locked.
     *
     * @param  Expense|Income  $movement  The movement.
     * @param  BankMatchSource  $by  How the link came.
     */
    public function linkTo(Expense|Income $movement, BankMatchSource $by): void
    {
        $this->forceFill([
            'expense_id' => $movement instanceof Expense ? $movement->id : null,
            'income_id' => $movement instanceof Income ? $movement->id : null,
            'matched_by' => $by,
        ])->save();
    }

    /**
     * The connection it was read through.
     *
     * @return BelongsTo<BankConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class, 'bank_connection_id');
    }

    /**
     * The account it belongs to.
     *
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /**
     * The expense it is linked to.
     *
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * The income it is linked to.
     *
     * @return BelongsTo<Income, $this>
     */
    public function income(): BelongsTo
    {
        return $this->belongsTo(Income::class);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booked_on' => 'immutable_date',
            'value_on' => 'immutable_date',
            'amount' => 'decimal:2',
            'status' => BankTransactionStatus::class,
            'matched_by' => BankMatchSource::class,
            'ignored_at' => 'immutable_datetime',
            'declined_at' => 'immutable_datetime',
        ];
    }
}
