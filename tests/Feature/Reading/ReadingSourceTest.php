<?php

declare(strict_types=1);

use App\Exceptions\Reading\ReadingSkipped;
use App\Models\File;
use App\Services\FileStore;
use App\Services\Reading\ReadingSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\Base64Image;

beforeEach(function (): void {
    Storage::fake('local');
    $this->actingAs(registerFamily());
});

/**
 * Stores some bytes as an uploaded file of the family.
 */
function storeReadable(string $content, string $name, string $mime): File
{
    $temp = tempnam(sys_get_temp_dir(), 'reading');
    file_put_contents($temp, $content);

    // the store reads the type from the bytes: the row says what the test needs
    return tap(resolve(FileStore::class)->store(new UploadedFile($temp, $name, $mime, null, true)))
        ->forceFill(['mime_type' => $mime]);
}

/**
 * A JPEG made with GD, as bytes.
 */
function jpegBytes(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, 0xFF0000);
    ob_start();
    imagejpeg($image, null, 90);

    return (string) ob_get_clean();
}

/**
 * The size of the image the model gets.
 *
 * @return array{int, int}
 */
function sentSize(ReadingSource $source): array
{
    $size = getimagesizefromstring(base64_decode($source->attachment()->base64, true) ?: '');

    return [$size[0], $size[1]];
}

test('refuses a format the model does not read', function (): void {
    $file = storeReadable('PK', 'archive.zip', 'application/zip');

    expect(fn () => ReadingSource::check($file))
        ->toThrow(ReadingSkipped::class, 'This kind of file cannot be read.');
});

test('refuses a text over 100 KB', function (): void {
    $file = storeReadable(str_repeat('a', 100 * 1024 + 1), 'big.txt', 'text/plain');

    expect(fn () => ReadingSource::check($file))
        ->toThrow(ReadingSkipped::class, 'The text is too big (at most 100 KB).');
});

test('strips the BOM of a text', function (): void {
    $file = storeReadable("\xEF\xBB\xBFciao €", 'note.txt', 'text/plain');

    $source = ReadingSource::of($file);

    expect($source->mediaType)->toBe('text/plain')
        ->and($source->attachment())->toBeInstanceOf(Base64Document::class)
        ->and(base64_decode($source->attachment()->base64, true))->toBe('ciao €');
});

test('reads a photo as jpeg', function (): void {
    $png = imagecreatetruecolor(10, 10);
    ob_start();
    imagepng($png);
    $file = storeReadable((string) ob_get_clean(), 'photo.png', 'image/png');

    $source = ReadingSource::of($file);

    expect($source->mediaType)->toBe('image/jpeg')
        ->and($source->attachment())->toBeInstanceOf(Base64Image::class)
        ->and(getimagesizefromstring(base64_decode($source->attachment()->base64, true) ?: '')['mime'])->toBe('image/jpeg');
});

test('a PDF goes whole', function (): void {
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/one-page.pdf'));
    $file = storeReadable($bytes, 'bill.pdf', 'application/pdf');

    $source = ReadingSource::of($file);

    expect($source->mediaType)->toBe('application/pdf')
        ->and($source->attachment())->toBeInstanceOf(Base64Document::class)
        ->and(base64_decode($source->attachment()->base64, true))->toBe($bytes);
});

test('a wide photo is made 2000 px wide at most', function (): void {
    $file = storeReadable(jpegBytes(3000, 1500), 'wide.jpg', 'image/jpeg');

    expect(sentSize(ReadingSource::of($file)))->toBe([2000, 1000]);
});

test('a small photo is not enlarged', function (): void {
    $file = storeReadable(jpegBytes(300, 200), 'small.jpg', 'image/jpeg');

    expect(sentSize(ReadingSource::of($file)))->toBe([300, 200]);
});

test('a HEIC photo is read as a jpeg', function (): void {
    $file = storeReadable((string) file_get_contents(base_path('tests/Fixtures/photo.heic')), 'photo.heic', 'image/heic');

    $source = ReadingSource::of($file);

    expect($source->mediaType)->toBe('image/jpeg')
        ->and(getimagesizefromstring(base64_decode($source->attachment()->base64, true) ?: '')['mime'])->toBe('image/jpeg');
});

test('a photo turned on its side comes out straight', function (): void {
    $jpeg = jpegBytes(300, 200);
    // orientation 6: the camera was turned, the pixels must be turned 90 degrees clockwise
    $exif = "Exif\0\0MM\0*\0\0\0\x08\0\x01\x01\x12\0\x03\0\0\0\x01\0\x06\0\0\0\0\0\0";
    $segment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
    $file = storeReadable(substr($jpeg, 0, 2).$segment.substr($jpeg, 2), 'turned.jpg', 'image/jpeg');
    expect(exif_read_data(Storage::disk('local')->path($file->path))['Orientation'] ?? null)->toBe(6);

    expect(sentSize(ReadingSource::of($file)))->toBe([200, 300]);
});

test('a 21 MB file is skipped with its message', function (): void {
    $file = storeReadable('x', 'huge.pdf', 'application/pdf');
    $file->forceFill(['size' => 21 * 1024 * 1024]);

    expect(fn () => ReadingSource::check($file))
        ->toThrow(ReadingSkipped::class, 'The file is too big to be read: fill it in yourself.');
});

test('the messages are translated', function (): void {
    app()->setLocale('it');
    $file = storeReadable('PK', 'archive.zip', 'application/zip');

    expect(fn () => ReadingSource::check($file))
        ->toThrow(ReadingSkipped::class, __('This kind of file cannot be read.'));
    expect(__('This kind of file cannot be read.'))->not->toBe('This kind of file cannot be read.');
});
