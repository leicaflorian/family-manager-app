<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Copies the family files (`families/` and `feedback/` of the private disk `local`) to the disk `backup`, the Space.
 * A manifest on the Space remembers size and modification time of every copied file, so a night only uploads what is
 * new or changed. A file removed from the app is moved to `deleted/<date>/` and kept for 30 days.
 */
final class FileBackup
{
    /** The folders of the private disk that are copied. */
    private const array ROOTS = ['families', 'feedback'];

    /** The manifest on the Space: path => {size, mtime}. */
    private const string MANIFEST = 'manifest.json';

    /** Days a deleted file stays in `deleted/`. */
    private const int KEEP_DAYS = 30;

    /**
     * Runs one backup. The manifest is written last, so a run that stops halfway uploads again the next time and
     * never forgets a file.
     *
     * @param  CarbonImmutable  $today  The day of the run, used for the `deleted/<date>/` folder and the pruning.
     * @return array{uploaded: int, kept: int, moved: int, pruned: int}
     *
     * @throws RuntimeException When a file cannot be read or the manifest is not valid.
     */
    public function run(CarbonImmutable $today): array
    {
        $local = Storage::disk('local');
        $backup = Storage::disk('backup');
        $manifest = $this->readManifest($backup);
        $uploaded = 0;
        $kept = 0;
        $moved = 0;
        $present = [];

        foreach (self::ROOTS as $root) {
            foreach ($local->allFiles($root) as $path) {
                $state = ['size' => $local->size($path), 'mtime' => $local->lastModified($path)];
                $present[$path] = true;

                if (($manifest[$path] ?? null) === $state) {
                    $kept++;

                    continue;
                }

                $this->upload($local, $backup, $path);
                $manifest[$path] = $state;
                $uploaded++;
            }
        }

        $day = $today->format('Y-m-d');

        foreach (array_keys($manifest) as $path) {
            if (isset($present[$path])) {
                continue;
            }

            if ($backup->exists($path)) {
                $backup->copy($path, "deleted/{$day}/{$path}");
                $backup->delete($path);
            }

            unset($manifest[$path]);
            $moved++;
        }

        $pruned = $this->prune($backup, $today);

        $backup->put(self::MANIFEST, json_encode($manifest, JSON_THROW_ON_ERROR));

        return ['uploaded' => $uploaded, 'kept' => $kept, 'moved' => $moved, 'pruned' => $pruned];
    }

    /**
     * Streams one file to the Space, so a big file does not fill the memory.
     *
     * @param  string  $path  The path on both disks.
     *
     * @throws RuntimeException When the file cannot be opened or written.
     */
    private function upload(Filesystem $local, Filesystem $backup, string $path): void
    {
        $stream = $local->readStream($path);

        if ($stream === null) {
            throw new RuntimeException("Cannot read {$path}");
        }

        try {
            $backup->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Reads the manifest, empty on the first run.
     *
     * @return array<string, array{size: int, mtime: int}>
     *
     * @throws RuntimeException When the manifest exists but is not valid JSON.
     */
    private function readManifest(Filesystem $backup): array
    {
        if (! $backup->exists(self::MANIFEST)) {
            return [];
        }

        $data = json_decode((string) $backup->get(self::MANIFEST), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new RuntimeException('The backup manifest is not valid');
        }

        /** @var array<string, array{size: int, mtime: int}> $data */
        return $data;
    }

    /**
     * Removes the `deleted/<date>/` folders older than 30 days.
     *
     * @return int How many folders were removed.
     */
    private function prune(Filesystem $backup, CarbonImmutable $today): int
    {
        $limit = $today->subDays(self::KEEP_DAYS)->format('Y-m-d');
        $pruned = 0;

        foreach ($backup->directories('deleted') as $directory) {
            $date = basename($directory);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && $date < $limit) {
                $backup->deleteDirectory($directory);
                $pruned++;
            }
        }

        return $pruned;
    }
}
