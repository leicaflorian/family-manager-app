<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Enums\IncomeType;
use App\Http\Requests\Concerns\ReadsCard;
use App\Models\Expense;
use App\Models\Income;
use App\Rules\OfFamily;
use App\Services\Movements;
use App\Support\PlannedKind;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The form of an expense or an income, new or changed. A new one names its kind in the body, a change in the address.
 * The address is resolved first: an unknown kind or a movement of another family answers 404 before anyone is refused
 * or the body is read. Whoever may create or change an expense or an income (so a `member` too) may save it.
 */
final class SaveMovementRequest extends FormRequest
{
    use ReadsCard;

    /**
     * The kind: the one of the address of a change, the one of the body of a new movement.
     *
     * @return string `expense` or `income`
     */
    public function kind(): string
    {
        $kind = $this->route('kind') ?? $this->input('kind');

        return $kind === 'income' ? 'income' : 'expense';
    }

    /**
     * The movement of the address, or null on a new one.
     *
     * @throws NotFoundHttpException if the kind is not `expense` or `income`
     * @throws ModelNotFoundException if the family has no such movement (Laravel answers 404)
     */
    public function movement(): Expense|Income|null
    {
        $kind = $this->route('kind');
        $id = $this->route('id');

        return is_string($kind) && is_string($id) ? Movements::find($kind, $id) : null;
    }

    /**
     * A new movement whoever may create one, a change whoever may change that one.
     */
    public function authorize(): bool
    {
        $movement = $this->movement();

        return $movement === null
            ? (bool) $this->user()?->can('create', Movements::modelClass($this->kind()))
            : (bool) $this->user()?->can('update', $movement);
    }

    /**
     * The rules of the fields. The accounts, categories, organizations, people and planned items are looked for in
     * the family only; the due date must be one that the planned item gives and nothing pays.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $expense = $this->kind() === 'expense';

        return [
            ...$this->route('id') === null ? ['kind' => ['required', Rule::in(['expense', 'income'])]] : [],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'financial_account_id' => ['nullable', new OfFamily('financial_accounts')],
            'organization_id' => ['nullable', new OfFamily('organizations')],
            'person_id' => ['nullable', new OfFamily('people')],
            'planned_id' => ['nullable', new OfFamily($expense ? 'planned_expenses' : 'planned_incomes')],
            'occurrence_date' => ['nullable', 'date_format:Y-m-d', $this->occurrenceRule(...)],
            ...$expense
                ? [
                    'fee_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                    'category_id' => ['nullable', new OfFamily('expense_categories')],
                    ...$this->cardRules(),
                ]
                : [
                    'type' => [$this->route('id') === null ? 'required' : 'sometimes', 'required', Rule::enum(IncomeType::class)],
                    'employment_id' => ['nullable', new OfFamily('employments')],
                ],
        ];
    }

    /**
     * The checks after the rules: without a due date named, the one that the day registers must be open.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $planned = $this->input('planned_id');
            $day = $this->input('date');
            $date = is_string($day) ? CarbonImmutable::createFromFormat('!Y-m-d', $day, 'Europe/Rome') : false;

            if ($validator->errors()->isNotEmpty() || ! is_numeric($planned) || $this->filled('occurrence_date') || ! $date instanceof CarbonImmutable) {
                return;
            }

            if (! resolve(Movements::class)->registeredIsOpen(PlannedKind::find($this->kind(), (string) $planned), $date, $this->movement())) {
                $validator->errors()->add('occurrence_date', __('This due date is already paid: choose another one.'));
            }
        }];
    }

    /**
     * The columns to write: the validated fields, `planned_id` among them when the form sent it, and the card of an
     * expense as the pair `card_type`, `card_id` when the form sent it.
     *
     * @return array<string, mixed>
     */
    public function columns(): array
    {
        $columns = $this->safe()->except(['kind', 'card']);

        return $this->kind() === 'expense' && $this->exists('card') ? [...$columns, ...$this->cardColumns()] : $columns;
    }

    /**
     * The category of the expense being changed, null on a new one or an income.
     *
     * @throws NotFoundHttpException if the kind is not `expense` or `income`
     * @throws ModelNotFoundException if the family has no such movement (Laravel answers 404)
     */
    protected function savedCategoryId(): ?int
    {
        $movement = $this->movement();

        return $movement instanceof Expense ? $movement->category_id : null;
    }

    /**
     * Checks that the due date is one of the planned item chosen that nothing pays (or that this movement pays).
     *
     * @param  string  $attribute  the field
     * @param  mixed  $value  the day sent
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail  reports the error
     */
    private function occurrenceRule(string $attribute, mixed $value, Closure $fail): void
    {
        $planned = $this->input('planned_id');
        $day = is_string($value) ? CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Europe/Rome') : false;

        $open = is_numeric($planned) && $day instanceof CarbonImmutable
            && resolve(Movements::class)->isOpen(PlannedKind::find($this->kind(), (string) $planned), $day, $this->movement());

        if (! $open) {
            $fail(__('This is not a due date that is still open.'));
        }
    }
}
