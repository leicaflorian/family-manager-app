<?php

declare(strict_types=1);

namespace App\Services\Mailgun;

use Illuminate\Support\Facades\Cache;

/**
 * The signature Mailgun puts on every call: an HMAC-SHA256 of `timestamp` followed by `token`, made with the
 * signing key. The signature covers nothing else: the age of the `timestamp` keeps an old call out, and each token
 * is taken once (`firstUse`), so a signed call copied by someone cannot be sent again with another mail inside;
 * the token of a call that ended in a 500 is forgotten (`forget`), so that call can be sent again as it was.
 * Mailgun signs every delivery again, a delivery after a 500 too, with a new token.
 */
final class MailgunSignature
{
    /** How far from now the `timestamp` of a call can be, before or after. */
    public const int MAX_AGE_SECONDS = 86400;

    /**
     * Whether the call can be compared with its signature: the three fields are there and the `timestamp` is near.
     *
     * @param  array<string, mixed>  $fields  the fields of the call
     * @param  int  $now  the time now, in seconds
     * @return string|null null when it can; else why not (`no signature`, `no timestamp`, `timestamp too far`), only for the log
     */
    public static function check(array $fields, int $now): ?string
    {
        $token = MailgunForm::text($fields, 'token');
        $timestamp = MailgunForm::text($fields, 'timestamp');
        $signature = MailgunForm::text($fields, 'signature');

        if ($token === '' || $timestamp === '' || preg_match('/^[0-9a-f]{64}$/i', $signature) !== 1) {
            return 'no signature';
        }

        if (preg_match('/^\d+$/', $timestamp) !== 1) {
            return 'no timestamp';
        }

        return abs($now - (int) $timestamp) > self::MAX_AGE_SECONDS ? 'timestamp too far' : null;
    }

    /**
     * Whether `$signature` is the HMAC-SHA256 of `$timestamp` followed by `$token`, in hex, made with the key.
     *
     * @param  string  $key  the signing key
     * @param  string  $timestamp  the `timestamp` field
     * @param  string  $token  the `token` field
     * @param  string  $signature  the `signature` field, in any case
     */
    public static function verify(string $key, string $timestamp, string $token, string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', $timestamp.$token, $key), strtolower($signature));
    }

    /**
     * Whether the token of a verified call is used for the first time, remembering it. It is kept as long as a
     * `timestamp` can be accepted: from a day ahead to a day ago, two days. Called only after the signature is
     * verified, so that calls without the key cannot fill the cache.
     *
     * @param  string  $token  the token of the call
     */
    public static function firstUse(string $token): bool
    {
        return Cache::add(self::cacheKey($token), true, 2 * self::MAX_AGE_SECONDS);
    }

    /**
     * Forgets the token of a call that ended in a 500: nothing was saved, so the same call sent again is let in.
     *
     * @param  string  $token  the token of the call
     */
    public static function forget(string $token): void
    {
        Cache::forget(self::cacheKey($token));
    }

    /**
     * Where a token is remembered: its hash, so the cache never holds a token itself.
     *
     * @param  string  $token  the token of the call
     */
    private static function cacheKey(string $token): string
    {
        return 'mailgun-token:'.hash('sha256', $token);
    }
}
