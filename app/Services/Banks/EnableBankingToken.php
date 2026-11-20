<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Exceptions\Banks\BankFeedFailed;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * The JWT Enable Banking wants in the `Authorization` header: RS256, signed with the private key of the application,
 * valid one hour and kept 50 minutes so one token serves many calls.
 */
final class EnableBankingToken
{
    private const string CACHE_KEY = 'bank_feed.enable_banking.token';

    private const int LIFETIME = 3600;

    private const int KEPT = 3000;

    /**
     * The token for the moment given: the cached one while it is under 50 minutes old, else a new one.
     *
     * @param  CarbonImmutable  $now  the moment of the call
     *
     * @throws BankFeedFailed when the key cannot be read or signs nothing
     */
    public function make(CarbonImmutable $now): string
    {
        // The key of the cache follows the application and its key, so a rotated one never reuses an old token
        $cacheKey = self::CACHE_KEY.':'.sha1(self::setting('app_id').'|'.self::setting('key_path'));
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && is_string($cached['token'] ?? null) && is_int($cached['at'] ?? null)
            && $now->getTimestamp() - $cached['at'] < self::KEPT && $now->getTimestamp() >= $cached['at']) {
            return $cached['token'];
        }

        $token = $this->sign($now);
        Cache::put($cacheKey, ['token' => $token, 'at' => $now->getTimestamp()], self::LIFETIME);

        return $token;
    }

    /**
     * A text setting of Enable Banking, empty when it is not set.
     *
     * @param  string  $key  the key under `bank_feed.enable_banking`
     */
    public static function setting(string $key): string
    {
        $value = Config::get('bank_feed.enable_banking.'.$key);

        return is_string($value) ? $value : '';
    }

    /**
     * The private key of the application as a full path, when it is set and can be read. A relative path counts from
     * the root of the app: PHP-FPM runs from `public/` and the worker from the root, and both must find the same file.
     */
    public static function keyPath(): ?string
    {
        $path = self::setting('key_path');

        if ($path === '') {
            return null;
        }

        $full = str_starts_with($path, '/') ? $path : base_path($path);

        return is_readable($full) ? $full : null;
    }

    /**
     * Builds and signs a token.
     *
     * @param  CarbonImmutable  $now  the issue time
     *
     * @throws BankFeedFailed
     */
    private function sign(CarbonImmutable $now): string
    {
        $path = self::keyPath();
        $pem = $path !== null ? file_get_contents($path) : false;

        if ($pem === false) {
            throw new BankFeedFailed('The key of the bank provider cannot be read.');
        }

        $header = $this->encode(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => self::setting('app_id')]);
        $body = $this->encode([
            'iss' => 'enablebanking.com',
            'aud' => 'api.enablebanking.com',
            'iat' => $now->getTimestamp(),
            'exp' => $now->getTimestamp() + self::LIFETIME,
        ]);

        if (! openssl_sign($header.'.'.$body, $signature, $pem, OPENSSL_ALGO_SHA256)) {
            throw new BankFeedFailed('The key of the bank provider cannot sign.');
        }

        return $header.'.'.$body.'.'.$this->base64($signature);
    }

    /**
     * The base64url of a JSON object.
     *
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        return $this->base64(json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * The base64url, without padding, of a string.
     */
    private function base64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
