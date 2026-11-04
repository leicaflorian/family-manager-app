<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\File;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

/**
 * A file of a family with its content on the disk and no checksum, like the ones uploaded before the readings.
 *
 * @param  string|null  $content  the content, null for a file missing on the disk
 */
function oldFile(Family $family, string $name, ?string $content, ?string $checksum = null): File
{
    $path = "families/{$family->id}/{$name}";

    if ($content !== null) {
        Storage::disk('local')->put($path, $content);
    }

    return CurrentFamily::using($family->id, fn (): File => File::query()->create([
        'disk' => 'local', 'path' => $path, 'original_name' => $name, 'mime_type' => 'application/pdf', 'size' => 10, 'checksum' => $checksum,
    ]));
}

test('it writes the checksum of the files that have none, family by family', function (): void {
    $verdi = Family::factory()->create();
    $rossi = Family::factory()->create();
    $one = oldFile($verdi, 'one.pdf', 'content one');
    $two = oldFile($rossi, 'two.pdf', 'content two');

    $this->artisan('files:checksums')->expectsOutputToContain('Checksums written: 2')->assertSuccessful()->run();

    expect(File::query()->withoutGlobalScopes()->find($one->id)?->checksum)->toBe(hash('sha256', 'content one'))
        ->and(File::query()->withoutGlobalScopes()->find($two->id)?->checksum)->toBe(hash('sha256', 'content two'));
});

test('it skips a file missing on the disk and says so', function (): void {
    $family = Family::factory()->create();
    $missing = oldFile($family, 'gone.pdf', null);
    $there = oldFile($family, 'there.pdf', 'still here');

    $this->artisan('files:checksums')
        ->expectsOutputToContain('Checksums written: 1')
        ->expectsOutputToContain('Missing on the disk: 1')
        ->expectsOutputToContain('gone.pdf')
        ->assertSuccessful()
        ->run();

    expect(File::query()->withoutGlobalScopes()->find($missing->id)?->checksum)->toBeNull()
        ->and(File::query()->withoutGlobalScopes()->find($there->id)?->checksum)->toBe(hash('sha256', 'still here'));
});

test('run again it changes nothing and leaves the checksums it finds', function (): void {
    $family = Family::factory()->create();
    $done = oldFile($family, 'done.pdf', 'real content', 'already-there');
    $new = oldFile($family, 'new.pdf', 'new content');

    $this->artisan('files:checksums')->expectsOutputToContain('Checksums written: 1')->assertSuccessful()->run();
    $this->artisan('files:checksums')->expectsOutputToContain('Checksums written: 0')->assertSuccessful()->run();

    expect(File::query()->withoutGlobalScopes()->find($done->id)?->checksum)->toBe('already-there')
        ->and(File::query()->withoutGlobalScopes()->find($new->id)?->checksum)->toBe(hash('sha256', 'new content'));
});
