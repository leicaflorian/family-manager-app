<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\AgendaData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The address of the Agenda: `?months=1|3|6|12`, `?view=` and `?month=`. Any other value is the default period, never an error.
 */
final class AgendaRequest extends FormRequest
{
    /** The period when the address asks for none, or for one the Agenda does not offer. */
    public const int DEFAULT_MONTHS = 1;

    /** How many months before or after the current one the calendar accepts. */
    public const int MAX_MONTHS_AWAY = 24;

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

    /**
     * The view the address asks for, `todo`, `done` or `calendar`; null when it asks for none or for another.
     *
     * @return 'todo'|'done'|'calendar'|null
     */
    public function askedView(): ?string
    {
        return match ($this->query('view')) {
            'todo' => 'todo',
            'done' => 'done',
            'calendar' => 'calendar',
            default => null,
        };
    }

    /**
     * The month of the calendar: the first day of `?month=yyyy-mm`, the month of today when it is missing, wrong
     * or more than `MAX_MONTHS_AWAY` months before or after the month of today (a far month would make the calendar
     * look through years of due dates).
     *
     * @param  CarbonImmutable  $today  today in Rome
     */
    public function month(CarbonImmutable $today): CarbonImmutable
    {
        $month = $this->query('month');

        if (is_string($month) && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month) === 1) {
            $asked = CarbonImmutable::parse($month.'-01', 'Europe/Rome')->startOfDay();

            if (abs(($asked->year - $today->year) * 12 + $asked->month - $today->month) <= self::MAX_MONTHS_AWAY) {
                return $asked;
            }
        }

        return $today->startOfMonth();
    }

    /**
     * The page of the done rows asked by `?done_page=`, 1 when it is missing, empty or not a number.
     */
    public function donePage(): int
    {
        return max(1, $this->integer('done_page'));
    }
}
