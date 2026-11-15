<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SuspiciousBackup;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

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
     * never forgets a file. A file that cannot be copied is reported in `failed` and the run goes on.
     *
     * @param  CarbonImmutable  $today  The day of the run, used for the `deleted/<date>/` folder and the pruning.
     * @param  bool  $force  Skips the check against a local disk that suddenly looks empty or much shorter.
     * @return array{uploaded: int, kept: int, moved: int, pruned: int, failed: list<string>}
     *
     * @throws SuspiciousBackup When too many files of the manifest are gone locally and `$force` is false.
     * @throws RuntimeException When the manifest is not valid or cannot be written.
     */
    public function run(CarbonImmutable $today, bool $force = false): array
    {
        $local = Storage::disk('local');
        $backup = Storage::disk('backup');
        $manifest = $this->readManifest($backup);
        $day = $today->format('Y-m-d');

        $present = [];

        foreach (self::ROOTS as $root) {
            foreach ($local->allFiles($root) as $path) {
                $present[$path] = true;
            }
        }

        $gone = array_values(array_filter(array_keys($manifest), fn (string $path): bool => ! isset($present[$path])));

        if (! $force) {
            $this->guard($manifest, $gone, $local->allFiles('families') !== []);
        }

        $uploaded = 0;
        $kept = 0;
        $failed = [];

        foreach (array_keys($present) as $path) {
            try {
                $state = ['size' => $local->size($path), 'mtime' => $local->lastModified($path)];
            } catch (Throwable $e) {
                // Vanished between the listing and now: not a deletion, the next run sees it as gone or back
                if (! $local->exists($path)) {
                    continue;
                }

                report($e);
                $failed[] = "{$path}: {$e->getMessage()}";

                continue;
            }

            if (($manifest[$path] ?? null) === $state) {
                $kept++;

                continue;
            }

            try {
                if (! $this->upload($local, $backup, $path, $day)) {
                    continue;
                }
            } catch (Throwable $e) {
                report($e);
                $failed[] = "{$path}: {$e->getMessage()}";

                continue;
            }

            $manifest[$path] = $state;
            $uploaded++;
        }

        $moved = 0;

        foreach ($gone as $path) {
            $this->archive($backup, $path, $day);
            $backup->delete($path);
            unset($manifest[$path]);
            $moved++;
        }

        $pruned = $this->prune($backup, $today);

        $backup->put(self::MANIFEST, json_encode($manifest, JSON_THROW_ON_ERROR));

        return ['uploaded' => $uploaded, 'kept' => $kept, 'moved' => $moved, 'pruned' => $pruned, 'failed' => $failed];
    }

    /**
     * Stops a run that would move too much: the local disk looks empty while the manifest is not, or more than half
     * of a manifest of 10 files or more is gone.
     *
     * @param  array<string, array{size: int, mtime: int}>  $manifest  What the Space knows.
     * @param  list<string>  $gone  The manifest paths missing locally.
     * @param  bool  $hasFamilies  Whether `families/` has any file.
     *
     * @throws SuspiciousBackup When the run looks wrong.
     */
    private function guard(array $manifest, array $gone, bool $hasFamilies): void
    {
        if ($manifest !== [] && ! $hasFamilies) {
            throw new SuspiciousBackup('families/ is empty but the backup is not. Nothing was moved.');
        }

        if (count($manifest) >= 10 && count($gone) * 2 > count($manifest)) {
            throw new SuspiciousBackup(sprintf('%d of %d backed up files are gone locally. Nothing was moved.', count($gone), count($manifest)));
        }
    }

    /**
     * Moves the object at `$path` on the Space to `deleted/<date>/<path>`, keeping the first copy of the day.
     *
     * @param  string  $path  The path on the Space.
     * @param  string  $day  The day folder, `Y-m-d`.
     */
    private function archive(Filesystem $backup, string $path, string $day): void
    {
        $target = "deleted/{$day}/{$path}";

        if ($backup->exists($path) && ! $backup->exists($target)) {
            $backup->copy($path, $target);
        }
    }

    /**
     * Streams one file to the Space, so a big file does not fill the memory. The object it replaces is kept under
     * `deleted/<date>/`, so a damaged local file never destroys the good copy.
     *
     * @param  Filesystem  $local  The disk the file is read from.
     * @param  Filesystem  $backup  The disk the file is written to.
     * @param  string  $path  The path on both disks.
     * @param  string  $day  The day folder for the replaced object, `Y-m-d`.
     * @return bool False when the file vanished from the local disk (nothing was done).
     *
     * @throws RuntimeException When the file exists but cannot be opened.
     */
    private function upload(Filesystem $local, Filesystem $backup, string $path, string $day): bool
    {
        $stream = $local->readStream($path);

        if ($stream === null) {
            if (! $local->exists($path)) {
                return false;
            }

            throw new RuntimeException("Cannot read {$path}");
        }

        try {
            $this->archive($backup, $path, $day);
            $backup->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return true;
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
