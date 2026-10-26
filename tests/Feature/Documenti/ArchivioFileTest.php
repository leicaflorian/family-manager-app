<?php

declare(strict_types=1);

use App\Models\File;
use App\Models\User;
use App\Services\ArchivioFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->utente = User::factory()->create();
    $this->actingAs($this->utente);
});

test('salva il file nella cartella della famiglia con un nome nuovo', function (): void {
    $file = resolve(ArchivioFile::class)->salva(UploadedFile::fake()->create('Bolletta luce.pdf', 120, 'application/pdf'));

    expect($file->family_id)->toBe($this->utente->family_id)
        ->and($file->disk)->toBe('local')
        ->and($file->original_name)->toBe('Bolletta luce.pdf')
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->size)->toBe(120 * 1024)
        ->and($file->path)->toMatch('#^families/'.$this->utente->family_id.'/[0-9a-f-]{36}\.pdf$#');
    Storage::disk('local')->assertExists($file->path);
});

test('elimina toglie il file anche dal disco', function (): void {
    $archivio = resolve(ArchivioFile::class);
    $file = $archivio->salva(UploadedFile::fake()->image('scontrino.jpg'));

    $archivio->elimina($file);

    Storage::disk('local')->assertMissing($file->path);
    expect(File::query()->find($file->id))->toBeNull();
});
