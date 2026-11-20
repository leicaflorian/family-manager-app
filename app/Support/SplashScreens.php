<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The splash screens of iPhone and iPad: what iOS shows while the app opened from the icon on the Home screen
 * loads. Android draws its own from the manifest; iOS wants one picture for every screen size, in the
 * `apple-touch-startup-image` links of the head. The screens are listed in `resources/design/splash-screens.json`
 * and the pictures are drawn by `npm run splash-screens` into `public/splash/`, with the same names as here.
 */
final class SplashScreens
{
    /** The themes of the pictures, as the media query of the system names them. */
    private const array THEMES = ['light', 'dark'];

    /** @var list<array{width: int, height: int, ratio: int, devices: string}>|null */
    private static ?array $screens = null;

    /**
     * The screens that get a splash screen: width and height in points, portrait, and pixel ratio.
     *
     * @return list<array{width: int, height: int, ratio: int, devices: string}>
     */
    public static function screens(): array
    {
        if (self::$screens === null) {
            /** @var array{screens: list<array{width: int, height: int, ratio: int, devices: string}>} $data */
            $data = json_decode((string) file_get_contents(resource_path('design/splash-screens.json')), true, flags: JSON_THROW_ON_ERROR);
            self::$screens = $data['screens'];
        }

        return self::$screens;
    }

    /**
     * The links of the head: one picture for every screen, orientation and theme.
     *
     * @return list<array{href: string, media: string, width: int, height: int}>
     */
    public static function links(): array
    {
        $links = [];

        foreach (self::screens() as $screen) {
            foreach (['portrait', 'landscape'] as $orientation) {
                // iOS keeps device-width and device-height of the portrait screen in both orientations
                [$width, $height] = $orientation === 'portrait'
                    ? [$screen['width'] * $screen['ratio'], $screen['height'] * $screen['ratio']]
                    : [$screen['height'] * $screen['ratio'], $screen['width'] * $screen['ratio']];

                foreach (self::THEMES as $theme) {
                    $links[] = [
                        'href' => "/splash/{$theme}-{$width}x{$height}.png",
                        'media' => "(device-width: {$screen['width']}px) and (device-height: {$screen['height']}px) and (-webkit-device-pixel-ratio: {$screen['ratio']}) and (orientation: {$orientation}) and (prefers-color-scheme: {$theme})",
                        'width' => $width,
                        'height' => $height,
                    ];
                }
            }
        }

        return $links;
    }
}
