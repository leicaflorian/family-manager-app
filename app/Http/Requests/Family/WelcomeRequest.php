<?php

declare(strict_types=1);

namespace App\Http\Requests\Family;

use App\Enums\PersonRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The two steps of the *Welcome*: who you are (the people to add) and the home,
 * which can be left for later.
 */
final class WelcomeRequest extends FormRequest
{
    /**
     * The rules of the fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'people' => ['present', 'array', 'max:20'],
            'people.*.first_name' => ['required', 'string', 'max:255'],
            'people.*.last_name' => ['required', 'string', 'max:255'],
            'people.*.role' => ['required', Rule::enum(PersonRole::class)],
            'home' => ['nullable', 'array'],
            'home.name' => ['required_with:home', 'string', 'max:255'],
            'home.address' => ['nullable', 'string', 'max:255'],
            'home.city' => ['nullable', 'string', 'max:255'],
            'home.is_owned' => ['boolean'],
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
            'people.*.first_name' => __('first name'),
            'people.*.last_name' => __('last name'),
            'people.*.role' => __('in the family they are'),
            'home.name' => __('what you call it'),
            'home.address' => __('address'),
            'home.city' => __('city'),
        ];
    }
}
