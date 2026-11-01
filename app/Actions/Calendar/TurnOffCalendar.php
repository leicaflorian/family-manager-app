<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Models\Family;

/**
 * Turns off the family's calendar to subscribe to.
 */
final class TurnOffCalendar
{
    /**
     * Forgets the secret and the language: the address answers 404 from now on, and turning it on again gives
     * another one.
     *
     * @param  Family  $family  the family
     */
    public function handle(Family $family): void
    {
        $family->forceFill([
            'calendar_token' => null,
            'calendar_token_hash' => null,
            'calendar_locale' => null,
        ])->save();
    }
}
