<?php

declare(strict_types=1);

use App\Support\DeviceName;

const IPHONE_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
const ANDROID_AGENT = 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36';

test('an iPhone, an Android and anything else are named after the person, in Italian', function (): void {
    app()->setLocale('it');

    expect(DeviceName::from(IPHONE_AGENT, 'Marco'))->toBe('iPhone di Marco')
        ->and(DeviceName::from(ANDROID_AGENT, 'Laura'))->toBe('Android di Laura')
        ->and(DeviceName::from('Mozilla/5.0 (X11; Linux x86_64)', 'Marco'))->toBe('Telefono di Marco')
        ->and(DeviceName::from(null, 'Marco'))->toBe('Telefono di Marco');
});

test('the names are in English too', function (): void {
    app()->setLocale('en');

    expect(DeviceName::from(IPHONE_AGENT, 'Marco'))->toBe('iPhone of Marco')
        ->and(DeviceName::from(ANDROID_AGENT, 'Laura'))->toBe('Android of Laura')
        ->and(DeviceName::from(null, 'Marco'))->toBe('Phone of Marco');
});
