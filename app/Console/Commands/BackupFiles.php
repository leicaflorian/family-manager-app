<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FileBackup;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Copies the family files to the Space (disk `backup`). Without a configured bucket it only warns.
 */
#[Description('Copy the family files to the Space')]
#[Signature('files:backup')]
final class BackupFiles extends Command
{
    /**
     * Runs the backup and prints the four counts.
     *
     * @param  FileBackup  $backup  Does the copy.
     */
    public function handle(FileBackup $backup): int
    {
        if (blank(config('filesystems.disks.backup.bucket'))) {
            $this->warn('No bucket configured (BACKUP_S3_BUCKET): nothing copied.');

            return self::SUCCESS;
        }

        $result = $backup->run(CarbonImmutable::now('Europe/Rome'));

        $this->info("Uploaded: {$result['uploaded']}");
        $this->info("Kept: {$result['kept']}");
        $this->info("Moved to deleted: {$result['moved']}");
        $this->info("Pruned: {$result['pruned']}");

        return self::SUCCESS;
    }
}
