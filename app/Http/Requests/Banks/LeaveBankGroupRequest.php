<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Models\BankTransaction;
use App\Services\Banks\BankGroups;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * *It is not of this group*: who may sort the movement sends the key of the group it leaves (`group`). The movement is
 * looked up in `authorize()`, which Laravel runs before the rules: one of another family answers 404 even with a
 * wrong body. The key must be the one of the group of the movement.
 */
final class LeaveBankGroupRequest extends FormRequest
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
     * The rules: the key of the group of the movement.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['group' => ['required', 'string', 'max:40', function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== resolve(BankGroups::class)->keyOf($this->transaction())) {
                $fail(__('This movement is not in this group any more: look at the groups again.'));
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
     * The key of the group, valid once the rules passed.
     */
    public function groupKey(): string
    {
        return $this->string('group')->toString();
    }
}
