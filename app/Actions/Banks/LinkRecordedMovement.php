<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Services\Banks\BankDuplicates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Links a bank transaction to an expense or income somebody already recorded, without creating anything (*It belongs
 * to an expense*, the person chose it from the search).
 */
final readonly class LinkRecordedMovement
{
    public function __construct(private BankDuplicates $duplicates) {}

    /**
     * Links them, in one transaction. The bank transaction is locked first and the movement right after, before
     * anything else is read, as `SettleFromBank` does, so two links of the same movement wait for each other.
     *
     * @param  BankTransaction  $transaction  The transaction of the bank.
     * @param  Expense|Income  $movement  The expense or income chosen.
     * @return Expense|Income the movement, now linked
     *
     * @throws ValidationException (`movement_id`) if the transaction is not to sort any more, the movement is not of its kind or another bank transaction has it
     */
    public function handle(BankTransaction $transaction, Expense|Income $movement): Expense|Income
    {
        return DB::transaction(function () use ($transaction, $movement): Expense|Income {
            $current = BankTransaction::query()->unsorted()->whereKey($transaction->id)->lockForUpdate()->first();

            if (! $current instanceof BankTransaction || ! $current->booked_on instanceof CarbonImmutable) {
                throw ValidationException::withMessages(['movement_id' => __('This movement is already sorted.')]);
            }

            if (($movement instanceof Expense) !== ($current->kind() === 'expense')) {
                throw ValidationException::withMessages(['movement_id' => __('This movement is not of this kind.')]);
            }

            $locked = $movement->newQuery()->lockForUpdate()->find($movement->getKey());

            if (! $locked instanceof Expense && ! $locked instanceof Income || ! $this->duplicates->isFree($locked, $current)) {
                throw ValidationException::withMessages(['movement_id' => __('This expense or income is already linked to another movement of the bank.')]);
            }

            $current->linkTo($locked, BankMatchSource::Duplicate);

            return $locked;
        });
    }
}
