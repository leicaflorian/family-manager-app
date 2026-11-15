<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\SuspiciousBackup;
use App\Services\FileBackup;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Copies the family files to the Space (disk `backup`). Without a configured bucket it only warns.
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
            $this->error($e->getMessage().' Use --force if the files really are gone.');

            return self::FAILURE;
        }

        $this->info("Uploaded: {$result['uploaded']}");
        $this->info("Kept: {$result['kept']}");
        $this->info("Moved to deleted: {$result['moved']}");
        $this->info("Pruned: {$result['pruned']}");

        foreach ($result['failed'] as $line) {
            $this->error("Failed: {$line}");
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
