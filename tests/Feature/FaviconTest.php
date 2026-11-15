<?php

declare(strict_types=1);

test('the svg favicon is the Fameja.app icon', function (): void {
    $svg = (string) file_get_contents(public_path('favicon.svg'));

    expect($svg)->toContain('#3399FF')
        ->not->toContain('#FF2D20');
});

test('the ico favicon has the three sizes of the browser tabs', function (): void {
    $ico = new Imagick(public_path('favicon.ico'));
    $sizes = [];

    foreach ($ico as $frame) {
        $sizes[] = $frame->getImageWidth();
    }
    sort($sizes);

    expect($sizes)->toBe([16, 32, 48]);
});
