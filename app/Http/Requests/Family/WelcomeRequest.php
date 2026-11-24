<?php

declare(strict_types=1);

namespace App\Http\Requests\Family;

use App\Concerns\PersonValidationRules;
use App\Enums\PersonRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * The *Welcome*: who you are (your birth date and tax code, and the people to add), the home, which can be left
 * for later, and the switches of the Budget and of the mail for the bills.
 */
final class WelcomeRequest extends FormRequest
{
    use PersonValidationRules;

    /**
     * The rules of the fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'me' => ['nullable', 'array'],
            ...Arr::only($this->personRules('me.'), ['me.birth_date', 'me.tax_code']),
            'people' => ['present', 'array', 'max:20'],
            ...$this->personRules('people.*.'),
            'people.*.role' => ['required', Rule::enum(PersonRole::class)],
            'home' => ['nullable', 'array'],
            'home.name' => ['required_with:home', 'string', 'max:255'],
            'home.address' => ['nullable', 'string', 'max:255'],
            'home.city' => ['nullable', 'string', 'max:255'],
            'home.is_owned' => ['boolean'],
            'features' => ['nullable', 'array'],
            'features.budget' => ['required_with:features', 'boolean'],
            'features.mail' => ['required_with:features', 'boolean'],
        ];
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'me.birth_date' => __('birth date'),
            'me.tax_code' => __('tax code'),
            'people.*.first_name' => __('first name'),
            'people.*.birth_date' => __('birth date'),
            'people.*.tax_code' => __('tax code'),
            'people.*.last_name' => __('last name'),
            'people.*.role' => __('in the family they are'),
            'home.name' => __('what you call it'),
            'home.address' => __('address'),
            'home.city' => __('city'),
        ];
    }
}
