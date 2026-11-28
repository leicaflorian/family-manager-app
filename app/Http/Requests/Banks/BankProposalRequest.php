<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Actions\Banks\AcceptBankProposal;
use App\Models\BankTransaction;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * *Yes* and *No* on «Maybe it is…»: who may sort the movement sends the key of the proposal (`key`). The movement is
 * looked up in `authorize()`, which Laravel runs before the rules: one of another family answers 404 even with a
 * wrong body.
 */
final class BankProposalRequest extends FormRequest
{
    /** The transaction of the address, read once. */
    private ?BankTransaction $transaction = null;

    /**
     * Whoever may sort the transaction (a `member` too).
     *
     * @throws ModelNotFoundException if the family has no such transaction
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->transaction());
    }

    /**
     * The rules: a key of a proposal.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['key' => ['required', 'string', 'max:80', static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || AcceptBankProposal::parse($value) === null) {
                $fail(__('This proposal is not valid any more: look at the movement again.'));
            }
        }]];
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
     * The key, valid once the rules passed.
     */
    public function proposalKey(): string
    {
        return $this->string('key')->toString();
    }
}
