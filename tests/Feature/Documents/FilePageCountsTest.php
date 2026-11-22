<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\File;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

/**
 * A file of a family with its content on the disk.
 *
 * @param  string  $fixture  a file of tests/Fixtures, or an empty string for a text file
 */
function pageCountsFile(Family $family, string $name, string $mime, string $fixture, ?int $count = null): File
{
    $path = "families/{$family->id}/{$name}";
    Storage::disk('local')->put($path, $fixture === '' ? 'text' : (string) file_get_contents(base_path('tests/Fixtures/'.$fixture)));

    return CurrentFamily::using($family->id, function () use ($path, $name, $mime, $count): File {
        $file = File::query()->create([
            'disk' => 'local', 'path' => $path, 'original_name' => $name, 'mime_type' => $mime, 'size' => 10,
        ]);
        $file->forceFill(['page_count' => $count])->save();

        return $file;
    });
}

test('it fills the PDFs without a count and skips the others', function (): void {
    $verdi = Family::factory()->create();
    $rossi = Family::factory()->create();
    $one = pageCountsFile($verdi, 'one.pdf', 'application/pdf', 'one-page.pdf');
    $two = pageCountsFile($rossi, 'two.pdf', 'application/pdf', 'one-page.pdf');
    $done = pageCountsFile($verdi, 'done.pdf', 'application/pdf', 'one-page.pdf', 7);
    $broken = pageCountsFile($verdi, 'broken.pdf', 'application/pdf', 'broken.pdf');
    $text = pageCountsFile($verdi, 'note.txt', 'text/plain', '');

    $this->artisan('files:page-counts')->expectsOutputToContain('Page counts written: 2')->assertSuccessful()->run();

    $count = fn (File $file): ?int => File::query()->withoutGlobalScopes()->find($file->id)?->page_count;
    expect($count($one))->toBe(1)
        ->and($count($two))->toBe(1)
        ->and($count($done))->toBe(7)
        ->and($count($broken))->toBeNull()
        ->and($count($text))->toBeNull();
});
