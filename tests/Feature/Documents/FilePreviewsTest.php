<?php

declare(strict_types=1);

use App\Models\File;
use App\Services\FilePreviews;
use App\Services\FileStore;
use Carbon\CarbonInterval;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->user = registerFamily();
    $this->actingAs($this->user);
});

/**
 * Stores a fixture as an upload, without moving the fixture.
 */
function storeFixture(string $fixture, string $name, string $mime): File
{
    $temp = tempnam(sys_get_temp_dir(), 'fixture');
    copy(base_path('tests/Fixtures/'.$fixture), $temp);

    return resolve(FileStore::class)->store(new UploadedFile($temp, $name, $mime, null, true));
}

test('a PDF gets a JPEG of its first page, 1200 px wide', function (): void {
    $file = storeFixture('one-page.pdf', 'bill.pdf', 'application/pdf');

    $path = resolve(FilePreviews::class)->path($file);

    $name = pathinfo($file->path, PATHINFO_FILENAME);
    expect($path)->toBe("families/{$file->family_id}/previews/{$name}.jpg");
    Storage::disk('local')->assertExists($path);
    $size = getimagesize(Storage::disk('local')->path($path));
    expect($size[0])->toBe(1200)
        ->and($size[2])->toBe(IMAGETYPE_JPEG)
        ->and($file->fresh()->preview_path)->toBe($path);
});

test('the second time the image is not made again', function (): void {
    $file = storeFixture('one-page.pdf', 'bill.pdf', 'application/pdf');
    $previews = resolve(FilePreviews::class);

    $first = $previews->path($file);
    $absolute = Storage::disk('local')->path((string) $first);
    touch($absolute, time() - 100);
    clearstatcache();
    $time = filemtime($absolute);
    $second = $previews->path($file->fresh());
    clearstatcache();

    expect($second)->toBe($first)->and(filemtime($absolute))->toBe($time);
});

test('an image deleted from the disk is made again', function (): void {
    $file = storeFixture('one-page.pdf', 'bill.pdf', 'application/pdf');
    $previews = resolve(FilePreviews::class);
    $first = (string) $previews->path($file);
    Storage::disk('local')->delete($first);

    $second = $previews->path($file->fresh());

    expect($second)->toBe($first);
    Storage::disk('local')->assertExists($first);
});

test('a PDF that cannot be read is marked and not tried again', function (): void {
    $broken = storeFixture('broken.pdf', 'broken.pdf', 'application/pdf');
    $previews = resolve(FilePreviews::class);

    expect($previews->path($broken))->toBeNull()
        ->and($broken->fresh()->preview_failed)->toBeTrue();

    $good = storeFixture('one-page.pdf', 'bill.pdf', 'application/pdf');
    $good->forceFill(['preview_failed' => true])->save();

    expect($previews->path($good->fresh()))->toBeNull();
    expect(Storage::disk('local')->allFiles("families/{$good->family_id}/previews"))->toBe([]);
});

test('a PNG has no preview', function (): void {
    $file = resolve(FileStore::class)->store(UploadedFile::fake()->image('photo.png'));

    expect(resolve(FilePreviews::class)->path($file))->toBeNull()
        ->and(Storage::disk('local')->allFiles("families/{$file->family_id}/previews"))->toBe([]);
});

test('a HEIC photo gets a JPEG at most 1200 px wide', function (): void {
    $file = storeFixture('photo.heic', 'photo.heic', 'image/heic');

    $path = resolve(FilePreviews::class)->path($file);

    expect($path)->not->toBeNull();
    $size = getimagesize(Storage::disk('local')->path((string) $path));
    expect($size[0])->toBe(1200)->and($size[1])->toBe(800)->and($size[2])->toBe(IMAGETYPE_JPEG);
});

test('deleting the file deletes its preview', function (): void {
    $file = storeFixture('one-page.pdf', 'bill.pdf', 'application/pdf');
    $path = (string) resolve(FilePreviews::class)->path($file);

    resolve(FileStore::class)->delete($file->fresh());

    Storage::disk('local')->assertMissing($path);
    Storage::disk('local')->assertMissing($file->path);
});

test('deleting the family deletes the previews too', function (): void {
    $file = storeFixture('one-page.pdf', 'bill.pdf', 'application/pdf');
    $path = (string) resolve(FilePreviews::class)->path($file);

    resolve(FileStore::class)->deleteFamily($file->family_id);

    Storage::disk('local')->assertMissing($path);
});

test('a file with a HEIC mime type reports a preview, a failed one does not', function (): void {
    $file = storeFixture('photo.heic', 'photo.heic', 'image/heic');
    expect($file->hasPreview())->toBeTrue();

    $file->forceFill(['preview_failed' => true]);
    expect($file->hasPreview())->toBeFalse();

    $png = resolve(FileStore::class)->store(UploadedFile::fake()->image('a.png'));
    expect($png->hasPreview())->toBeFalse();
});

test('a conversion that takes too long marks the file as failed and leaves nothing', function (): void {
    $file = storeFixture('one-page.pdf', 'bill.pdf', 'application/pdf');

    expect(new FilePreviews(CarbonInterval::milliseconds(1))->path($file))->toBeNull()
        ->and($file->fresh()->preview_failed)->toBeTrue()
        ->and(Storage::disk('local')->allFiles("families/{$file->family_id}/previews"))->toBe([]);
});

test('deleting a stale copy of the file still deletes the preview', function (): void {
    $file = storeFixture('one-page.pdf', 'bill.pdf', 'application/pdf');
    $stale = File::query()->findOrFail($file->id);
    $path = (string) resolve(FilePreviews::class)->path($file);
    Storage::disk('local')->assertExists($path);

    resolve(FileStore::class)->delete($stale);

    Storage::disk('local')->assertMissing($path);
});
