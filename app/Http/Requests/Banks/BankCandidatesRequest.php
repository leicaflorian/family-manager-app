<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Models\BankTransaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The search of *It belongs to an expense*: who may sort the transaction of the address writes what to look for.
 */
final class BankCandidatesRequest extends FormRequest
{
    /** The transaction of the address, read once. */
    private ?BankTransaction $transaction = null;

    /**
     * Whoever may sort the transaction; one of another family answers 404 before anything else.
     *
     * @throws ModelNotFoundException if the family has no such transaction
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->transaction());
    }

    /**
     * The rules: the words to look for, which may be none.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100']];
    }

    /**
     * What the person wrote, empty when nothing.
     */
    public function searchText(): string
    {
        return trim($this->string('q')->toString());
    }

    /**
     * The transaction of the address.
     *
     * @throws ModelNotFoundException if the family has no such transaction (Laravel answers 404)
     */
    public function transaction(): BankTransaction
    {
        $id = $this->route('transaction');

        return $this->transaction ??= BankTransaction::query()->findOrFail(is_numeric($id) ? (int) $id : 0);
    }
}
