<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\File;
use App\Support\HeicConverter;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Process\Exceptions\ProcessFailedException;
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
    /** Makes the JPEG of a HEIC photo. */
    private HeicConverter $heic;

    /** The mime types a preview can be made for. */
    public const array TYPES = ['application/pdf', 'image/heic', 'image/heif'];

    /** The width of the preview, in pixels. */
    private const int WIDTH = 1200;

    /** The quality of the JPEG, from 0 to 100. */
    private const int QUALITY = 80;

    /** The seconds a conversion may take, unless the constructor says otherwise. */
    public const int TIMEOUT = 20;

    /** The seconds the lock of a file lasts beyond the timeout of a conversion. */
    private const int LOCK_MARGIN = 10;

    /** The exit codes of a shell for a tool that is not executable or not found. */
    public const array TOOL_MISSING = [126, 127];

    /** The seconds a request waits for the lock of a file. */
    private const int LOCK_WAIT = 15;

    /**
     * @param  CarbonInterval|int  $timeout  how long a conversion may take; a number is in seconds
     * @param  string  $pdfTool  the tool that makes the image of a PDF
     * @param  string  $heicTool  the tool that makes a JPEG of a HEIC photo
     */
    public function __construct(
        private CarbonInterval|int $timeout = self::TIMEOUT,
        private string $pdfTool = 'pdftoppm',
        string $heicTool = 'heif-convert',
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

        try {
            $made = $file->mime_type === 'application/pdf'
                ? $this->fromPdf($disk->path($file->path), $output)
                : $this->heic->toJpeg($disk->path($file->path), $output, self::WIDTH, self::QUALITY);
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
     * The seconds the lock of a file lasts: the timeout of a conversion and some more.
     */
    private function lockSeconds(): int
    {
        $seconds = $this->timeout instanceof CarbonInterval ? (int) ceil($this->timeout->totalSeconds) : $this->timeout;

        return $seconds + self::LOCK_MARGIN;
    }

    /**
     * Writes the first page of a PDF as a JPEG.
     *
     * @param  string  $source  the PDF
     * @param  string  $output  where the JPEG goes
     * @return bool whether the tool finished well
     *
     * @throws ProcessTimedOutException if the tool takes too long
     * @throws ProcessFailedException if the tool fails
     */
    private function fromPdf(string $source, string $output): bool
    {
        Process::timeout($this->timeout)->run([
            $this->pdfTool, '-f', '1', '-l', '1', '-singlefile', '-jpeg', '-jpegopt', 'quality='.self::QUALITY,
            '-scale-to-x', (string) self::WIDTH, '-scale-to-y', '-1', $source, substr($output, 0, -4),
        ])->throw();

        return true;
    }
}
