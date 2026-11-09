<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The name a linked phone gets, read from the browser: nobody is asked for it.
 */
final class DeviceName
{
    /**
     * *iPhone of Marco*, *Android of Laura*, or *Phone of Marco* when the browser does not say.
     *
     * @param  string|null  $userAgent  the `User-Agent` of the phone
     * @param  string  $firstName  the first name of the account
     */
    public static function from(?string $userAgent, string $firstName): string
    {
        return match (true) {
            $userAgent !== null && str_contains($userAgent, 'iPhone') => __('iPhone of :name', ['name' => $firstName]),
            $userAgent !== null && str_contains($userAgent, 'Android') => __('Android of :name', ['name' => $firstName]),
            default => __('Phone of :name', ['name' => $firstName]),
        };
    }
}
