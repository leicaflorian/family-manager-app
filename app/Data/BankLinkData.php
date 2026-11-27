<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The expense or income a bank transaction is linked to, as the list *All* of *From the bank* shows it.
 */
#[TypeScript]
final class BankLinkData extends Data
{
    /**
     * @param  string  $kind  `expense` or `income`
     * @param  int  $id  the expense or income
     * @param  string  $title  its title
     * @param  string  $url  the address of its page
     * @param  string  $how  how the link came (a `BankMatchSource` value)
     * @param  int|null  $filter_id  the filter that made the link, once filters exist
     * @param  string|null  $filter_name  the name of that filter
     * @param  string|null  $undo  what taking the link back does: `delete` (the movement goes) or `detach`; null when it cannot be undone from here
     * @param  string|null  $undo_url  where *It was not this one* goes, only when `undo` is given
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $id,
        public readonly string $title,
        public readonly string $url,
        public readonly string $how,
        public readonly ?int $filter_id,
        public readonly ?string $filter_name,
        public readonly ?string $undo,
        public readonly ?string $undo_url,
    ) {}

    /**
     * The link of a transaction to its movement, or null for a transaction that is not linked. The undo is offered for
     * the payments the bank recorded, to whoever can delete the movement.
     *
     * @param  BankTransaction  $transaction  the transaction, with `expense` and `income` (and its `organization`) loaded
     */
    public static function fromTransaction(BankTransaction $transaction): ?self
    {
        $movement = $transaction->movement();

        if (! $movement instanceof Expense && ! $movement instanceof Income) {
            return null;
        }

        $kind = $movement instanceof Expense ? 'expense' : 'income';
        $recorded = in_array($transaction->matched_by, [BankMatchSource::Rule, BankMatchSource::Assigned], true);
        $undo = $recorded && Gate::allows('delete', $movement);

        return new self(
            $kind,
            $movement->id,
            $movement->label(),
            route('budget.movements.show', ['kind' => $kind, 'id' => $movement->id], absolute: false),
            $transaction->matched_by->value ?? BankMatchSource::Duplicate->value,
            null,
            null,
            $undo ? 'delete' : null,
            $undo ? route('budget.bank.undo', ['transaction' => $transaction->id], absolute: false) : null,
        );
    }
}
