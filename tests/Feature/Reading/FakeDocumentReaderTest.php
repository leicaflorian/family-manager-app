<?php

declare(strict_types=1);

use App\Data\Reading\Extraction;
use App\Data\Reading\Reading;
use App\Exceptions\Reading\ReadingFailed;
use App\Models\File;
use App\Services\Reading\ReadingContext;
use App\Services\Reading\ReadingSource;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDocumentReader;

beforeEach(function (): void {
    Storage::fake('local');
    $file = File::factory()->create(['mime_type' => 'text/plain', 'size' => 4]);
    Storage::disk('local')->put($file->path, 'ciao');
    $this->source = ReadingSource::of($file);
    $this->context = new ReadingContext([], [], [], [], null, null);
});

test('the fake reader gives its extraction and counts the readings', function (): void {
    $reader = (new FakeDocumentReader)->returns(Extraction::from(['kind' => 'receipt', 'confidence' => 'high']));

    $reading = $reader->read($this->source, $this->context, 'it');
    $reader->read($this->source, $this->context, 'it');

    expect($reading->extraction->kind)->toBe('receipt')
        ->and($reading->provider)->toBe('gemini')
        ->and($reader->reads())->toBe(2)
        ->and($reader->isActive())->toBeTrue()
        ->and((new FakeDocumentReader)->off()->isActive())->toBeFalse();
});

test('the fake reader fails with its error', function (): void {
    $reader = (new FakeDocumentReader)->fails(ReadingFailed::refused());

    expect(fn (): Reading => $reader->read($this->source, $this->context, 'it'))
        ->toThrow(ReadingFailed::class, 'The model refused to read the document.')
        ->and($reader->reads())->toBe(1);
});
