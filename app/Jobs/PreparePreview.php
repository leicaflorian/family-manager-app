<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\File;
use App\Services\FilePreviews;
use App\Support\CurrentFamily;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Makes the preview of a file after its reading, so that it is ready when someone opens it. Kept out of the job of
 * the reading: a slow conversion there would make the queue try the paid reading again. A file deleted meanwhile
 * ends the job without a word, and a preview that fails is reported and marked by `FilePreviews`, nothing more.
 */
final class PreparePreview implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $fileId  the file
     * @param  int  $familyId  the family of the file
     */
    public function __construct(
        public readonly int $fileId,
        public readonly int $familyId,
    ) {}

    /**
     * Makes the preview, in the family of the file.
     *
     * @param  FilePreviews  $previews  makes the preview of a PDF or a HEIC
     */
    public function handle(FilePreviews $previews): void
    {
        CurrentFamily::using($this->familyId, function () use ($previews): void {
            $file = File::query()->find($this->fileId);

            if (! $file instanceof File) {
                return;
            }

            try {
                $previews->path($file);
            } catch (Throwable $error) {
                report($error);
            }
        });
    }
}
