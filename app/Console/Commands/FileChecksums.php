<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Family;
use App\Models\File;
use App\Services\FileStore;
use App\Support\CurrentFamily;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Writes the checksum of the files that have none: the ones uploaded before the readings and the ones copied from
 * Directus without their content. Without a checksum a copy of a file is not recognised. It is safe to run again.
 */
#[Description('Write the checksum of the files that have none')]
#[Signature('files:checksums')]
final class FileChecksums extends Command
{
    /**
     * Goes through the families one by one and hashes each file without a checksum. A file missing on the disk is
     * left as it is and listed at the end.
     *
     * @param  FileStore  $store  Works out the checksum.
     */
    public function handle(FileStore $store): int
    {
        $written = 0;
        $missing = [];

        foreach (Family::query()->orderBy('id')->get(['id']) as $family) {
            CurrentFamily::using($family->id, function () use ($store, &$written, &$missing): void {
                foreach (File::query()->whereNull('checksum')->orderBy('id')->cursor() as $file) {
                    $disk = Storage::disk($file->disk);
                    $checksum = $disk->exists($file->path) ? $store->checksum($disk->path($file->path)) : null;

                    if ($checksum === null) {
                        $missing[] = "{$file->original_name} ({$file->path})";

                        continue;
                    }

                    $file->forceFill(['checksum' => $checksum])->saveQuietly();
                    $written++;
                }
            });
        }

        $this->info("Checksums written: {$written}");

        if ($missing !== []) {
            $this->warn('Missing on the disk: '.count($missing));

            foreach ($missing as $line) {
                $this->line("  {$line}");
            }
        }

        return self::SUCCESS;
    }
}
