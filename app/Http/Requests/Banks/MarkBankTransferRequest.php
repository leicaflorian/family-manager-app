<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Models\BankTransaction;
use App\Models\FinancialAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * *It is a transfer*: who may sort a transaction says which account of the family is on the other side
 * (`account_id`). The account is looked up first, in `authorize()`, which Laravel runs before the rules: one of
 * another family answers 404 even when the rest of the body is wrong.
 */
final class MarkBankTransferRequest extends FormRequest
{
    /** The transaction of the address, read once. */
    private ?BankTransaction $transaction = null;

    /** The account named in the body, read once. */
    private ?FinancialAccount $account = null;

    /**
     * Whoever may sort the transaction.
     *
     * @throws ModelNotFoundException if the family has no such transaction or account
     */
    public function authorize(): bool
    {
        $id = $this->input('account_id');

        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $this->account = FinancialAccount::query()->findOrFail($id);
        }

        return (bool) $this->user()?->can('update', $this->transaction());
    }

    /**
     * The rules: the account on the other side.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['account_id' => ['required', 'integer', 'min:1']];
    }

    /**
     * The transaction of the address (of the family, or 404).
     *
     * @throws ModelNotFoundException if the family has no such transaction
     */
    public function transaction(): BankTransaction
    {
        $id = $this->route('transaction');

        return $this->transaction ??= $id instanceof BankTransaction ? $id : BankTransaction::query()->findOrFail(is_numeric($id) ? (int) $id : 0);
    }

    /**
     * The account on the other side, valid once the rules passed.
     */
    public function account(): FinancialAccount
    {
        return $this->account ?? throw new ModelNotFoundException;
    }
}
