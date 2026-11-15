<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The icons and colours the mails draw: the icons of `resources/design/mail-icons.json`
 * (drawn as PNG in `public/mail/icons/` by `npm run mail-icons`) and the light colours of
 * the design system, made solid because mail programs read neither CSS variables nor
 * colours with transparency.
 */
final class MailIcons
{
    /** The colours of the circles of the people, in turn, as `FmFaces` draws them. */
    public const array FACE_COLORS = ['action', 'paid', 'warning', 'ink'];

    /** @var array<string, array{lucide: string, color: string, tile: string}>|null */
    private static ?array $icons = null;

    /** @var array<string, string>|null */
    private static ?array $colors = null;

    /**
     * Every icon of the mails, by name.
     *
     * @return array<string, array{lucide: string, color: string, tile: string}>
     */
    public static function all(): array
    {
        if (self::$icons === null) {
            /** @var array<string, array{lucide: string, color: string, tile: string}> $icons */
            $icons = json_decode((string) file_get_contents(resource_path('design/mail-icons.json')), true, flags: JSON_THROW_ON_ERROR);
            self::$icons = $icons;
        }

        return self::$icons;
    }

    /**
     * The name of an icon that exists: the one asked for, or `general`.
     *
     * @param  string  $name  the icon, for example an area (`vehicles`)
     */
    public static function name(string $name): string
    {
        return array_key_exists($name, self::all()) ? $name : 'general';
    }

    /**
     * The address of the picture of an icon.
     *
     * @param  string  $name  the icon
     */
    public static function src(string $name): string
    {
        return asset('mail/icons/'.self::name($name).'.png');
    }

    /**
     * The solid colour behind an icon.
     *
     * @param  string  $name  the icon
     */
    public static function tile(string $name): string
    {
        return self::color(self::all()[self::name($name)]['tile']);
    }

    /**
     * The solid light colour of a token, as it looks on a white card.
     *
     * @param  string  $token  the colour token, for example `vehicles-soft`
     *
     * @throws \InvalidArgumentException when the token does not exist
     */
    public static function color(string $token): string
    {
        $colors = self::colors();

        if (! array_key_exists($token, $colors)) {
            throw new \InvalidArgumentException(sprintf('No colour token "%s"', $token));
        }

        return self::onWhite($colors[$token]);
    }

    /**
     * The light value of every colour token.
     *
     * @return array<string, string>
     */
    private static function colors(): array
    {
        if (self::$colors === null) {
            /** @var array{color: array{tokens: list<array{name: string, value: string|array<string, string>}>}} $tokens */
            $tokens = json_decode((string) file_get_contents(resource_path('design/tokens.json')), true, flags: JSON_THROW_ON_ERROR);
            self::$colors = [];

            foreach ($tokens['color']['tokens'] as $token) {
                self::$colors[$token['name']] = is_string($token['value']) ? $token['value'] : ($token['value']['light'] ?? '');
            }
        }

        return self::$colors;
    }

    /**
     * A `#rrggbb` or `#rrggbbaa` colour laid on white, as a solid `#rrggbb`.
     *
     * @param  string  $hex  the colour
     */
    private static function onWhite(string $hex): string
    {
        $hex = strtolower(ltrim($hex, '#'));

        if (strlen($hex) !== 8) {
            return '#'.$hex;
        }

        $alpha = hexdec(substr($hex, 6, 2)) / 255;
        $solid = '';

        foreach ([0, 2, 4] as $start) {
            $channel = hexdec(substr($hex, $start, 2));
            $solid .= sprintf('%02x', (int) round(255 - (255 - $channel) * $alpha));
        }

        return '#'.$solid;
    }
}
