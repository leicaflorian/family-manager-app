<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Enums\IncomeType;
use App\Enums\IntervalUnit;
use App\Http\Requests\Concerns\ReadsCard;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Rules\OfFamily;
use App\Support\PlannedKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The form of a planned expense or income, new or changed (only an `admin`). The address is resolved first: an
 * unknown kind or a planned item of another family answers 404 before anyone is refused or the body is read.
 */
final class SavePlannedRequest extends FormRequest
{
    use ReadsCard;

    /**
     * The kind of the route.
     *
     * @return string `expense` or `income`
     *
     * @throws NotFoundHttpException if the kind is not `expense` or `income`
     */
    public function kind(): string
    {
        $kind = $this->route('kind');

        return $kind === 'expense' || $kind === 'income' ? $kind : throw new NotFoundHttpException;
    }

    /**
     * The planned item of the route, or null on a new one.
     *
     * @throws NotFoundHttpException if the kind is unknown
     * @throws ModelNotFoundException if the family has no such planned item (Laravel answers 404)
     */
    public function planned(): PlannedExpense|PlannedIncome|null
    {
        $id = $this->route('id');

        return is_string($id) ? PlannedKind::find($this->kind(), $id) : null;
    }

    /**
     * A new planned item whoever may add them, a change whoever may change it: the `admin`.
     */
    public function authorize(): bool
    {
        $planned = $this->planned();

        return $planned === null
            ? (bool) $this->user()?->can('create', PlannedKind::modelClass($this->kind()))
            : (bool) $this->user()?->can('update', $planned);
    }

    /**
     * The rules of the fields. The people, organizations, accounts, categories, employments and cards are looked for
     * in the family only.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $expense = $this->kind() === 'expense';

        return [
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'is_estimated' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_recurring' => ['boolean'],
            'interval' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'interval_unit' => ['nullable', Rule::enum(IntervalUnit::class)],
            'organization_id' => ['nullable', new OfFamily('organizations')],
            'financial_account_id' => ['nullable', new OfFamily('financial_accounts')],
            'person_id' => ['nullable', new OfFamily('people')],
            ...$expense
                ? [
                    'category_id' => ['nullable', new OfFamily('expense_categories')],
                    ...$this->cardRules(),
                ]
                : [
                    'type' => ['required', Rule::enum(IncomeType::class)],
                    'employment_id' => ['nullable', new OfFamily('employments')],
                ],
        ];
    }

    /**
     * The columns to write: the validated fields, and for an expense the card of *Refers to* as the pair
     * `card_type`, `card_id` (both empty without one).
     *
     * @return array<string, mixed>
     */
    public function columns(): array
    {
        $validated = $this->safe()->except(['card']);
        $columns = [
            ...$validated,
            'is_recurring' => $this->boolean('is_recurring'),
            'is_estimated' => $this->boolean('is_estimated'),
            'interval' => max(1, $this->integer('interval')),
            'interval_unit' => $this->input('interval_unit') ?? IntervalUnit::Month->value,
        ];

        if ($this->kind() === 'income') {
            return $columns;
        }

        return [...$columns, ...$this->cardColumns()];
    }

    /**
     * The category of the planned expense being changed, null on a new one.
     *
     * @throws NotFoundHttpException if the kind is unknown
     * @throws ModelNotFoundException if the family has no such planned item (Laravel answers 404)
     */
    protected function savedCategoryId(): ?int
    {
        $planned = $this->planned();

        return $planned instanceof PlannedExpense ? $planned->category_id : null;
    }
}
