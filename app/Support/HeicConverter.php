<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterval;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;

/**
 * Turns a HEIC photo into a JPEG with `heif-convert`, for the preview of a file and for the reading of a document.
 */
final readonly class HeicConverter
{
    /** The seconds a conversion may take, unless the constructor says otherwise. */
    public const int TIMEOUT = 20;

    /** The bytes GD needs for a pixel when it decodes a JPEG, with some room. */
    private const int BYTES_PER_PIXEL = 5;

    /** The bytes kept free for everything else when GD decodes an image. */
    private const int MEMORY_MARGIN = 8 * 1024 * 1024;

    /**
     * @param  CarbonInterval|int  $timeout  how long a conversion may take; a number is in seconds
     * @param  string  $tool  the tool that makes a JPEG of a HEIC photo
     */
    public function __construct(
        private CarbonInterval|int $timeout = self::TIMEOUT,
        private string $tool = 'heif-convert',
    ) {}

    /**
     * Writes a HEIC photo as a JPEG, at most `$width` pixels wide. The photo is
     * decoded by GD only if it fits in the memory left to PHP.
     *
     * @param  string  $source  the HEIC photo
     * @param  string  $output  where the JPEG goes
     * @param  int  $width  the widest the JPEG may be, in pixels
     * @param  int  $quality  the quality of the JPEG, from 0 to 100
     * @return bool whether the conversion worked
     *
     * @throws ProcessTimedOutException if the tool takes too long
     * @throws ProcessFailedException if the tool fails
     */
    public function toJpeg(string $source, string $output, int $width = 1200, int $quality = 80): bool
    {
        Process::timeout($this->timeout)->run([$this->tool, '-q', (string) $quality, $source, $output])->throw();

        if (! self::fitsInMemory($output)) {
            return false;
        }

        $image = @imagecreatefromjpeg($output);

        if ($image === false) {
            return false;
        }

        if (imagesx($image) > $width) {
            $image = imagescale($image, $width);
        }

        return $image !== false && imagejpeg($image, $output, $quality);
    }

    /**
     * Whether GD can decode the image with the memory PHP has left, read from its header only.
     *
     * @param  string  $image  the path of the image
     */
    public static function fitsInMemory(string $image): bool
    {
        $size = @getimagesize($image);

        return $size !== false && self::fitsDimensions($size[0], $size[1]);
    }

    /**
     * Whether GD can decode an image of this size with the memory PHP has left.
     *
     * @param  int  $width  the width in pixels
     * @param  int  $height  the height in pixels
     */
    public static function fitsDimensions(int $width, int $height): bool
    {
        $limit = ini_parse_quantity(ini_get('memory_limit'));

        return $limit <= 0
            || $limit - memory_get_usage() >= $width * $height * self::BYTES_PER_PIXEL + self::MEMORY_MARGIN;
    }
}
