<?php

declare(strict_types=1);

use App\Models\File;
use App\Services\FilePreviews;
use App\Services\FileStore;
use App\Support\CurrentFamily;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->user = registerFamily();
    $this->actingAs($this->user);
});

/**
 * Stores a fixture as an upload of the current family.
 */
function previewsResetStore(string $fixture, string $mime): File
{
    $temp = tempnam(sys_get_temp_dir(), 'fixture');
    copy(base_path('tests/Fixtures/'.$fixture), $temp);

    return resolve(FileStore::class)->store(new UploadedFile($temp, $fixture, $mime, null, true));
}

test('the reset deletes the PDF previews and clears their columns, in every family', function (): void {
    $pdf = previewsResetStore('one-page.pdf', 'application/pdf');
    $broken = previewsResetStore('broken.pdf', 'application/pdf');
    $path = resolve(FilePreviews::class)->path($pdf);
    resolve(FilePreviews::class)->path($broken);
    $other = registerFamily('Rossi', 'rossi@example.com');
    $otherPdf = CurrentFamily::using($other->family_id, function (): File {
        $file = previewsResetStore('one-page.pdf', 'application/pdf');
        resolve(FilePreviews::class)->path($file);

        return $file;
    });
    Storage::disk('local')->assertExists((string) $path);

    $this->artisan('files:previews', ['--reset-pdf' => true])->expectsOutputToContain('Previews reset: 3')->assertSuccessful();

    Storage::disk('local')->assertMissing((string) $path);
    expect($pdf->fresh()->preview_path)->toBeNull()
        ->and($broken->fresh()->preview_failed)->toBeFalse()
        ->and(CurrentFamily::using($other->family_id, fn (): ?string => $otherPdf->fresh()->preview_path))->toBeNull()
        ->and(resolve(FilePreviews::class)->path($pdf->fresh()))->not->toBeNull();
});

test('the reset leaves the previews of the photos alone', function (): void {
    $heic = previewsResetStore('photo.heic', 'image/heic');
    $path = resolve(FilePreviews::class)->path($heic);

    $this->artisan('files:previews', ['--reset-pdf' => true])->expectsOutputToContain('Previews reset: 0')->assertSuccessful();

    Storage::disk('local')->assertExists((string) $path);
    expect($heic->fresh()->preview_path)->toBe($path);
});

test('without the option nothing is deleted', function (): void {
    $this->artisan('files:previews')->assertFailed();
});
