<?php

declare(strict_types=1);

use App\Services\Banks\EnableBankingToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Makes a key pair in a temporary file and points the config to it.
 *
 * @return array{public: string, path: string}
 */
function bankTokenKeyPair(): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $private);
    $path = tempnam(sys_get_temp_dir(), 'eb-key');
    file_put_contents($path, $private);
    config(['bank_feed.enable_banking.app_id' => 'app-123', 'bank_feed.enable_banking.key_path' => $path]);

    return ['public' => openssl_pkey_get_details($key)['key'], 'path' => $path];
}

/** Decodes a base64url part. */
function bankTokenPart(string $part): string
{
    return base64_decode(strtr($part, '-_', '+/'));
}

it('signs the token with the application key', function (): void {
    ['public' => $public, 'path' => $path] = bankTokenKeyPair();
    Cache::flush();
    $now = CarbonImmutable::parse('2026-11-20 10:00:00', 'UTC');

    $jwt = (new EnableBankingToken)->make($now);
    [$header, $body, $signature] = explode('.', $jwt);

    expect(json_decode(bankTokenPart($header), true))->toBe(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => 'app-123'])
        ->and(json_decode(bankTokenPart($body), true))->toBe([
            'iss' => 'enablebanking.com',
            'aud' => 'api.enablebanking.com',
            'iat' => $now->getTimestamp(),
            'exp' => $now->getTimestamp() + 3600,
        ])
        ->and(openssl_verify($header.'.'.$body, bankTokenPart($signature), $public, OPENSSL_ALGO_SHA256))->toBe(1);

    File::delete($path);
});

it('keeps the token in the cache for 50 minutes', function (): void {
    ['path' => $path] = bankTokenKeyPair();
    Cache::flush();
    $now = CarbonImmutable::parse('2026-11-20 10:00:00', 'UTC');

    $first = (new EnableBankingToken)->make($now);
    $again = (new EnableBankingToken)->make($now->addMinutes(49));
    $later = (new EnableBankingToken)->make($now->addMinutes(51));

    expect($again)->toBe($first)->and($later)->not->toBe($first);

    File::delete($path);
});

it('reads a relative key path from the root of the app, wherever the process runs', function (): void {
    ['path' => $temporary] = bankTokenKeyPair();
    $name = 'storage/framework/testing/eb-key-'.bin2hex(random_bytes(4)).'.pem';
    File::ensureDirectoryExists(dirname(base_path($name)));
    File::move($temporary, base_path($name));
    config(['bank_feed.enable_banking.key_path' => $name]);
    Cache::flush();
    $before = getcwd();
    // PHP-FPM runs from public/, the worker from the root: a relative path must mean the same in both
    chdir(public_path());

    try {
        expect(EnableBankingToken::keyPath())->toBe(base_path($name))
            ->and((new EnableBankingToken)->make(CarbonImmutable::parse('2026-11-20 10:00:00', 'UTC')))->toContain('.');
    } finally {
        chdir((string) $before);
        File::delete(base_path($name));
    }
});

it('has no key path when the file is missing or not set', function (): void {
    config(['bank_feed.enable_banking.key_path' => null]);
    expect(EnableBankingToken::keyPath())->toBeNull();

    config(['bank_feed.enable_banking.key_path' => '/nowhere/enable-banking.pem']);
    expect(EnableBankingToken::keyPath())->toBeNull();
});

it('makes a new token when the application id changes', function (): void {
    ['path' => $path] = bankTokenKeyPair();
    Cache::flush();
    $now = CarbonImmutable::parse('2026-11-20 10:00:00', 'UTC');

    $first = (new EnableBankingToken)->make($now);
    config(['bank_feed.enable_banking.app_id' => 'app-456']);
    $second = (new EnableBankingToken)->make($now->addMinutes(5));

    expect(json_decode(bankTokenPart(explode('.', $second)[0]), true)['kid'])->toBe('app-456')
        ->and($second)->not->toBe($first);

    File::delete($path);
});
