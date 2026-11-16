<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Http\Requests\Concerns\ReadsMonth;
use App\Models\Expense;
use App\Models\Income;
use App\Rules\OfFamily;
use App\Services\Movements;
use App\Support\MovementFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The address of *Movements*: `?month=2026-10&kind=expense&account=3&movement=expense:12`. Whatever is not a month,
 * a kind, an account or a movement of the family is a wrong address: it answers 404, as a wrong id does. The filters of
 * the search (`search`, `min`, `max`, `from`, `to`) are typed by the person, so a wrong one is an error to correct (422).
 */
final class MovementIndexRequest extends FormRequest
{
    use ReadsMonth {
        failedValidation as private notFound;
    }

    /** The fields typed by the person: a mistake in them is a 422, not a wrong address. */
    private const array FILTER_FIELDS = ['search', 'min', 'max', 'from', 'to'];

    /**
     * The rules of the address.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...self::MONTH_RULES,
            'kind' => ['nullable', 'in:expense,income'],
            'account' => ['nullable', new OfFamily('financial_accounts')],
            'movement' => ['nullable', 'regex:/^(expense|income):[0-9]+$/'],
            'search' => ['nullable', 'string', 'max:100'],
            'min' => ['nullable', 'numeric', 'min:0'],
            'max' => ['nullable', 'numeric', 'min:0', Rule::when($this->filled('min'), 'gte:min')],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
        ];
    }

    /**
     * The filters of the search: the text trimmed (null when empty), and the kind, account, amounts and days asked.
     */
    public function filters(): MovementFilters
    {
        $text = trim($this->string('search')->toString());

        return new MovementFilters(
            search: $text === '' ? null : $text,
            kind: $this->kind(),
            account: $this->account(),
            min: $this->filled('min') ? $this->float('min') : null,
            max: $this->filled('max') ? $this->float('max') : null,
            from: $this->filled('from') ? $this->string('from')->toString() : null,
            to: $this->filled('to') ? $this->string('to')->toString() : null,
        );
    }

    /**
     * A wrong month, kind, account or movement is a page that does not exist (404); a wrong filter is an error of the
     * form (422), unless a wrong address comes with it, which wins.
     *
     * @param  Validator  $validator  The validator that failed.
     *
     * @throws ValidationException if only the filters are wrong
     */
    protected function failedValidation(Validator $validator): never
    {
        if (array_diff(array_keys($validator->errors()->messages()), self::FILTER_FIELDS) !== []) {
            $this->notFound($validator);
        }

        throw new ValidationException($validator)->errorBag($this->errorBag)->redirectTo($this->getRedirectUrl());
    }

    /**
     * The kind to show, null for both.
     */
    public function kind(): ?string
    {
        return $this->filled('kind') ? $this->string('kind')->toString() : null;
    }

    /**
     * The account to show, null for all.
     */
    public function account(): ?int
    {
        return $this->filled('account') ? $this->integer('account') : null;
    }

    /**
     * The movement to open beside the list, if one is asked.
     *
     * @throws ModelNotFoundException if the family has no such movement (Laravel answers 404)
     */
    public function movement(): Expense|Income|null
    {
        if (! $this->filled('movement')) {
            return null;
        }

        [$kind, $id] = explode(':', $this->string('movement')->toString());

        return Movements::find($kind, $id);
    }
}
