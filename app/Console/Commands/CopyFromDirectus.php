<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Copy\Copier;
use App\Copy\CopyRefused;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Copies the family of Directus into the app: `directus:copy --family="Leica"`.
 */
#[Description('Copy the family of Directus into the app')]
#[Signature('directus:copy
        {--family= : The name of the family in the app}
        {--replace : Delete the family of that name, with its files, and make it again}
        {--without-files : Write the rows of the files without copying their content}')]
final class CopyFromDirectus extends Command
{
    /**
     * Runs the copy and prints what it did.
     */
    public function handle(Copier $copier): int
    {
        $family = $this->option('family');

        if (! is_string($family) || trim($family) === '') {
            $this->error('Write the name of the family: --family="Leica".');

            return self::FAILURE;
        }

        try {
            $report = $copier->copy(trim($family), (bool) $this->option('replace'), ! $this->option('without-files'));
        } catch (CopyRefused $refused) {
            $this->error($refused->getMessage());

            return self::FAILURE;
        }

        $this->table(['Directus', 'Rows'], array_map(null, array_keys($report->rows), array_values($report->rows)));

        foreach ($report->ignoredColumns as $table => $fields) {
            $this->line("Not copied, the app has no column for them: {$table}: ".implode(', ', $fields));
        }

        foreach ($report->leftOut as $table => $count) {
            $this->line("Left out, a row they need is missing: {$table}: {$count}");
        }

        if ($report->withoutAccount !== []) {
            $this->warn('Copied without an account, their Directus account has no email: '.implode(', ', $report->withoutAccount));
        }

        if ($report->missingFiles !== []) {
            $this->warn('Files missing in the uploads (the row is there, the content is not): '.count($report->missingFiles));

            foreach ($report->missingFiles as $file) {
                $this->line("  {$file}");
            }
        }

        $this->info(sprintf('Done in %.1f s.', $report->seconds));

        return self::SUCCESS;
    }
}
