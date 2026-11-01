<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Http\Middleware\SetLocale;
use App\Models\Family;
use InvalidArgumentException;

/**
 * Turns on the family's calendar to subscribe to.
 */
final readonly class TurnOnCalendar
{
    public function __construct(private ResetCalendarLink $reset) {}

    /**
     * Gives the family a secret for the address of its calendar, with the language of its texts. A calendar already
     * on keeps its secret and its language, so the address the family already uses goes on working.
     *
     * @param  Family  $family  the family
     * @param  string  $locale  the language of the page it is turned on in, `en` or `it`
     * @return string the secret
     *
     * @throws InvalidArgumentException when the language is not one the app is translated into
     */
    public function handle(Family $family, string $locale): string
    {
        throw_unless(in_array($locale, SetLocale::LOCALES, true), InvalidArgumentException::class, "The calendar has no language {$locale}.");

        if ($family->calendar_token !== null) {
            return $family->calendar_token;
        }

        $family->forceFill(['calendar_locale' => $locale])->save();

        return $this->reset->handle($family);
    }
}
