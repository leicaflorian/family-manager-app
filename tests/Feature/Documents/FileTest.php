<?php

declare(strict_types=1);

use App\Models\File;
use App\Services\FileStore;
use App\Support\CurrentFamily;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->marco = registerFamily();
    $this->file = CurrentFamily::using($this->marco->family_id, fn () => resolve(FileStore::class)
        ->store(UploadedFile::fake()->create('Electricity bill.pdf', 20, 'application/pdf')));
});

test('the file of the Bianchis opens and downloads when signed in as a Bianchi', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('files.show', $this->file))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('files.download', $this->file))->assertOk()->assertDownload('Electricity bill.pdf');
});

test('signed in as a Rossi the file of the Bianchis answers 404', function (): void {
    $this->actingAs(registerFamily('Rossi', 'anna@example.com'));

    $this->get(route('files.show', $this->file))->assertNotFound();
    $this->get(route('files.download', $this->file))->assertNotFound();
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
