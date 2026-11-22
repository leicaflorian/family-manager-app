<?php

declare(strict_types=1);

namespace App\Support;

use GdImage;

/**
 * The EXIF orientation of a photo, and the image turned the right way up: GD drops the EXIF of a photo it writes
 * again, so a photo taken on its side would end up on its side. Used by the reading of a document and by the preview
 * of a photo.
 */
final class PhotoOrientation
{
    /**
     * The EXIF orientation of a JPEG, 1 when it has none.
     *
     * @param  string  $jpeg  the bytes of the JPEG
     * @return int the orientation, 1 to 8
     */
    public static function of(string $jpeg): int
    {
        $stream = fopen('php://memory', 'r+');

        if ($stream === false) {
            return 1;
        }

        fwrite($stream, $jpeg);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        return is_array($exif) && is_int($exif['Orientation'] ?? null) ? $exif['Orientation'] : 1;
    }

    /**
     * The image at most `$width` pixels wide and the right way up. It is scaled before it is turned: the turn
     * allocates a second bitmap, and a small one is cheap. A photo on its side ends up as wide as it is tall now,
     * so that is the side held to `$width`.
     *
     * @param  GdImage  $image  the image as stored
     * @param  int  $orientation  the EXIF orientation, 1 to 8
     * @param  int  $width  the widest the image may end up, in pixels
     * @return GdImage the image, smaller if needed and the right way up
     */
    public static function upright(GdImage $image, int $orientation, int $width): GdImage
    {
        $heightIsSide = in_array($orientation, [5, 6, 7, 8], true);
        $side = $heightIsSide ? imagesy($image) : imagesx($image);

        if ($side > $width) {
            $scaled = $heightIsSide ? (int) round(imagesx($image) * $width / $side) : $width;
            $image = imagescale($image, max(1, $scaled)) ?: $image;
        }

        return self::straighten($image, $orientation);
    }

    /**
     * Turns and flips the image so that it is the right way up, as its EXIF orientation says.
     *
     * @param  GdImage  $image  the image as stored
     * @param  int  $orientation  the EXIF orientation, 1 to 8
     * @return GdImage the image the right way up
     */
    private static function straighten(GdImage $image, int $orientation): GdImage
    {
        $turned = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6, 5 => imagerotate($image, -90, 0),
            8, 7 => imagerotate($image, 90, 0),
            default => $image,
        } ?: $image;

        match ($orientation) {
            2, 5, 7 => imageflip($turned, IMG_FLIP_HORIZONTAL),
            4 => imageflip($turned, IMG_FLIP_VERTICAL),
            default => null,
        };

        return $turned;
    }
}
