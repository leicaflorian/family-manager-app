<?php

declare(strict_types=1);

use App\Support\DeviceName;

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
