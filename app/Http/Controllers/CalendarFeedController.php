<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Family;
use App\Services\CalendarFeed;
use App\Support\CurrentFamily;
use App\Support\Ics\Calendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Traits\Localizable;

/**
 * The family's calendar to subscribe to, read by Google, Apple and Outlook
 * without signing in: the secret in the address is the access.
 */
final class CalendarFeedController
{
    use Localizable;

    /**
     * The calendar of the family with this secret, in the language it was turned on in. Every wrong case (a secret
     * that does not exist, an old one, a calendar turned off) is the same 404, so from outside nothing tells them apart.
     *
     * @param  CalendarFeed  $feed  the events
     * @param  string  $token  the secret
     */
    public function __invoke(CalendarFeed $feed, string $token): Response
    {
        // The one read of `families` by a secret: it finds the family, then everything goes through its scope
        $family = Family::query()->where('calendar_token_hash', hash('sha256', $token))->first();

        // A suspended family is the same 404 as a secret that does not exist
        abort_unless($family instanceof Family && ! $family->isBlocked(), 404);

        $ics = $this->withLocale($family->calendar_locale ?? config()->string('app.locale'), fn (): string => CurrentFamily::using(
            $family->id,
            fn (): string => Calendar::render("Family Manager · {$family->name}", $feed->events(CarbonImmutable::today('Europe/Rome'))),
        ));

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
