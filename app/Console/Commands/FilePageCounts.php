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
use Illuminate\Support\Facades\Storage;

/**
 * Writes the page count of the PDFs that have none: the ones uploaded before the count was kept. A broken PDF or a
 * file missing on the disk stays without a count. It is safe to run again.
 */
#[Description('Write the page count of the PDFs that have none')]
#[Signature('files:page-counts')]
final class FilePageCounts extends Command
{
    /**
     * Goes through the families one by one, in chunks of 100 files, and reads the pages of each PDF without a count.
     *
     * @param  FilePreviews  $previews  Reads the pages.
     */
    public function handle(FilePreviews $previews): int
    {
        $written = 0;

        foreach (Family::query()->orderBy('id')->get(['id']) as $family) {
            CurrentFamily::using($family->id, function () use ($previews, &$written): void {
                File::query()
                    ->where('mime_type', 'application/pdf')
                    ->whereNull('page_count')
                    ->chunkById(100, function ($files) use ($previews, &$written): void {
                        foreach ($files as $file) {
                            if (! Storage::disk($file->disk)->exists($file->path)) {
                                continue;
                            }

                            $pages = $previews->pageCount($file);

                            if ($pages !== null) {
                                $file->forceFill(['page_count' => $pages])->saveQuietly();
                                $written++;
                            }
                        }
                    });
            });
        }

        $this->info("Page counts written: {$written}");

        return self::SUCCESS;
    }
}
