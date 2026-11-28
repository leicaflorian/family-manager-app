<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Models\BankTransaction;
use App\Models\Expense;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * *It is the commission of…*: who may sort a transaction says which recorded expense the commission belongs to
 * (`expense_id`). The expense is looked up first, in `authorize()`, which Laravel runs before the rules: one of
 * another family answers 404 even when the rest of the body is wrong.
 */
final class AttachBankFeeRequest extends FormRequest
{
    /** The transaction of the address, read once. */
    private ?BankTransaction $transaction = null;

    /** The expense named in the body, read once. */
    private ?Expense $expense = null;

    /**
     * Whoever may sort the transaction.
     *
     * @throws ModelNotFoundException if the family has no such transaction or expense
     */
    public function authorize(): bool
    {
        $id = $this->input('expense_id');

        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $this->expense = Expense::query()->findOrFail($id);
        }

        return (bool) $this->user()?->can('update', $this->transaction());
    }

    /**
     * The rules: the expense the commission belongs to.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['expense_id' => ['required', 'integer', 'min:1']];
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
     * The expense of the body, valid once the rules passed.
     */
    public function expense(): Expense
    {
        return $this->expense ?? throw new ModelNotFoundException;
    }
}
