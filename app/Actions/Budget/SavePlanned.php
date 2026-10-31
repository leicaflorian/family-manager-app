<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\DefaultAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Saves a planned expense or income, new or changed. A planned item without an account takes the default account of
 * the family, as the payments do. When a change moves its due dates, its payments and exceptions move with them.
 */
final readonly class SavePlanned
{
    /** The columns that decide the due dates. */
    private const array SCHEDULE = ['start_date', 'interval', 'interval_unit', 'is_recurring'];

    public function __construct(private FillOccurrenceDates $occurrenceDates) {}

    /**
     * Creates the planned item, or changes the one given.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  array<string, mixed>  $columns  the columns to write
     * @param  PlannedExpense|PlannedIncome|null  $planned  the planned item to change, null for a new one
     *
     * @throws InvalidArgumentException if the kind is not `expense` or `income`
     */
    public function handle(string $kind, array $columns, PlannedExpense|PlannedIncome|null $planned = null): PlannedExpense|PlannedIncome
    {
        // A change that does not send the account keeps the current one; an empty account means the default
        if (! $planned instanceof Model || array_key_exists('financial_account_id', $columns)) {
            $columns['financial_account_id'] ??= DefaultAccount::id();
        }

        if ($planned instanceof PlannedExpense || $planned instanceof PlannedIncome) {
            return DB::transaction(function () use ($planned, $columns): PlannedExpense|PlannedIncome {
                $planned->update($columns);

                if ($planned->wasChanged(self::SCHEDULE)) {
                    $this->occurrenceDates->remap($planned);
                }

                return $planned;
            });
        }

        return match ($kind) {
            'expense' => PlannedExpense::query()->create($columns),
            'income' => PlannedIncome::query()->create($columns),
            default => throw new InvalidArgumentException("Unknown kind {$kind}."),
        };
    }
}
