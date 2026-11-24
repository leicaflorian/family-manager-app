<?php

declare(strict_types=1);

/**
 * The icons of the manifests, as drawn by `npm run app-icons`.
 *
 * @return list<array{src: string, sizes: string, type: string, purpose?: string}>
 */
function appIconsOf(string $manifest): array
{
    /** @var array{icons: list<array{src: string, sizes: string, type: string, purpose?: string}>} $data */
    $data = json_decode((string) file_get_contents(public_path($manifest)), true, flags: JSON_THROW_ON_ERROR);

    return $data['icons'];
}

/**
 * Whether the pixel of a PNG is fully opaque.
 */
function appIconsOpaqueAt(GdImage $image, int $x, int $y): bool
{
    return (imagecolorat($image, $x, $y) >> 24 & 0x7F) === 0;
}

test('every icon of the manifests is drawn, with its size, and fills the whole square', function (string $manifest): void {
    $icons = [...appIconsOf($manifest), ['src' => '/apple-touch-icon.png', 'sizes' => '180x180', 'type' => 'image/png']];

    foreach ($icons as $icon) {
        $path = public_path(ltrim($icon['src'], '/'));
        $size = getimagesize($path);

        $image = $size === false ? false : imagecreatefrompng($path);

        if ($size === false || $image === false) {
            throw new RuntimeException("Missing {$icon['src']}: run npm run app-icons");
        }

        expect("{$size[0]}x{$size[1]}")->toBe($icon['sizes']);
        $last = $size[0] - 1;

        // macOS and Android give the icon their own shape: one with transparent corners ends up shrunk on a white plate
        foreach ([[0, 0], [$last, 0], [0, $last], [$last, $last]] as [$x, $y]) {
            expect(appIconsOpaqueAt($image, $x, $y))->toBeTrue("{$icon['src']} is transparent at {$x},{$y}");
        }
    }
})->with(['manifest.webmanifest', 'manifest-ios.webmanifest']);

test('the manifests offer an icon the system can cut to its own shape', function (string $manifest): void {
    $maskable = array_values(array_filter(appIconsOf($manifest), fn (array $icon): bool => ($icon['purpose'] ?? 'any') === 'maskable'));

    expect(array_column($maskable, 'sizes'))->toContain('192x192', '512x512');
})->with(['manifest.webmanifest', 'manifest-ios.webmanifest']);
