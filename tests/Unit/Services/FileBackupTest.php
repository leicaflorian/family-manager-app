<?php

declare(strict_types=1);

use App\Exceptions\SuspiciousBackup;
use App\Services\FileBackup;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PathPrefixer;

beforeEach(function (): void {
    Storage::fake('backup');
    Storage::disk('local')->put('families/1/a.pdf', 'aaa');
    Storage::disk('local')->put('families/1/b.pdf', 'bbbb');
    Storage::disk('local')->put('feedback/1/c.png', 'cc');
});

it('uploads new files', function (): void {
    $result = resolve(FileBackup::class)->run(CarbonImmutable::parse('2026-11-15'));

    expect($result)->toBe(['uploaded' => 3, 'kept' => 0, 'moved' => 0, 'pruned' => 0, 'failed' => []]);
    expect(Storage::disk('backup')->get('families/1/a.pdf'))->toBe('aaa');
    expect(Storage::disk('backup')->get('families/1/b.pdf'))->toBe('bbbb');
    expect(Storage::disk('backup')->get('feedback/1/c.png'))->toBe('cc');
});

it('skips unchanged files', function (): void {
    $backup = resolve(FileBackup::class);
    $backup->run(CarbonImmutable::parse('2026-11-15'));

    expect($backup->run(CarbonImmutable::parse('2026-11-16')))->toBe(['uploaded' => 0, 'kept' => 3, 'moved' => 0, 'pruned' => 0, 'failed' => []]);
});

it('uploads a changed file again', function (): void {
    $backup = resolve(FileBackup::class);
    $backup->run(CarbonImmutable::parse('2026-11-15'));
    Storage::disk('local')->put('families/1/a.pdf', 'a longer content');

    $result = $backup->run(CarbonImmutable::parse('2026-11-16'));

    expect($result['uploaded'])->toBe(1)->and($result['kept'])->toBe(2);
    expect(Storage::disk('backup')->get('families/1/a.pdf'))->toBe('a longer content');
});

it('keeps a deleted file for 30 days', function (): void {
    $backup = resolve(FileBackup::class);
    $backup->run(CarbonImmutable::parse('2026-11-01'));
    Storage::disk('local')->delete('families/1/a.pdf');

    $first = $backup->run(CarbonImmutable::parse('2026-11-02'));

    expect($first['moved'])->toBe(1);
    expect(Storage::disk('backup')->exists('families/1/a.pdf'))->toBeFalse();
    expect(Storage::disk('backup')->get('deleted/2026-11-02/families/1/a.pdf'))->toBe('aaa');

    $backup->run(CarbonImmutable::parse('2026-12-02'));
    expect(Storage::disk('backup')->exists('deleted/2026-11-02/families/1/a.pdf'))->toBeTrue();

    $last = $backup->run(CarbonImmutable::parse('2026-12-03'));
    expect($last['pruned'])->toBe(1);
    expect(Storage::disk('backup')->exists('deleted/2026-11-02/families/1/a.pdf'))->toBeFalse();
});

it('ignores files outside families and feedback', function (): void {
    Storage::disk('local')->put('backups/dump.sql', 'x');
    Storage::disk('local')->put('other/file.txt', 'x');

    $result = resolve(FileBackup::class)->run(CarbonImmutable::parse('2026-11-15'));

    expect($result['uploaded'])->toBe(3);
    expect(Storage::disk('backup')->exists('backups/dump.sql'))->toBeFalse();
    expect(Storage::disk('backup')->exists('other/file.txt'))->toBeFalse();
});

/** Puts ten files on both disks and backs them up on 2026-11-01. */
function tenFilesBackedUp(): FileBackup
{
    foreach (range(1, 7) as $n) {
        Storage::disk('local')->put("families/2/f{$n}.pdf", "file {$n}");
    }
    $backup = resolve(FileBackup::class);
    $backup->run(CarbonImmutable::parse('2026-11-01'));

    return $backup;
}

it('refuses to move files when families is empty', function (): void {
    $backup = resolve(FileBackup::class);
    $backup->run(CarbonImmutable::parse('2026-11-01'));
    Storage::disk('local')->deleteDirectory('families');
    Storage::disk('local')->deleteDirectory('feedback');
    $manifest = Storage::disk('backup')->get('manifest.json');

    expect(fn () => $backup->run(CarbonImmutable::parse('2026-11-02')))->toThrow(SuspiciousBackup::class);
    expect(Storage::disk('backup')->get('manifest.json'))->toBe($manifest);
    expect(Storage::disk('backup')->exists('families/1/a.pdf'))->toBeTrue();
    expect(Storage::disk('backup')->exists('deleted'))->toBeFalse();
});

