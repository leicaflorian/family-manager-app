<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LinkedDevice;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\DeviceName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Linking a phone to an account with a code made on the computer, and entering the phone later.
 *
 * The code lives in the cache for ten minutes, under the sha256 of its characters, and is taken out when it is used.
 */
final class PhoneLinks
{
    /** How long a code is valid. */
    public const int CODE_MINUTES = 10;

    /** The characters of a code: no 0, 1, I and O, so it can be read aloud or typed. */
    public const string ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /** The cookie of the phone, with its secret. */
    public const string COOKIE = 'fm_device';

    /** How long the cookie lasts: the longest browsers allow. */
    public const int COOKIE_DAYS = 400;

    /** The wrong codes allowed in ten minutes from the same network address. */
    public const int MAX_WRONG = 5;

    private const int CODE_LENGTH = 8;

    /**
     * Makes a code for the account: the one it had before stops working.
     *
     * @param  User  $user  the account that links a phone
     * @return string the code, as `K7QM-XPA9`
     */
    public function newCode(User $user): string
    {
        $plain = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $plain .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        $ttl = now()->addMinutes(self::CODE_MINUTES);
        $ownerKey = 'phone-link-of:'.$user->id;
        $previous = Cache::get($ownerKey);

        if (is_string($previous)) {
            Cache::forget('phone-link:'.$previous);
        }

        $hash = hash('sha256', $plain);
        Cache::put('phone-link:'.$hash, ['user_id' => $user->id, 'family_id' => $user->family_id], $ttl);
        Cache::put($ownerKey, $hash, $ttl);

        return self::format($plain);
    }

    /**
     * Writes a code the way it is shown, in two groups.
     *
     * @param  string  $code  the code, with or without the dash
     * @return string the code in capitals as `K7QM-XPA9`, or what was given if it cannot be one
     */
    public static function format(string $code): string
    {
        $plain = self::normalize($code);

        return $plain === null ? $code : substr($plain, 0, 4).'-'.substr($plain, 4);
    }

    /**
     * Whether the text can be a code: 8 characters of the alphabet, in any case, with dashes or spaces around.
     *
     * @param  string  $code  what the person wrote
     * @return string|null the 8 characters in capitals, or null
     */
    public static function normalize(string $code): ?string
    {
        $plain = Str::upper(preg_replace('/[\s-]+/', '', $code) ?? '');

        return preg_match('/^['.self::ALPHABET.']{'.self::CODE_LENGTH.'}$/', $plain) === 1 ? $plain : null;
    }

    /**
     * Whose code it is, without using it: this is what the link shows before the person taps *Link*, so a preview
     * of the link does not burn the code.
     *
     * @param  string  $code  the code
     * @return array{user_id: int, family_id: int}|null null if it is wrong, expired or used
     */
    public function find(string $code): ?array
    {
        $plain = self::normalize($code);
        $entry = $plain === null ? null : Cache::get('phone-link:'.hash('sha256', $plain));

        return $this->valid($entry) ? $entry : null;
    }

    /**
     * Uses the code: once only. `Cache::pull` alone is a read then a delete and two requests at the same moment
     * can both read the code, so the pull runs inside a lock on the code; a lock not obtained counts as used.
     * The phone is made in the family of the code.
     *
     * @param  string  $code  the code
     * @param  Request  $request  the request of the phone, for its name
     * @return array{device: LinkedDevice, secret: string}|null the phone and its secret (kept nowhere but the
     *                                                          cookie and the page), or null if the code is wrong, expired or used
     */
    public function use(string $code, Request $request): ?array
    {
        $plain = self::normalize($code);
        if ($plain === null) {
            return null;
        }

        $hash = hash('sha256', $plain);
        $lock = Cache::lock('phone-link-use:'.$hash, 10);

        if (! $lock->get()) {
            return null;
        }

        try {
            $entry = Cache::pull('phone-link:'.$hash);
        } finally {
            $lock->release();
        }

        if (! $this->valid($entry)) {
            return null;
        }

        $user = User::query()->find($entry['user_id']);

        if (! $user instanceof User) {
            return null;
        }

        $secret = Str::random(64);
        $device = CurrentFamily::using($user->family_id, fn (): LinkedDevice => LinkedDevice::query()->create([
            'user_id' => $user->id,
            'name' => DeviceName::from($request->userAgent(), $this->firstName($user)),
            'token_hash' => hash('sha256', $secret),
            'last_used_at' => now(),
        ]));

        return ['device' => $device, 'secret' => $secret];
    }

    /**
     * Enters the account of a linked phone in this session, as after a login, and marks the phone as used.
     *
     * @param  LinkedDevice  $device  the phone
     * @param  Request  $request  the request, whose session is the phone's
     */
    public function signIn(LinkedDevice $device, Request $request): void
    {
        $user = $device->user;
        $current = Auth::user();

        if ($current instanceof User && ! $current->is($user)) {
            // Another account was signed in here: nothing of its session (confirmed password, intended url) carries over
            $request->session()->invalidate();
        }

        Auth::login($user);
        $request->session()->regenerate();
        // After the login: the `Login` event of a password clears this mark
        $request->session()->put('linked_device_id', $device->id);

        $device->forceFill(['last_used_at' => now(), 'session_id' => $request->session()->getId()])->save();
    }

    /**
     * The cookie that keeps the phone linked. It is `Secure` only when the request is, so a phone can try
     * the app over plain http on the home network.
     *
     * @param  string  $secret  the secret of the phone
     * @param  Request  $request  the request
     */
    public function cookie(string $secret, Request $request): Cookie
    {
        return Cookie::create(
            self::COOKIE,
            $secret,
            now()->addDays(self::COOKIE_DAYS),
            '/',
            null,
            $request->isSecure() || (bool) config('session.secure'),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }

    /**
     * The first name used in the name of the phone: the person tied to the account, or the first word of its name.
     *
     * @param  User  $user  the account
     */
    public function firstName(User $user): string
    {
        $person = CurrentFamily::using($user->family_id, fn () => $user->person()->first());

        return $person instanceof Person ? $person->first_name : Str::before($user->name, ' ');
    }

    /**
     * @param  mixed  $entry  what the cache holds
     *
     * @phpstan-assert-if-true array{user_id: int, family_id: int} $entry
     */
    private function valid(mixed $entry): bool
    {
        return is_array($entry) && is_int($entry['user_id'] ?? null) && is_int($entry['family_id'] ?? null);
    }
}
