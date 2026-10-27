<?php

declare(strict_types=1);

use App\Services\ArchivioFile;
use App\Support\CurrentFamily;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->marco = iscriviFamiglia();
    $this->file = CurrentFamily::per($this->marco->family_id, fn () => resolve(ArchivioFile::class)
        ->salva(UploadedFile::fake()->create('Bolletta luce.pdf', 20, 'application/pdf')));
});

test('il file dei Bianchi si apre e si scarica entrando come Bianchi', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('file.show', $this->file))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('file.scarica', $this->file))->assertOk()->assertDownload('Bolletta luce.pdf');
});

test('entrando come Rossi il file dei Bianchi risponde 404', function (): void {
    $this->actingAs(iscriviFamiglia('Rossi', 'anna@example.com'));

    $this->get(route('file.show', $this->file))->assertNotFound();
    $this->get(route('file.scarica', $this->file))->assertNotFound();
});

test('senza accesso il file porta ad Accedi', function (): void {
    $this->get(route('file.show', $this->file))->assertRedirect(route('login'));
});
