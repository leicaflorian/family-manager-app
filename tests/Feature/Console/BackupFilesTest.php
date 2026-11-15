<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

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
