<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Models\BankTransaction;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Banks\BankFeedAvailability;
use App\Support\PlannedKind;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *It belongs to a planned expense* (or income): who may sort a transaction chooses the planned item it pays. The
 * planned item is looked up first, in `authorize()`, which Laravel runs before the rules: one of another family
 * answers 404 even when the rest of the body is wrong.
 */
final class AssignBankTransactionRequest extends FormRequest
{
    /** The transaction of the address, read once. */
    private ?BankTransaction $transaction = null;

    /** The planned item named in the body, read once. */
    private PlannedExpense|PlannedIncome|null $planned = null;

    /**
     * Whoever may sort the transaction, while the link to the bank is on and the planned item exists in the family.
     *
     * @throws NotFoundHttpException while the link to the bank is off, or when the planned item is not of the family
     * @throws ModelNotFoundException if the family has no such transaction
     */
    public function authorize(): bool
    {
        abort_unless(BankFeedAvailability::on(), 404);

        $kind = $this->input('kind');
        $id = $this->input('planned_id');

        if (is_string($kind) && (is_int($id) || (is_string($id) && ctype_digit($id)))) {
            $this->planned = PlannedKind::find($kind, (string) $id);
        }

        return (bool) $this->user()?->can('update', $this->transaction());
    }

    /**
     * The rules: the kind and the planned item.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['expense', 'income'])],
            'planned_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * The checks after the rules: the planned item must be of the direction of the transaction (an expense for a
     * payment, an income for a credit) and must not have ended before the day the bank booked it.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->planned instanceof PlannedExpense && ! $this->planned instanceof PlannedIncome) {
                return;
            }

            $transaction = $this->transaction();

            if ($this->planned() instanceof PlannedExpense !== ($transaction->kind() === 'expense')) {
                $validator->errors()->add('kind', __('This movement is not of this kind.'));

                return;
            }

            $end = $this->planned()->end_date;

            if ($end !== null && $transaction->booked_on !== null && $end->toDateString() < $transaction->booked_on->toDateString()) {
                $validator->errors()->add('planned_id', $this->planned() instanceof PlannedExpense ? __('This planned expense has ended.') : __('This planned income has ended.'));
            }
        }];
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

    /**
     * The planned item chosen.
     *
     * @throws NotFoundHttpException if none was named (the rules refuse that before it is read)
     */
    public function planned(): PlannedExpense|PlannedIncome
    {
        return $this->planned ?? throw new NotFoundHttpException;
    }
}
