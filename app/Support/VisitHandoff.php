<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\LimitVisit;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * The one-time link of *Enter as…*: the panel and the app live on two hosts with two sessions, so the panel cannot
 * sign the owner into the app. The link carries a random token kept in the cache for {@see LimitVisit::LINK_SECONDS}
 * seconds, which the app trades once for the owner who made it, and a signature that ties it to the account.
 */
final class VisitHandoff
{
    /** The prefix of the cache key of a token. */
    private const string KEY = 'visit-handoff:';

    /**
     * Makes the link for the owner to enter as an account, on the host of the app ({@see config('app.url')}) even
     * when the panel makes it on its own host.
     *
     * @param  Owner  $owner  the owner who clicked *Enter as*
     * @param  User  $user  the account to look as
     * @return string the absolute, signed link, valid for {@see LimitVisit::LINK_SECONDS} seconds
     */
    public static function link(Owner $owner, User $user): string
    {
        $token = Str::random(40);

        Cache::put(self::KEY.$token, ['owner_id' => $owner->id, 'user_id' => $user->id], LimitVisit::LINK_SECONDS);

        return self::onAppHost()->temporarySignedRoute(
            'support.visit.start',
            now()->addSeconds(LimitVisit::LINK_SECONDS),
            ['user' => $user->id, 'token' => $token],
        );
    }

    /**
     * Trades a token for the owner who made the link, once: the token is gone after the first call, whatever the answer.
     *
     * @param  string  $token  the token of the link
     * @param  int  $userId  the account in the link
     * @return Owner|null the owner, or null when the token is unknown, expired, used or made for another account
     */
    public static function consume(string $token, int $userId): ?Owner
    {
        $entry = Cache::pull(self::KEY.$token);

        if (! is_array($entry) || ($entry['user_id'] ?? null) !== $userId || ! is_int($entry['owner_id'] ?? null)) {
            return null;
        }

        return Owner::query()->find($entry['owner_id']);
    }

    /**
     * A copy of the URL generator with the root and the scheme of the app, so the generator of the request (the
     * panel's host) is left as it was.
     */
    private static function onAppHost(): UrlGenerator
    {
        $root = config()->string('app.url');
        $current = URL::getFacadeRoot();
        assert($current instanceof UrlGenerator);
        $generator = clone $current;

        $generator->forceRootUrl($root);
        $generator->forceScheme((string) parse_url($root, PHP_URL_SCHEME));

        return $generator;
    }
}
