<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Exceptions\Reading\ReadingSkipped;
use App\Models\File;
use App\Services\FilePreviews;
use App\Support\HeicConverter;
use GdImage;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\Base64Image;

/**
 * The file as the model reads it: a PDF or a text as they are (the text without its BOM),
 * a photo as a JPEG at most 2000 px wide, turned the right way up.
 */
final readonly class ReadingSource
{
    /** The widest a photo may be when it goes to the model, in pixels. */
    private const int WIDTH = 2000;

    /** The quality of the JPEG that goes to the model, from 0 to 100. */
    private const int QUALITY = 85;

    /** The mime types of the photos the model reads, besides HEIC. */
    private const array IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** The mime types of the HEIC photos. */
    private const array HEIC_TYPES = ['image/heic', 'image/heif'];

    /**
     * @param  string  $mediaType  `application/pdf`, `image/jpeg` or `text/plain`
     * @param  string  $data  the bytes the model gets
     */
    private function __construct(public string $mediaType, private string $data) {}

    /**
     * Checks the kind and the size of a file from its row, without opening it.
     *
     * @param  File  $file  the file
     *
     * @throws ReadingSkipped if the model does not read it
     */
    public static function check(File $file): void
    {
        $type = $file->mime_type;

        if ($type !== 'application/pdf' && $type !== 'text/plain' && ! in_array($type, [...self::IMAGE_TYPES, ...self::HEIC_TYPES], true)) {
            throw ReadingSkipped::unsupported();
        }

        if ($type === 'text/plain' && $file->size > Config::integer('document_reader.max_text_bytes')) {
            throw ReadingSkipped::textTooBig();
        }

        if ($file->size > Config::integer('document_reader.max_bytes')) {
            throw ReadingSkipped::tooBig();
        }
    }

    /**
     * Prepares a file for the model.
     *
     * @param  File  $file  the file
     *
     * @throws ReadingSkipped if the model does not read it, or the photo cannot be prepared
     */
    public static function of(File $file): self
    {
        self::check($file);

        $data = Storage::disk($file->disk)->get($file->path) ?? throw ReadingSkipped::unsupported();

        return match (true) {
            $file->mime_type === 'application/pdf' => new self('application/pdf', $data),
            $file->mime_type === 'text/plain' => new self('text/plain', str_starts_with($data, "\xEF\xBB\xBF") ? substr($data, 3) : $data),
            in_array($file->mime_type, self::HEIC_TYPES, true) => new self('image/jpeg', self::photo(self::heicToJpeg($data))),
            default => new self('image/jpeg', self::photo($data)),
        };
    }

    /**
     * What goes to the model: the photo as an image, the rest as a document.
     */
    public function attachment(): Base64Document|Base64Image
    {
        $base64 = base64_encode($this->data);

        return $this->mediaType === 'image/jpeg'
            ? new Base64Image($base64, $this->mediaType)
            : new Base64Document($base64, $this->mediaType);
    }

    /**
     * Re-encodes a photo as a JPEG at most 2000 px wide, with the rotation of its EXIF applied.
     *
     * @param  string  $bytes  the photo
     * @return string the JPEG
     *
     * @throws ReadingSkipped if the photo is not an image or does not fit in the memory of PHP
     */
    private static function photo(string $bytes): string
    {
        $size = @getimagesizefromstring($bytes);

        if ($size === false) {
            throw ReadingSkipped::unsupported();
        }

        if (! HeicConverter::fitsDimensions($size[0], $size[1], strlen($bytes))) {
            throw ReadingSkipped::tooBig();
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw ReadingSkipped::unsupported();
        }

        $orientation = $size['mime'] === 'image/jpeg' ? self::orientationOf($bytes) : 1;

        // Scaled before it is turned: the turn allocates a second bitmap, and a small one is cheap.
        // A photo on its side ends up as wide as it is tall now, so that is the side held to 2000 px.
        $turnsSide = in_array($orientation, [5, 6, 7, 8], true);
        $image = self::scaled($image, $turnsSide ? imagesy($image) : imagesx($image), $turnsSide);
        $image = self::straighten($image, $orientation);

        ob_start();
        imagejpeg($image, null, self::QUALITY);

        return (string) ob_get_clean();
    }

    /**
     * The image made smaller so that the side that ends up as its width is at most 2000 px.
     *
     * @param  GdImage  $image  the image as stored
     * @param  int  $side  the length of the side that ends up as the width
     * @param  bool  $heightIsSide  true when that side is the height of the stored image
     */
    private static function scaled(GdImage $image, int $side, bool $heightIsSide): GdImage
    {
        if ($side <= self::WIDTH) {
            return $image;
        }

        $width = $heightIsSide
            ? (int) round(imagesx($image) * self::WIDTH / $side)
            : self::WIDTH;

        return imagescale($image, max(1, $width)) ?: $image;
    }

    /**
     * The EXIF orientation of a JPEG, 1 when it has none.
     *
     * @param  string  $jpeg  the JPEG
     */
    private static function orientationOf(string $jpeg): int
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
     * Turns and flips the image so that it is the right way up, as its EXIF orientation says.
     *
     * @param  GdImage  $image  the image as stored
     * @param  int  $orientation  the EXIF orientation, 1 to 8
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

    /**
     * Converts a HEIC photo to a JPEG as wide as the model takes.
     *
     * @param  string  $heic  the HEIC photo
     * @return string the JPEG
     *
     * @throws ReadingSkipped if the photo cannot be converted
     */
    private static function heicToJpeg(string $heic): string
    {
        $source = tempnam(sys_get_temp_dir(), 'reading');
        $output = $source.'.jpg';

        try {
            file_put_contents($source, $heic);
            $made = (new HeicConverter)->toJpeg($source, $output, self::WIDTH, self::QUALITY);
            $jpeg = $made ? file_get_contents($output) : false;

            if (! $made && is_file($output) && ! HeicConverter::fitsInMemory($output)) {
                throw ReadingSkipped::tooBig();
            }
        } catch (ProcessTimedOutException) {
            $jpeg = false;
        } catch (ProcessFailedException $exception) {
            $jpeg = false;

            if (in_array($exception->result->exitCode(), FilePreviews::TOOL_MISSING, true)) {
                Log::error('[document-reader] heic tool missing or not executable');
            }
        } finally {
            Filesystem::delete([$source, $output]);
        }

        return $jpeg !== false ? $jpeg : throw ReadingSkipped::unsupported();
    }
}
