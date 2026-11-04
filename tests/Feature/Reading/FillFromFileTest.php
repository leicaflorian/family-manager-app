<?php

declare(strict_types=1);

use App\Contracts\DocumentReader;
use App\Enums\ReadingStatus;
use App\Enums\Role;
use App\Jobs\ReadFile;
use App\Models\AiReading;
use App\Models\Document;
use App\Models\File;
use App\Models\User;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeDocumentReader;

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
    app()->instance(DocumentReader::class, new FakeDocumentReader);
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
});

/**
 * A saved document with its file.
 *
 * @param  array<string, mixed>  $file  attributes of the file
 */
function savedDocument(array $file = []): Document
{
    $stored = File::factory()->for(test()->marco->family)->create($file);

    return Document::factory()->for(test()->marco->family)->create(['file_id' => $stored->id]);
}

test('Fill in from the file reads the file of the document again', function (): void {
    $document = savedDocument(['reading_status' => ReadingStatus::Done]);

    $this->from(route('documents.edit', $document))->post(route('files.read', $document->file))
        ->assertRedirect(route('documents.edit', $document));

    expect($document->file->fresh()->reading_status)->toBe(ReadingStatus::Queued);
    Queue::assertPushed(ReadFile::class, fn (ReadFile $job): bool => $job->fileId === $document->file_id);
});

test('a document copied from Directus has no reading status and can be read', function (): void {
    $document = savedDocument(['reading_status' => null]);

    $this->get(route('documents.edit', $document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('reading.file_id', $document->file_id)
        ->where('reading.status', null)
        ->where('reading.can_read', true)
        ->where('reading.unavailable', null));

    $this->post(route('files.read', $document->file))->assertRedirect();

    expect($document->file->fresh()->reading_status)->toBe(ReadingStatus::Queued);
});

test('the page sends the state of the reading and, once done, the proposal', function (): void {
    $document = savedDocument([
        'reading_status' => ReadingStatus::Done,
        'reading' => ['proposal' => [
            'title' => 'Payslip', 'category_id' => null, 'person_id' => null, 'organization_id' => null,
            'date' => '2026-10-25', 'expiry_date' => null, 'links' => [], 'headline' => 'I found a payslip', 'found' => [],
        ]],
    ]);

    $this->get(route('documents.edit', $document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('reading.status', 'done')
        ->where('reading.proposal.headline', 'I found a payslip')
        ->where('reading.proposal.date', '2026-10-25'));
});

test('a file read again sends no old proposal while it waits or is read', function (ReadingStatus $status): void {
    $document = savedDocument([
        'reading_status' => $status,
        'reading' => ['proposal' => ['title' => 'Old', 'category_id' => null, 'person_id' => null, 'organization_id' => null, 'date' => null, 'expiry_date' => null, 'links' => [], 'headline' => 'Old', 'found' => []]],
    ]);

    $this->get(route('documents.edit', $document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('reading.status', $status->value)
        ->where('reading.proposal', null));
})->with([ReadingStatus::Queued, ReadingStatus::Reading]);

test('a failed reading sends why', function (): void {
    $document = savedDocument(['reading_status' => ReadingStatus::Failed, 'reading_error' => 'The file could not be read.']);

    $this->get(route('documents.edit', $document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('reading.error', 'The file could not be read.')
        ->where('reading.can_read', true));
});

test('the reading is a prop of its own, so the poll does not reload the form', function (): void {
    $document = savedDocument();

    $this->get(route('documents.edit', $document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->reloadOnly('reading', fn (AssertableInertia $reload): AssertableInertia => $reload
            ->has('reading.can_read')
            ->missing('document')
            ->missing('options')));
});

test('there is no button, and a sentence says why, when the reading is off', function (): void {
    app()->instance(DocumentReader::class, (new FakeDocumentReader)->off());
    $document = savedDocument();

    $this->get(route('documents.edit', $document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('reading.can_read', false)
        ->where('reading.unavailable', 'Automatic reading is not on.'));
});

test('there is no button, and a sentence says why, when the readings of the month are used up', function (): void {
    config()->set('document_reader.monthly_limit', 1);
    AiReading::factory()->for($this->marco->family)->create();
    $document = savedDocument();

    $this->get(route('documents.edit', $document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('reading.can_read', false)
        ->where('reading.unavailable', fn (string $text): bool => str_starts_with($text, 'You have used all the automatic readings of')));
});

test('a member can use it', function (): void {
    $rosa = User::factory()->for($this->marco->family)->create(['role' => Role::Member, 'email_verified_at' => now()]);
    $document = savedDocument();

    $this->actingAs($rosa)->get(route('documents.edit', $document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('reading.can_read', true));

    $this->actingAs($rosa)->post(route('files.read', $document->file))->assertRedirect();

    Queue::assertPushed(ReadFile::class, 1);
});

test('a document of another family is 404, to open and to read', function (): void {
    $rossi = registerFamily('Rossi', 'anna@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): Document => Document::factory()->for($rossi->family)->create(['file_id' => File::factory()->for($rossi->family)->create()->id]));

    $this->actingAs($this->marco)->get(route('documents.edit', $theirs))->assertNotFound();
    $this->actingAs($this->marco)->post(route('files.read', $theirs->file_id))->assertNotFound();

    Queue::assertNothingPushed();
});

test('reading a document\'s file with the reading off leaves it off, with the reason', function (): void {
    app()->instance(DocumentReader::class, (new FakeDocumentReader)->off());
    $document = savedDocument(['reading_status' => ReadingStatus::Done]);

    $this->post(route('files.read', $document->file))->assertRedirect();

    expect($document->file->fresh())->reading_status->toBe(ReadingStatus::Off)->reading_error->toBe('Automatic reading is not on.');
    Queue::assertNothingPushed();
});

test('reading a document\'s file with the month used up leaves it off, with the reason', function (): void {
    config()->set('document_reader.monthly_limit', 1);
    AiReading::factory()->for($this->marco->family)->create();
    $document = savedDocument(['reading_status' => ReadingStatus::Done]);

    $this->post(route('files.read', $document->file))->assertRedirect();

    expect($document->file->fresh())->reading_status->toBe(ReadingStatus::Off)
        ->and($document->file->fresh()->reading_error)->toStartWith('You have used all the automatic readings of');
    Queue::assertNothingPushed();
});
