<?php

declare(strict_types=1);

use App\Support\SplashScreens;

test('every screen of iPhone and iPad has its picture in the head, in both orientations and both themes', function (): void {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)->toContain(
        '<link rel="apple-touch-startup-image" href="/splash/light-1179x2556.png" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait) and (prefers-color-scheme: light)">',
    )->and($html)->toContain(
        '<link rel="apple-touch-startup-image" href="/splash/dark-2556x1179.png" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape) and (prefers-color-scheme: dark)">',
    )->and(substr_count((string) $html, 'apple-touch-startup-image'))->toBe(count(SplashScreens::screens()) * 4);
});

test('every picture is drawn, with the pixels of its screen', function (): void {
    foreach (SplashScreens::links() as $link) {
        $path = public_path(ltrim($link['href'], '/'));
        $size = getimagesize($path);

        expect($size)->not->toBeFalse("Missing {$link['href']}: run npm run splash-screens")
            ->and([$size[0] ?? null, $size[1] ?? null])->toBe([$link['width'], $link['height']]);
    }
});

test('the list has the screens of today, each once', function (): void {
    $screens = SplashScreens::screens();
    $keys = array_map(fn (array $screen): string => "{$screen['width']}x{$screen['height']}@{$screen['ratio']}", $screens);

    expect($keys)->toContain('393x852@3', '402x874@3', '440x956@3', '1032x1376@2')
        ->and(array_unique($keys))->toHaveCount(count($keys));
});
