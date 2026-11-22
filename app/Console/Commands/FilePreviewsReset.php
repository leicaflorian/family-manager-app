<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Family;
use App\Models\File;
use App\Services\FilePreviews;
use App\Support\CurrentFamily;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Throws away the saved previews of the PDF files, so the next view makes them again. Use it after the way
 * previews are drawn changes. It is safe to run again.
 */
#[Description('Delete the saved previews of the PDF files, so they are made again on the first view')]
#[Signature('files:previews {--reset-pdf : Delete the previews of the PDF files}')]
final class FilePreviewsReset extends Command
{
    /**
     * Goes through the families one by one: deletes the preview image of each PDF and clears its preview columns.
     *
     * @param  FilePreviews  $previews  Knows where the previews are and deletes them.
     */
    public function handle(FilePreviews $previews): int
    {
        if (! $this->option('reset-pdf')) {
            $this->error('Nothing to do: pass --reset-pdf.');

            return self::FAILURE;
        }

        $reset = 0;

        foreach (Family::query()->orderBy('id')->get(['id']) as $family) {
            CurrentFamily::using($family->id, function () use ($previews, &$reset): void {
                foreach (File::query()->where('mime_type', 'application/pdf')->orderBy('id')->cursor() as $file) {
                    $previews->deleteOf($file);
                    $file->forceFill(['preview_path' => null, 'preview_failed' => false])->saveQuietly();
                    $reset++;
                }
            });
        }

        $this->info("Previews reset: {$reset}");

        return self::SUCCESS;
    }
}
