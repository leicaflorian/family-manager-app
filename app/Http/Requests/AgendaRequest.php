<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The address of the Agenda: `?months=1|3|6|12`. Any other value is the default period, never an error.
 */
final class AgendaRequest extends FormRequest
{
    /** The periods the Agenda offers, in months. */
    public const array MONTHS = [1, 3, 6, 12];

    /** The period when the address asks for none, or for one the Agenda does not offer. */
    public const int DEFAULT_MONTHS = 1;

    /**
     * The rules of the address: none, since a wrong period falls back to the default.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The period asked, in months: 1, 3, 6 or 12, else the default.
     */
    public function months(): int
    {
        $months = $this->query('months');

        return is_string($months) && in_array($months, array_map(strval(...), self::MONTHS), true)
            ? (int) $months
            : self::DEFAULT_MONTHS;
    }
}
