<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\File;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * The preview of a file: a JPEG of the first page of a PDF, or of a HEIC photo.
 *
 * It is made the first time it is asked, with `pdftoppm` and `heif-convert`,
 * and kept next to the file in `families/<family_id>/previews/`. A file that
 * cannot be converted is marked, so the tools do not run on it again.
 */
final readonly class FilePreviews
{
    /** The mime types a preview can be made for. */
    public const array TYPES = ['application/pdf', 'image/heic', 'image/heif'];

    /** The width of the preview, in pixels. */
    private const int WIDTH = 1200;

    /** The quality of the JPEG, from 0 to 100. */
    private const int QUALITY = 80;

    /** The seconds a conversion may take, unless the constructor says otherwise. */
    public const int TIMEOUT = 20;

    /** The seconds the lock of a file is kept, at most. */
    private const int LOCK_SECONDS = 20;

    /** The seconds a request waits for the lock of a file. */
    private const int LOCK_WAIT = 15;

    /**
     * @param  CarbonInterval|int  $timeout  how long a conversion may take; a number is in seconds
     */
    public function __construct(private CarbonInterval|int $timeout = self::TIMEOUT) {}

    /**
     * The path of the preview of a file on its disk, where it is made and where it is deleted.
     *
     * @param  File  $file  the file
     */
    public function previewPathOf(File $file): string
    {
        return "families/{$file->family_id}/previews/".pathinfo($file->path, PATHINFO_FILENAME).'.jpg';
    }

    /**
     * The path of the preview on the disk of the file, made if it is missing.
     *
     * @param  File  $file  the file to preview
     * @return string|null the path of the JPEG; null if the type has no preview or the image cannot be made
     */
    public function path(File $file): ?string
    {
        if (! in_array($file->mime_type, self::TYPES, true) || $file->preview_failed) {
            return null;
        }

        if ($this->exists($file)) {
            return $file->preview_path;
        }

        $path = null;

        try {
            Cache::lock("file-preview:{$file->id}", self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT, function () use ($file, &$path): void {
                    $path = $this->make($file);
                });

            return $path;
        } catch (LockTimeoutException) {
            Log::warning('[file-preview] lock not taken', ['file' => $file->id]);

            return null;
        }
    }

    /**
     * Whether the preview of the file is saved and still on the disk.
     *
     * @param  File  $file  the file
     */
    private function exists(File $file): bool
    {
        return $file->preview_path !== null && Storage::disk($file->disk)->exists($file->preview_path);
    }

    /**
     * Makes the preview, once the lock is taken.
     *
     * @param  File  $file  the file to preview
     * @return string|null the path of the JPEG, null if it could not be made
     */
    private function make(File $file): ?string
    {
        $file->refresh();

        if ($file->preview_failed) {
            return null;
        }

        if ($this->exists($file)) {
            return $file->preview_path;
        }

        $disk = Storage::disk($file->disk);
        $relative = $this->previewPathOf($file);
        $output = $disk->path($relative);
        $disk->makeDirectory(dirname($relative));

        try {
            $made = $file->mime_type === 'application/pdf'
                ? $this->fromPdf($disk->path($file->path), $output)
                : $this->fromHeic($disk->path($file->path), $output);
        } catch (ProcessTimedOutException) {
            $made = false;
        }

        if (! $made || ! is_file($output)) {
            $disk->delete($relative);
            $file->forceFill(['preview_failed' => true])->save();
            Log::warning('[file-preview] conversion failed', ['file' => $file->id]);

            return null;
        }

        $file->forceFill(['preview_path' => $relative])->save();

        return $relative;
    }

    /**
     * Writes the first page of a PDF as a JPEG.
     *
     * @param  string  $source  the PDF
     * @param  string  $output  where the JPEG goes
     * @return bool whether the tool finished well
     *
     * @throws ProcessTimedOutException if the tool takes too long
     */
    private function fromPdf(string $source, string $output): bool
    {
        return Process::timeout($this->timeout)->run([
            'pdftoppm', '-f', '1', '-l', '1', '-singlefile', '-jpeg', '-jpegopt', 'quality='.self::QUALITY,
            '-scale-to-x', (string) self::WIDTH, '-scale-to-y', '-1', $source, substr($output, 0, -4),
        ])->successful();
    }

    /**
     * Writes a HEIC photo as a JPEG, at most as wide as the preview.
     *
     * @param  string  $source  the HEIC photo
     * @param  string  $output  where the JPEG goes
     * @return bool whether the conversion worked
     *
     * @throws ProcessTimedOutException if the tool takes too long
     */
    private function fromHeic(string $source, string $output): bool
    {
        if (! Process::timeout($this->timeout)->run(['heif-convert', '-q', (string) self::QUALITY, $source, $output])->successful()) {
            return false;
        }

        $image = @imagecreatefromjpeg($output);

        if ($image === false) {
            return false;
        }

        if (imagesx($image) > self::WIDTH) {
            $image = imagescale($image, self::WIDTH);
        }

        return $image !== false && imagejpeg($image, $output, self::QUALITY);
    }
}
