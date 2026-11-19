<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Http\Requests\Concerns\ReadsMonth;
use App\Http\Requests\Concerns\ReadsSearchFilters;
use App\Rules\OfFamily;
use App\Support\MovementFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * The address of *Movements*: `?month=2026-10&kind=expense&account=3`. Whatever is not a month,
 * a kind, or an account of the family is a wrong address: it answers 404, as a wrong id does. The filters of
 * the search (`search`, `min`, `max`, `from`, `to`) are typed by the person, so a wrong one is an error to correct (422).
 */
final class MovementIndexRequest extends FormRequest
{
    use ReadsMonth {
        failedValidation as private notFound;
    }
    use ReadsSearchFilters;

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
            ...$this->searchFilterRules(),
        ];
    }

    /**
     * The filters of the search: the text trimmed (null when empty), and the kind, account, amounts and days asked.
     */
    public function filters(): MovementFilters
    {
        return new MovementFilters(
            search: $this->searchText(),
            kind: $this->kind(),
            account: $this->account(),
            min: $this->filterAmount('min'),
            max: $this->filterAmount('max'),
            from: $this->filterDay('from'),
            to: $this->filterDay('to'),
        );
    }

    /**
     * A wrong month, kind or account is a page that does not exist (404); a wrong filter is an error of the
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
}