it('refuses to move more than half of ten or more files', function (): void {
    $backup = tenFilesBackedUp();
    foreach (range(1, 6) as $n) {
        Storage::disk('local')->delete("families/2/f{$n}.pdf");
    }

    expect(fn () => $backup->run(CarbonImmutable::parse('2026-11-02')))->toThrow(SuspiciousBackup::class);
    expect(Storage::disk('backup')->exists('families/2/f1.pdf'))->toBeTrue();
});

it('moves up to half of the files normally', function (): void {
    $backup = tenFilesBackedUp();
    foreach (range(1, 4) as $n) {
        Storage::disk('local')->delete("families/2/f{$n}.pdf");
    }

    expect($backup->run(CarbonImmutable::parse('2026-11-02'))['moved'])->toBe(4);
    expect(Storage::disk('backup')->exists('deleted/2026-11-02/families/2/f1.pdf'))->toBeTrue();
});

it('moves the files anyway when forced', function (): void {
    $backup = tenFilesBackedUp();
    foreach (range(1, 6) as $n) {
        Storage::disk('local')->delete("families/2/f{$n}.pdf");
    }

    expect($backup->run(CarbonImmutable::parse('2026-11-02'), force: true)['moved'])->toBe(6);
    expect(Storage::disk('backup')->get('deleted/2026-11-02/families/2/f1.pdf'))->toBe('file 1');
});

it('keeps the old copy of a changed file for 30 days', function (): void {
    $backup = resolve(FileBackup::class);
    $backup->run(CarbonImmutable::parse('2026-11-01'));
    Storage::disk('local')->put('families/1/a.pdf', 'truncated!');

    $backup->run(CarbonImmutable::parse('2026-11-02'));

    expect(Storage::disk('backup')->get('families/1/a.pdf'))->toBe('truncated!');
    expect(Storage::disk('backup')->get('deleted/2026-11-02/families/1/a.pdf'))->toBe('aaa');

    // Changed again the same day: the first old copy stays
    Storage::disk('local')->put('families/1/a.pdf', 'changed once more');
    $backup->run(CarbonImmutable::parse('2026-11-02'));
    expect(Storage::disk('backup')->get('deleted/2026-11-02/families/1/a.pdf'))->toBe('aaa');

    expect($backup->run(CarbonImmutable::parse('2026-12-03'))['pruned'])->toBe(1);
    expect(Storage::disk('backup')->exists('deleted/2026-11-02/families/1/a.pdf'))->toBeFalse();
});

it('skips a file that vanished before it was read', function (): void {
    $root = Storage::disk('local')->path('');
    $vanishing = new class($root) extends LocalFilesystemAdapter
    {
        public function fileSize(string $path): FileAttributes
        {
            if ($path === 'families/1/b.pdf') {
                unlink($this->prefixer()->prefixPath($path));
            }

            return parent::fileSize($path);
        }

        private function prefixer(): PathPrefixer
        {
            return new PathPrefixer(Storage::disk('local')->path(''));
        }
    };
    Storage::set('local', new FilesystemAdapter(new Filesystem($vanishing), $vanishing, ['root' => $root, 'throw' => false]));

    $result = resolve(FileBackup::class)->run(CarbonImmutable::parse('2026-11-15'));

    expect($result['uploaded'])->toBe(2)->and($result['failed'])->toBe([]);
    expect(Storage::disk('backup')->exists('families/1/b.pdf'))->toBeFalse();
});

it('reports a file that cannot be uploaded and still saves the others', function (): void {
    $root = Storage::disk('backup')->path('');
    $failing = new class($root) extends LocalFilesystemAdapter
    {
        public static int $writes = 0;

        public static bool $fail = true;

        public function writeStream(string $path, $contents, Config $config): void
        {
            if (self::$fail && ++self::$writes === 2) {
                throw new RuntimeException('Space unreachable');
            }

            parent::writeStream($path, $contents, $config);
        }
    };
    Storage::set('backup', new FilesystemAdapter(new Filesystem($failing), $failing, ['root' => $root]));

    $result = resolve(FileBackup::class)->run(CarbonImmutable::parse('2026-11-15'));

    expect($result['uploaded'])->toBe(2)->and($result['failed'])->toHaveCount(1);
    expect(Storage::disk('backup')->exists('manifest.json'))->toBeTrue();

    $failing::$fail = false;
    $next = resolve(FileBackup::class)->run(CarbonImmutable::parse('2026-11-16'));

    expect($next['uploaded'])->toBe(1)->and($next['kept'])->toBe(2)->and($next['failed'])->toBe([]);
});
