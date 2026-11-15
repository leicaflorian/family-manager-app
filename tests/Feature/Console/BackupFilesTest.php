<?php

declare(strict_types=1);

use App\Services\FileBackup;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

it('prints the four numbers', function (): void {
    config(['filesystems.disks.backup.bucket' => 'family-manager']);
    Storage::fake('backup');
    Storage::disk('local')->put('families/1/a.pdf', 'aaa');

    $this->artisan('files:backup')
        ->expectsOutputToContain('Uploaded: 1')
        ->expectsOutputToContain('Kept: 0')
        ->expectsOutputToContain('Moved to deleted: 0')
        ->expectsOutputToContain('Pruned: 0')
        ->assertExitCode(0);

    expect(Storage::disk('backup')->exists('families/1/a.pdf'))->toBeTrue();
});

it('exits 1 and says why when the backup looks suspicious, unless forced', function (): void {
    config(['filesystems.disks.backup.bucket' => 'family-manager']);
    Storage::fake('backup');
    Storage::disk('local')->put('families/1/a.pdf', 'aaa');
    resolve(FileBackup::class)->run(CarbonImmutable::now());
    Storage::disk('local')->deleteDirectory('families');

    Log::spy();

    $this->artisan('files:backup')->expectsOutputToContain('Nothing was moved')->assertExitCode(1);
    expect(Storage::disk('backup')->exists('families/1/a.pdf'))->toBeTrue();
    // The scheduled output goes nowhere: the reason must reach the log
    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'Nothing was moved'))->once();

    $this->artisan('files:backup --force')->expectsOutputToContain('Moved to deleted: 1')->assertExitCode(0);
});

it('exits 1 when a file failed', function (): void {
    config(['filesystems.disks.backup.bucket' => 'family-manager']);
    $root = Storage::fake('backup')->path('');
    $broken = new class($root) extends LocalFilesystemAdapter
    {
        public function writeStream(string $path, $contents, Config $config): void
        {
            throw new RuntimeException('Space unreachable');
        }
    };
    Storage::set('backup', new FilesystemAdapter(new Filesystem($broken), $broken, ['root' => $root]));
    Storage::disk('local')->put('families/1/a.pdf', 'aaa');

    Log::spy();

    $this->artisan('files:backup')->expectsOutputToContain('families/1/a.pdf: Space unreachable')->assertExitCode(1);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'families/1/a.pdf: Space unreachable'))->once();
});

it('warns and does nothing without a bucket', function (): void {
    config(['filesystems.disks.backup.bucket' => null]);
    Storage::fake('backup');
    Storage::disk('local')->put('families/1/a.pdf', 'aaa');

    $this->artisan('files:backup')
        ->expectsOutputToContain('No bucket')
        ->assertExitCode(0);

    expect(Storage::disk('backup')->allFiles())->toBe([]);
});

it('schedules the backup only when the bucket is set', function (): void {
    $commands = fn (): array => collect(resolve(Schedule::class)->events())->pluck('command')->all();
    $has = fn (): bool => collect($commands())->contains(fn (string $c): bool => str_contains($c, 'files:backup'));

    config(['filesystems.disks.backup.bucket' => null]);
    $this->refreshApplication();
    expect($has())->toBeFalse();

    putenv('BACKUP_S3_BUCKET=family-manager');
    $_ENV['BACKUP_S3_BUCKET'] = 'family-manager';
    $this->refreshApplication();
    try {
        expect($has())->toBeTrue();
    } finally {
        putenv('BACKUP_S3_BUCKET');
        unset($_ENV['BACKUP_S3_BUCKET']);
    }
});
