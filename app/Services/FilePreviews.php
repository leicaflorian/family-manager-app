<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\File;
use App\Support\HeicConverter;
use App\Support\PhotoOrientation;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * The preview of a file: a JPEG of the first page of a PDF, of a HEIC photo, or of a JPEG photo made smaller, so
 * that a grid of pictures does not download every photo whole. A PNG or a WebP is shown as it is: it may be
 * transparent (a logo, a drawing on no background), and a JPEG has no transparency.
 *
 * It is made the first time it is asked, with Ghostscript (`gs`), `heif-convert` and GD,
 * and kept next to the file in `families/<family_id>/previews/`. A file that
 * cannot be converted is marked, so the tools do not run on it again.
 */
final readonly class FilePreviews
{
    /** Makes the JPEG of a HEIC photo. */
    private HeicConverter $heic;

    /** The photos the browser opens, made smaller with GD: only JPEG, which has no transparency to lose. */
    private const array PHOTO_TYPES = ['image/jpeg'];

    /** The mime types a preview can be made for. */
    public const array TYPES = ['application/pdf', 'image/heic', 'image/heif', ...self::PHOTO_TYPES];

    /** The width of the preview, in pixels. */
    private const int WIDTH = 1200;

    /** The quality of the JPEG, from 0 to 100. */
    private const int QUALITY = 80;

    /** The seconds a conversion may take, unless the constructor says otherwise. */
    public const int TIMEOUT = 20;

    /** The most conversions one preview runs one after the other (a PDF: the size probe and the render). */
    private const int PASSES = 2;

    /** The seconds the lock of a file lasts beyond the timeout of a conversion. */
    private const int LOCK_MARGIN = 10;

    /** The exit codes of a shell for a tool that is not executable or not found. */
    public const array TOOL_MISSING = [126, 127];

    /** The most pages `page_count` holds (an unsigned small integer): a bigger number is not believed. */
    private const int MAX_PAGES = 65535;

    /** The seconds a request waits for the lock of a file. */
    private const int LOCK_WAIT = 15;

    /**
     * @param  CarbonInterval|int  $timeout  how long a conversion may take; a number is in seconds
     * @param  string  $pdfTool  the tool that makes the image of a PDF
     * @param  string  $heicTool  the tool that makes a JPEG of a HEIC photo
     * @param  string  $pdfInfoTool  the tool that tells how many pages a PDF has
     */
    public function __construct(
        private CarbonInterval|int $timeout = self::TIMEOUT,
        private string $pdfTool = 'gs',
        string $heicTool = 'heif-convert',
        private string $pdfInfoTool = 'pdfinfo',
    ) {
        $this->heic = new HeicConverter($timeout, $heicTool);
    }

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
            Cache::lock("file-preview:{$file->id}", $this->lockSeconds())
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

        $context = ['file' => $file->id];

        if ($file->mime_type === 'application/pdf') {
            $pages = $this->pageCount($file);

            if ($pages !== null) {
                $file->forceFill(['page_count' => $pages])->saveQuietly();
            }
        }

        try {
            $made = match (true) {
                $file->mime_type === 'application/pdf' => $this->fromPdf($disk->path($file->path), $output, $context),
                in_array($file->mime_type, self::PHOTO_TYPES, true) => $this->fromPhoto($disk->path($file->path), $output),
                default => $this->heic->toJpeg($disk->path($file->path), $output, self::WIDTH, self::QUALITY),
            };
        } catch (ProcessTimedOutException) {
            $made = false;
        } catch (ProcessFailedException $exception) {
            $made = false;
            $context = [...$context, ...$this->describe($exception)];

            if (in_array($exception->result->exitCode(), self::TOOL_MISSING, true)) {
                $this->deleteLeftovers($file);
                Log::error('[file-preview] tool missing or not executable', $context);

                return null;
            }
        }

        if (! $made || ! is_file($output)) {
            $this->deleteLeftovers($file);
            $file->forceFill(['preview_failed' => true])->save();
            Log::warning('[file-preview] conversion failed', $context);

            return null;
        }

        $this->deleteLeftovers($file, keepPreview: true);
        $file->forceFill(['preview_path' => $relative])->save();

        if (! File::withoutGlobalScopes()->whereKey($file->id)->exists()) {
            $disk->delete($relative);

            return null;
        }

        return $relative;
    }

    /**
     * How many pages a PDF has, read with `pdfinfo`. A tool that is missing is logged; a broken PDF, a timeout or an
     * answer without pages is just no number.
     *
     * @param  File  $file  the file
     * @return int|null the pages; null for a file that is not a PDF, when the tool fails or finds none, or when it
     *                  says more than the column holds
     */
    public function pageCount(File $file): ?int
    {
        if ($file->mime_type !== 'application/pdf') {
            return null;
        }

        try {
            $result = Process::timeout($this->timeout)
                ->run([$this->pdfInfoTool, Storage::disk($file->disk)->path($file->path)])
                ->throw();
        } catch (ProcessTimedOutException) {
            return null;
        } catch (ProcessFailedException $exception) {
            if (in_array($exception->result->exitCode(), self::TOOL_MISSING, true)) {
                Log::error('[file-preview] tool missing or not executable', ['file' => $file->id, ...$this->describe($exception)]);
            }

            return null;
        }

        $pages = preg_match('/^Pages:\s+(\d+)\s*$/m', $result->output(), $match) === 1 ? (int) $match[1] : 0;

        return $pages > 0 && $pages <= self::MAX_PAGES ? $pages : null;
    }

    /**
     * Deletes the files the tools wrote for a file: the preview itself, unless asked to keep it,
     * and the extra images (`<uuid>-*`) they leave next to it.
     *
     * @param  File  $file  the file
     * @param  bool  $keepPreview  true to keep the JPEG of the preview
     */
    private function deleteLeftovers(File $file, bool $keepPreview = false): void
    {
        $preview = $this->previewPathOf($file);
        $disk = Storage::disk($file->disk);
        $extra = array_filter(
            $disk->files(dirname($preview)),
            fn (string $path): bool => str_starts_with(basename($path), pathinfo($preview, PATHINFO_FILENAME).'-'),
        );

        $disk->delete($keepPreview ? array_values($extra) : [...$extra, $preview]);
    }

    /**
     * Deletes the preview of a file and every other file the tools wrote with its name.
     *
     * @param  File  $file  the file
     */
    public function deleteOf(File $file): void
    {
        $disk = Storage::disk($file->disk);
        $name = pathinfo($file->path, PATHINFO_FILENAME);

        $disk->delete(array_values(array_filter(
            $disk->files(dirname($this->previewPathOf($file))),
            fn (string $path): bool => str_starts_with(basename($path), $name),
        )));
    }

    /**
     * The exit code and the message of a tool that failed, for the log.
     *
     * @param  ProcessFailedException  $exception  the failure
     * @return array{exit_code: int|null, error: string}
     */
    private function describe(ProcessFailedException $exception): array
    {
        return [
            'exit_code' => $exception->result->exitCode(),
            'error' => mb_substr(trim($exception->result->errorOutput()), 0, 500),
        ];
    }

    /**
     * The seconds the lock of a file lasts: the timeout of every pass of a conversion and some more.
     */
    private function lockSeconds(): int
    {
        $seconds = $this->timeout instanceof CarbonInterval ? (int) ceil($this->timeout->totalSeconds) : $this->timeout;

        return $seconds * self::PASSES + self::LOCK_MARGIN;
    }

    /**
     * Writes the first page of a PDF as a JPEG, `WIDTH` px wide.
     *
     * Ghostscript is used because poppler draws the white of a scanner page (a CalGray image with a D65 white
     * point) pinkish. The page size is read first with a 72 dpi render (one pixel per point, crop box and rotation
     * included), then the page is drawn at the resolution that makes it `WIDTH` wide. Fit-to-page options are not
     * used: they ignore `/Rotate` for 90 and 180 degrees.
     *
     * @param  string  $source  the PDF
     * @param  string  $output  where the JPEG goes
     * @param  array<string, mixed>  $context  the log context, to which the reason of a failure without an exit code is added
     * @return bool whether the tool finished well
     *
     * @throws ProcessTimedOutException if the tool takes too long
     * @throws ProcessFailedException if the tool fails
     */
    private function fromPdf(string $source, string $output, array &$context): bool
    {
        $probe = $output.'-probe.jpg';
        $options = [
            $this->pdfTool, '-q', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dUseCropBox', '-sDEVICE=jpeg',
            '-dFirstPage=1', '-dLastPage=1',
        ];

        try {
            $result = Process::timeout($this->timeout)->run([...$options, '-r72', '-sOutputFile='.$probe, $source])->throw();
            $size = is_file($probe) ? getimagesize($probe) : false;
        } finally {
            if (is_file($probe)) {
                Filesystem::delete($probe);
            }
        }

        if ($size === false || $size[0] < 1) {
            $context['error'] = mb_substr(trim($result->errorOutput().' '.$result->output()), 0, 500);

            return false;
        }

        Process::timeout($this->timeout)->run([
            ...$options, '-dJPEGQ='.self::QUALITY, '-r'.round(72 * self::WIDTH / $size[0], 3), '-sOutputFile='.$output, $source,
        ])->throw();

        return true;
    }

    /**
     * Writes a JPEG photo as a JPEG at most 1200 px wide, the right way up. The photo is decoded by GD only if it fits
     * in the memory left to PHP.
     *
     * @param  string  $source  the photo
     * @param  string  $output  where the JPEG goes
     * @return bool whether the JPEG was written
     */
    private function fromPhoto(string $source, string $output): bool
    {
        $bytes = @file_get_contents($source);
        $size = $bytes === false ? false : @getimagesizefromstring($bytes);

        if ($bytes === false || $size === false || ! HeicConverter::fitsDimensions($size[0], $size[1], strlen($bytes))) {
            return false;
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return false;
        }

        $orientation = PhotoOrientation::of($bytes);
        unset($bytes);

        return imagejpeg(PhotoOrientation::upright($image, $orientation, self::WIDTH), $output, self::QUALITY);
    }
}
