<?php

declare(strict_types=1);

use App\Services\Mailgun\MailgunSignature;

const MAILGUN_KEY = 'test-signing-key';
const MAILGUN_NOW = 1_800_000_000;
const MAILGUN_SIGNATURE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

/**
 * Signs a timestamp and a token the way Mailgun does.
 */
function mailgunSign(string $key, string $timestamp, string $token): string
{
    return hash_hmac('sha256', $timestamp.$token, $key);
}

/**
 * The three fields of a signed call, with the timestamp given.
 *
 * @return array{token: string, timestamp: string, signature: string}
 */
function mailgunSigned(int $timestamp): array
{
    return ['token' => 'tok-123', 'timestamp' => (string) $timestamp, 'signature' => MAILGUN_SIGNATURE];
}

describe('verify', function (): void {
    $timestamp = '1800000000';
    $token = 'tok-123';

    test('a signature made with the key is accepted', function () use ($timestamp, $token): void {
        expect(MailgunSignature::verify(MAILGUN_KEY, $timestamp, $token, mailgunSign(MAILGUN_KEY, $timestamp, $token)))->toBeTrue();
    });

    test('an upper case hex signature is accepted', function () use ($timestamp, $token): void {
        expect(MailgunSignature::verify(MAILGUN_KEY, $timestamp, $token, strtoupper(mailgunSign(MAILGUN_KEY, $timestamp, $token))))->toBeTrue();
    });

    test('a wrong signature is refused', function () use ($timestamp, $token): void {
        expect(MailgunSignature::verify(MAILGUN_KEY, $timestamp, $token, str_repeat('b', 64)))->toBeFalse();
    });

    test('a signature made with another key is refused', function () use ($timestamp, $token): void {
        expect(MailgunSignature::verify(MAILGUN_KEY, $timestamp, $token, mailgunSign('other-key', $timestamp, $token)))->toBeFalse();
    });

    test('a signature that differs from the real one only in the last digit is refused', function () use ($timestamp, $token): void {
        $real = mailgunSign(MAILGUN_KEY, $timestamp, $token);
        $last = str_ends_with($real, '0') ? '1' : '0';

        expect(MailgunSignature::verify(MAILGUN_KEY, $timestamp, $token, substr($real, 0, 63).$last))->toBeFalse();
    });

    test('a signature of another token or timestamp is refused', function () use ($timestamp, $token): void {
        expect(MailgunSignature::verify(MAILGUN_KEY, $timestamp, $token, mailgunSign(MAILGUN_KEY, $timestamp, 'tok-999')))->toBeFalse()
            ->and(MailgunSignature::verify(MAILGUN_KEY, $timestamp, $token, mailgunSign(MAILGUN_KEY, '1800000001', $token)))->toBeFalse();
    });

    test('a signature of a different length is refused', function () use ($timestamp, $token): void {
        $real = mailgunSign(MAILGUN_KEY, $timestamp, $token);

        expect(MailgunSignature::verify(MAILGUN_KEY, $timestamp, $token, substr($real, 0, 62)))->toBeFalse()
            ->and(MailgunSignature::verify(MAILGUN_KEY, $timestamp, $token, $real.'00'))->toBeFalse()
            ->and(MailgunSignature::verify(MAILGUN_KEY, $timestamp, $token, ''))->toBeFalse();
    });

    test('a signature that is not hex is refused', function () use ($timestamp, $token): void {
        expect(MailgunSignature::verify(MAILGUN_KEY, $timestamp, $token, str_repeat('z', 64)))->toBeFalse();
    });
});

describe('check', function (): void {
    test('a call within the window is accepted', function (): void {
        expect(MailgunSignature::check(mailgunSigned(MAILGUN_NOW - 23 * 3600), MAILGUN_NOW))->toBeNull();
    });

    test('a timestamp 24 hours and 1 second before now is refused', function (): void {
        expect(MailgunSignature::check(mailgunSigned(MAILGUN_NOW - 24 * 3600 - 1), MAILGUN_NOW))->toBe('timestamp too far');
    });

    test('a timestamp exactly 24 hours before now is still accepted', function (): void {
        expect(MailgunSignature::MAX_AGE_SECONDS)->toBe(86400)
            ->and(MailgunSignature::check(mailgunSigned(MAILGUN_NOW - 24 * 3600), MAILGUN_NOW))->toBeNull();
    });

    test('a timestamp in the future is judged by the same limit', function (): void {
        expect(MailgunSignature::check(mailgunSigned(MAILGUN_NOW + 3600), MAILGUN_NOW))->toBeNull()
            ->and(MailgunSignature::check(mailgunSigned(MAILGUN_NOW + 24 * 3600), MAILGUN_NOW))->toBeNull()
            ->and(MailgunSignature::check(mailgunSigned(MAILGUN_NOW + 24 * 3600 + 1), MAILGUN_NOW))->toBe('timestamp too far');
    });

    test('missing fields are refused', function (): void {
        expect(MailgunSignature::check(['timestamp' => (string) MAILGUN_NOW, 'signature' => MAILGUN_SIGNATURE], MAILGUN_NOW))->toBe('no signature')
            ->and(MailgunSignature::check(['token' => 'tok-123', 'signature' => MAILGUN_SIGNATURE], MAILGUN_NOW))->toBe('no signature')
            ->and(MailgunSignature::check(['token' => 'tok-123', 'timestamp' => (string) MAILGUN_NOW], MAILGUN_NOW))->toBe('no signature')
            ->and(MailgunSignature::check([], MAILGUN_NOW))->toBe('no signature');
    });

    test('a signature that is not 64 hex digits is refused', function (): void {
        expect(MailgunSignature::check(['token' => 'tok-123', 'timestamp' => (string) MAILGUN_NOW, 'signature' => 'abc'], MAILGUN_NOW))->toBe('no signature')
            ->and(MailgunSignature::check(['token' => 'tok-123', 'timestamp' => (string) MAILGUN_NOW, 'signature' => str_repeat('g', 64)], MAILGUN_NOW))->toBe('no signature');
    });

    test('a timestamp that is not digits is refused', function (): void {
        expect(MailgunSignature::check(['token' => 'tok-123', 'timestamp' => '17e8', 'signature' => MAILGUN_SIGNATURE], MAILGUN_NOW))->toBe('no timestamp')
            ->and(MailgunSignature::check(['token' => 'tok-123', 'timestamp' => '-5', 'signature' => MAILGUN_SIGNATURE], MAILGUN_NOW))->toBe('no timestamp');
    });
});
