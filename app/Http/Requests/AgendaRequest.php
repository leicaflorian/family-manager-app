<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\AgendaData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The address of the Agenda: `?months=1|3|6|12`. Any other value is the default period, never an error.
 */
final class AgendaRequest extends FormRequest
{
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
     * The period asked by the address when it is one the Agenda offers, else null.
     */
    public function askedMonths(): ?int
    {
        $months = $this->query('months');

        return is_string($months) && in_array($months, array_map(strval(...), AgendaData::PERIODS), true)
            ? (int) $months
            : null;
    }

    /**
     * The period to show: the one asked, the default when the address asks for another, the saved one when it asks for none.
     *
     * @param  int  $saved  the period remembered in the account
     */
    public function months(int $saved): int
    {
        return $this->query->has('months') ? ($this->askedMonths() ?? self::DEFAULT_MONTHS) : $saved;
    }
}
