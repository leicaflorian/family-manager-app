<?php

declare(strict_types=1);

use App\Models\File;
use App\Services\FilePreviews;
use App\Services\FileStore;
use Carbon\CarbonInterval;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

/**
 * Puts a file next to the preview of a file, as a tool writing extra images would.
 */
function leaveExtra(File $file, string $suffix): string
{
    $name = pathinfo($file->path, PATHINFO_FILENAME).$suffix;
    $path = "families/{$file->family_id}/previews/{$name}";
    Storage::disk('local')->put($path, 'extra');

    return $path;
}

test('extra files written next to the preview are deleted after a conversion', function (): void {
    $file = storeFixture('one-page.pdf', 'bill.pdf', 'application/pdf');
    $extra = leaveExtra($file, '-depth.jpg');

    $path = (string) resolve(FilePreviews::class)->path($file);

    Storage::disk('local')->assertExists($path);
    Storage::disk('local')->assertMissing($extra);
});

test('extra files are deleted also when the conversion fails', function (): void {
    $file = storeFixture('broken.pdf', 'broken.pdf', 'application/pdf');
    $extra = leaveExtra($file, '-depth.jpg');

    expect(resolve(FilePreviews::class)->path($file))->toBeNull();

    Storage::disk('local')->assertMissing($extra);
});

test('deleting the file deletes every file the preview left', function (): void {
    $file = storeFixture('one-page.pdf', 'bill.pdf', 'application/pdf');
    $path = (string) resolve(FilePreviews::class)->path($file);
    $extra = leaveExtra($file, '-depth.jpg');
    $other = leaveExtra(storeFixture('one-page.pdf', 'other.pdf', 'application/pdf'), '.jpg');

    resolve(FileStore::class)->delete($file->fresh());

    Storage::disk('local')->assertMissing($path);
    Storage::disk('local')->assertMissing($extra);
    Storage::disk('local')->assertExists($other);
});

test('a file deleted while its preview is made leaves no JPEG', function (): void {
    $file = storeFixture('one-page.pdf', 'bill.pdf', 'application/pdf');
    $id = $file->id;
    File::saving(function () use ($id): void {
        DB::table('files')->where('id', $id)->delete();
    });

    expect(resolve(FilePreviews::class)->path($file))->toBeNull();

    Storage::disk('local')->assertMissing(resolve(FilePreviews::class)->previewPathOf($file));
});

test('a big HEIC photo that does not fit in memory is marked and leaves nothing', function (): void {
    $file = storeFixture('photo.heic', 'photo.heic', 'image/heic');
    $limit = ini_get('memory_limit');
    ini_set('memory_limit', (string) (memory_get_usage() + 14 * 1024 * 1024));

    try {
        $path = resolve(FilePreviews::class)->path($file);
    } finally {
        ini_set('memory_limit', (string) $limit);
    }

    expect($path)->toBeNull()
        ->and($file->fresh()->preview_failed)->toBeTrue()
        ->and(Storage::disk('local')->allFiles("families/{$file->family_id}/previews"))->toBe([]);
});

test('a failure is logged with the exit code and the message of the tool', function (): void {
    Log::spy();
    $broken = storeFixture('broken.pdf', 'broken.pdf', 'application/pdf');

    resolve(FilePreviews::class)->path($broken);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === '[file-preview] conversion failed'
        && is_int($context['exit_code'])
        && $context['exit_code'] > 0
        && is_string($context['error']))->once();
});

test('a missing tool does not mark the file as failed', function (): void {
    Log::spy();
    $file = storeFixture('one-page.pdf', 'bill.pdf', 'application/pdf');
    $heic = storeFixture('photo.heic', 'photo.heic', 'image/heic');
    $previews = new FilePreviews(pdfTool: 'no-such-pdf-tool', heicTool: '/no/such/heic-tool');

    expect($previews->path($file))->toBeNull()
        ->and($previews->path($heic))->toBeNull()
        ->and($file->fresh()->preview_failed)->toBeFalse()
        ->and($heic->fresh()->preview_failed)->toBeFalse()
        ->and(Storage::disk('local')->allFiles("families/{$file->family_id}/previews"))->toBe([]);
    Log::shouldHaveReceived('error')->twice();
    expect(resolve(FilePreviews::class)->path($file->fresh()))->not->toBeNull();
});
