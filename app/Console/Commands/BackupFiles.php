<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\SuspiciousBackup;
use App\Services\FileBackup;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Copies the family files to the Space (disk `backup`). Without a configured bucket it only warns. The reasons of a
 * failed run also go to the log: the output of the scheduled run is thrown away, the log is what Coolify shows.
 */
#[Description('Copy the family files to the Space')]
#[Signature('files:backup {--force : Move the files that are gone locally even when they are many}')]
final class BackupFiles extends Command
{
    /**
     * Runs the backup and prints the four counts. Exits 1 when the run looks suspicious or a file failed.
     *
     * @param  FileBackup  $backup  Does the copy.
     */
    public function handle(FileBackup $backup): int
    {
        if (blank(config('filesystems.disks.backup.bucket'))) {
            $this->warn('No bucket configured (BACKUP_S3_BUCKET): nothing copied.');

            return self::SUCCESS;
        }

        try {
            $result = $backup->run(CarbonImmutable::now('Europe/Rome'), (bool) $this->option('force'));
        } catch (SuspiciousBackup $e) {
            $message = $e->getMessage().' Use --force if the files really are gone.';
            $this->error($message);
            Log::error('files:backup stopped: '.$message);

            return self::FAILURE;
        }

        $this->info("Uploaded: {$result['uploaded']}");
        $this->info("Kept: {$result['kept']}");
        $this->info("Moved to deleted: {$result['moved']}");
        $this->info("Pruned: {$result['pruned']}");

        foreach ($result['failed'] as $line) {
            $this->error("Failed: {$line}");
        }

        if ($result['failed'] !== []) {
            Log::error('files:backup failed for '.count($result['failed']).' files: '.implode('; ', $result['failed']));
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
