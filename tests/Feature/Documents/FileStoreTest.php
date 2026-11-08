<?php

declare(strict_types=1);

use App\Enums\FileSource;
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

test('storeContents saves a text as a file of the family, like an upload', function (): void {
    $file = resolve(FileStore::class)->storeContents("\u{FEFF}Avviso arrivato per posta", 'Avviso - La tua fattura.txt', 'text/plain', true, FileSource::Mail, 'Arrived by mail');

    expect($file->family_id)->toBe($this->user->family_id)
        ->and($file->original_name)->toBe('Avviso - La tua fattura.txt')
        ->and($file->mime_type)->toBe('text/plain')
        ->and($file->to_sort)->toBeTrue()
        ->and($file->source)->toBe(FileSource::Mail)
        ->and($file->note)->toBe('Arrived by mail')
        ->and($file->checksum)->toBe(hash('sha256', "\u{FEFF}Avviso arrivato per posta"))
        ->and($file->path)->toMatch('#^families/'.$this->user->family_id.'/[0-9a-f-]{36}\.txt$#')
        ->and(Storage::disk('local')->get($file->path))->toBe("\u{FEFF}Avviso arrivato per posta");
});

test('a row that is not written leaves no file on the disk', function (): void {
    File::creating(fn () => throw new RuntimeException('The database is gone.'));

    expect(fn () => resolve(FileStore::class)->storeContents('testo', 'avviso.txt', 'text/plain', true, FileSource::Mail, null))
        ->toThrow(RuntimeException::class, 'The database is gone.')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
