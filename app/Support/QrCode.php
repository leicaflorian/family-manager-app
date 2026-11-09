<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * A QR code drawn on the server, as an SVG the page shows.
 */
final class QrCode
{
    /**
     * Draws the text as a QR code.
     *
     * @param  string  $text  what the code holds, as an address
     * @param  int  $size  the side of the drawing in pixels
     * @return string the SVG, made only of the paths of the squares
     */
    public static function svg(string $text, int $size = 200): string
    {
        return new Writer(new ImageRenderer(new RendererStyle($size, 0), new SvgImageBackEnd))->writeString($text);
    }
}
