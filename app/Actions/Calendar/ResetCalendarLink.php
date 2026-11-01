<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Models\Family;
use Illuminate\Support\Facades\Crypt;
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

        // Written by the query, not by `save()`: saving compares the new secret with the old one, decrypting it, and an
        // old secret that cannot be decrypted any more (the `APP_KEY` changed) is exactly when it has to be replaced.
        Family::query()->whereKey($family->getKey())->update([
            'calendar_token' => Crypt::encryptString($token),
            'calendar_token_hash' => hash('sha256', $token),
        ]);
        $family->refresh();

        return $token;
    }
}
