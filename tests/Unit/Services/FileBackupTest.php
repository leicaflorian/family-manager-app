<?php

declare(strict_types=1);

use App\Services\FileBackup;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

beforeEach(function (): void {
    Storage::fake('backup');
    Storage::disk('local')->put('families/1/a.pdf', 'aaa');
    Storage::disk('local')->put('families/1/b.pdf', 'bbbb');
    Storage::disk('local')->put('feedback/1/c.png', 'cc');
});

it('uploads new files', function (): void {
    $result = resolve(FileBackup::class)->run(CarbonImmutable::parse('2026-11-15'));

    expect($result)->toBe(['uploaded' => 3, 'kept' => 0, 'moved' => 0, 'pruned' => 0]);
    expect(Storage::disk('backup')->get('families/1/a.pdf'))->toBe('aaa');
    expect(Storage::disk('backup')->get('families/1/b.pdf'))->toBe('bbbb');
    expect(Storage::disk('backup')->get('feedback/1/c.png'))->toBe('cc');
});

it('skips unchanged files', function (): void {
    $backup = resolve(FileBackup::class);
    $backup->run(CarbonImmutable::parse('2026-11-15'));

    expect($backup->run(CarbonImmutable::parse('2026-11-16')))->toBe(['uploaded' => 0, 'kept' => 3, 'moved' => 0, 'pruned' => 0]);
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

it('leaves the manifest unchanged when an upload fails', function (): void {
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

    expect(fn () => resolve(FileBackup::class)->run(CarbonImmutable::parse('2026-11-15')))->toThrow(RuntimeException::class);
    expect(Storage::disk('backup')->exists('manifest.json'))->toBeFalse();

    $failing::$fail = false;
    $result = resolve(FileBackup::class)->run(CarbonImmutable::parse('2026-11-16'));

    expect($result['uploaded'])->toBe(3)->and($result['kept'])->toBe(0);
});
