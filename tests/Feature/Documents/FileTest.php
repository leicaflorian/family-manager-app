<?php

declare(strict_types=1);

use App\Models\File;
use App\Services\FileStore;
use App\Support\CurrentFamily;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Stores a fixture as a real file of the family, without moving the fixture.
 */
function storeRealFile(int $familyId, string $fixture, string $name): File
{
    $temp = tempnam(sys_get_temp_dir(), 'fixture');
    copy(base_path('tests/Fixtures/'.$fixture), $temp);

    return CurrentFamily::using($familyId, fn () => resolve(FileStore::class)
        ->store(new UploadedFile($temp, $name, 'application/pdf', null, true)));
}

beforeEach(function (): void {
    Storage::fake('local');
    $this->marco = registerFamily();
    $this->file = storeRealFile($this->marco->family_id, 'one-page.pdf', 'Electricity bill.pdf');
});

test('the file of the Bianchis opens and downloads when signed in as a Bianchi', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('files.show', $this->file))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('files.download', $this->file))->assertOk()->assertDownload('Electricity bill.pdf');
});

test('the preview of a PDF is a JPEG kept in the browser cache for a week', function (): void {
    $this->actingAs($this->marco);

    $response = $this->get(route('files.preview', $this->file))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('max-age=604800')
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

test('with the same ETag the preview answers 304', function (): void {
    $this->actingAs($this->marco);
    $etag = (string) $this->get(route('files.preview', $this->file))->headers->get('ETag');

    $response = $this->get(route('files.preview', $this->file), ['If-None-Match' => $etag])->assertStatus(304);

    expect($response->getContent())->toBeEmpty()
        ->and($response->headers->get('ETag'))->toBe($etag)
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=604800');
});

test('the file is kept in the cache too, and answers 304', function (): void {
    $this->actingAs($this->marco);
    $first = $this->get(route('files.show', $this->file))->assertOk();
    $etag = (string) $first->headers->get('ETag');

    expect($first->headers->get('Cache-Control'))->toContain('private')->toContain('max-age=604800')
        ->and($etag)->not->toBe('');
    $this->get(route('files.show', $this->file), ['If-None-Match' => $etag])->assertStatus(304);
});

test('downloading has no cache headers', function (): void {
    $this->actingAs($this->marco);

    $response = $this->get(route('files.download', $this->file))->assertOk();

    expect($response->headers->get('Cache-Control'))->not->toContain('max-age=604800')
        ->and($response->headers->has('ETag'))->toBeFalse();
});

test('a PDF without preview answers 404', function (): void {
    $this->actingAs($this->marco);
    $broken = storeRealFile($this->marco->family_id, 'broken.pdf', 'broken.pdf');

    $this->get(route('files.preview', $broken))->assertNotFound();
});

test('a PNG answers 404', function (): void {
    $this->actingAs($this->marco);
    $png = CurrentFamily::using($this->marco->family_id, fn () => resolve(FileStore::class)
        ->store(UploadedFile::fake()->image('photo.png')));

    $this->get(route('files.preview', $png))->assertNotFound();
});

test('signed in as a Rossi the preview of the Bianchis answers 404', function (): void {
    $this->actingAs(registerFamily('Rossi', 'anna@example.com'));

    $this->get(route('files.preview', $this->file))->assertNotFound();
});

test('without signing in the preview leads to Sign in', function (): void {
    $this->get(route('files.preview', $this->file))->assertRedirect(route('login'));
});

test('signed in as a Rossi the file of the Bianchis answers 404', function (): void {
    $this->actingAs(registerFamily('Rossi', 'anna@example.com'));

    $this->get(route('files.show', $this->file))->assertNotFound();
    $this->get(route('files.download', $this->file))->assertNotFound();
});

test('a file whose content is missing on the disk answers 404', function (): void {
    $this->actingAs($this->marco);
    Storage::disk('local')->delete($this->file->path);

    $this->get(route('files.show', $this->file))->assertNotFound();
    $this->get(route('files.download', $this->file))->assertNotFound();
});

test('the private disk is not served outside the file routes, not even with a signed link', function (): void {
    $this->actingAs($this->marco);

    expect(Route::has('storage.local'))->toBeFalse();
    $this->get('/storage/'.$this->file->path)->assertNotFound();
});

test('without signing in the file leads to Sign in', function (): void {
    $this->get(route('files.show', $this->file))->assertRedirect(route('login'));
});

test('a .pdf that is not a PDF is rejected by its real type', function (): void {
    $this->actingAs($this->marco);
    $path = tempnam(sys_get_temp_dir(), 'fake');
    file_put_contents($path, "#!/bin/sh\necho 'not a pdf'\n");

    $this->post(route('upload.store'), ['files' => [new UploadedFile($path, 'fake.pdf', 'application/pdf', null, true)]])
        ->assertSessionHasErrors('files.0');

    expect(File::query()->count())->toBe(1);
});
