<?php

declare(strict_types=1);

namespace App\Services\Mailgun;

/**
 * The signature Mailgun puts on every call: an HMAC-SHA256 of `timestamp` followed by `token`, made with the
 * signing key. The signature covers nothing else, so the age of the `timestamp` is the only thing that keeps an
 * old call from being sent again.
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
}
