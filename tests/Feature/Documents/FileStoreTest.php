<?php

declare(strict_types=1);

use App\Models\File;
use App\Models\User;
use App\Services\FileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('stores the file in the folder of the family with a new name', function (): void {
    $file = resolve(FileStore::class)->store(UploadedFile::fake()->create('Electricity bill.pdf', 120, 'application/pdf'));

    expect($file->family_id)->toBe($this->user->family_id)
        ->and($file->disk)->toBe('local')
        ->and($file->original_name)->toBe('Electricity bill.pdf')
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->size)->toBe(120 * 1024)
        ->and($file->path)->toMatch('#^families/'.$this->user->family_id.'/[0-9a-f-]{36}\.pdf$#');
    Storage::disk('local')->assertExists($file->path);
});

test('delete removes the file from the disk too', function (): void {
    $files = resolve(FileStore::class);
    $file = $files->store(UploadedFile::fake()->image('receipt.jpg'));

    $files->delete($file);

    Storage::disk('local')->assertMissing($file->path);
    expect(File::query()->find($file->id))->toBeNull();
});
