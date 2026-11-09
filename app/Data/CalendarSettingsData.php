<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Family;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * *Settings → Calendar*: whether the family's calendar to subscribe to is on, and its address.
 */
#[TypeScript]
final class CalendarSettingsData extends Data
{
    /**
     * @param  bool  $on  whether the calendar is on
     * @param  string|null  $url  the whole address to subscribe to; null while it is off, when the secret cannot be read any more, or during a visit of the owner
     * @param  bool  $hidden  whether the address is kept out of the page because the owner is visiting
     * @param  bool  $can_change  whether who looks can turn it on, off or change the address (only an administrator)
     */
    public function __construct(
        public readonly bool $on,
        public readonly ?string $url,
        public readonly bool $can_change,
        public readonly bool $hidden = false,
    ) {}

    /**
     * Builds it from the family and who looks.
     *
     * A secret that cannot be decrypted (the `APP_KEY` changed) is not an error of the page: `calendarSecret()` gives
     * null, so the calendar stays on without an address, and an administrator can change it to get a working one.
     *
     * During a visit the address stays out of the page: it would let the owner read the dates after the visit.
     *
     * @param  Family  $family  the family
     * @param  User  $user  whoever signed in
     * @param  bool  $hidden  whether the owner is visiting
     */
    public static function fromFamily(Family $family, User $user, bool $hidden = false): self
    {
        $token = $hidden ? null : $family->calendarSecret();

        return new self(
            on: $family->calendar_token_hash !== null,
            url: $token === null ? null : route('calendar.feed', ['token' => $token]),
            can_change: $user->can('manageCalendar', $family),
            hidden: $hidden,
        );
    }
}
