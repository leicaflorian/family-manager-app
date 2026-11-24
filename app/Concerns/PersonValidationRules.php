<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The rules of the fields every form that saves a person shares.
 */
trait PersonValidationRules
{
    /**
     * The rules of name, birth date and tax code of a person.
     *
     * @param  string  $prefix  put before each key, for a person inside a list (`people.*.`)
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function personRules(string $prefix = ''): array
    {
        return [
            "{$prefix}first_name" => ['required', 'string', 'max:100'],
            "{$prefix}last_name" => ['required', 'string', 'max:100'],
            "{$prefix}birth_date" => ['nullable', 'date', 'before_or_equal:today'],
            "{$prefix}tax_code" => ['nullable', 'string', 'max:16'],
        ];
    }
}
