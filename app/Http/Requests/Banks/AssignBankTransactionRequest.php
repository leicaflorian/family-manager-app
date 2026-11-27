<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\PlannedKind;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *It belongs to an expense* (or income): who may sort a transaction chooses the planned item it pays (`planned_id`,
 * with the due date `occurrence` when the person chose one) or an expense or income already recorded (`movement_id`).
 * What is chosen is looked up first, in `authorize()`, which Laravel runs before the rules: one of another family
 * answers 404 even when the rest of the body is wrong.
 */
final class AssignBankTransactionRequest extends FormRequest
{
    /** The transaction of the address, read once. */
    private ?BankTransaction $transaction = null;

    /** The planned item named in the body, read once. */
    private PlannedExpense|PlannedIncome|null $planned = null;

    /** The recorded expense or income named in the body, read once. */
    private Expense|Income|null $movement = null;

    /**
     * Whoever may sort the transaction, while the link to the bank is on and the planned item exists in the family.
     *
     * @throws NotFoundHttpException while the link to the bank is off, or when the planned item is not of the family
     * @throws ModelNotFoundException if the family has no such transaction
     */
    public function authorize(): bool
    {
        $kind = $this->input('kind');
        $id = $this->input('planned_id');
        $movementId = $this->input('movement_id');

        if (is_string($kind) && (is_int($id) || (is_string($id) && ctype_digit($id)))) {
            $this->planned = PlannedKind::find($kind, (string) $id);
        }

        if (is_string($kind) && (is_int($movementId) || (is_string($movementId) && ctype_digit($movementId)))) {
            $this->movement = match ($kind) {
                'expense' => Expense::query()->findOrFail($movementId),
                'income' => Income::query()->findOrFail($movementId),
                default => throw new NotFoundHttpException,
            };
        }

        return (bool) $this->user()?->can('update', $this->transaction());
    }

    /**
     * The rules: the kind and either the planned item (with its due date, when chosen) or the recorded movement.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['expense', 'income'])],
            'planned_id' => ['required_without:movement_id', 'prohibits:movement_id', 'integer', 'min:1'],
            'occurrence' => ['nullable', 'prohibits:movement_id', 'date_format:Y-m-d'],
            'movement_id' => ['required_without:planned_id', 'integer', 'min:1'],
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
     * The recorded expense or income chosen, null when the person chose a planned item.
     */
    public function movement(): Expense|Income|null
    {
        return $this->movement;
    }

    /**
     * The due date the person chose, null when the closest one is to be paid.
     */
    public function occurrence(): ?CarbonImmutable
    {
        $day = $this->input('occurrence');

        return is_string($day) && $day !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $day, 'Europe/Rome') ?: null : null;
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
