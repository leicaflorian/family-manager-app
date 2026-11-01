<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Models\Family;
use Illuminate\Support\Str;

/**
 * Changes the address of the family's calendar: the old one stops working at once.
 */
final class ResetCalendarLink
{
    /**
     * Gives the family a new secret. It is kept encrypted, to show it again, and as its sha256, to find the family
     * from the address: never in clear.
     *
     * @param  Family  $family  the family
     * @return string the new secret, 40 letters and digits
     */
    public function handle(Family $family): string
    {
        $token = Str::random(40);

        $family->forceFill([
            'calendar_token' => $token,
            'calendar_token_hash' => hash('sha256', $token),
        ])->save();

        return $token;
    }
}
