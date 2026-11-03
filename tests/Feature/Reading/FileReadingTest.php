<?php

declare(strict_types=1);

use App\Contracts\DocumentReader;
use App\Enums\ReadingStatus;
use App\Enums\Role;
use App\Jobs\ReadFile;
use App\Models\Document;
use App\Models\File;
use App\Models\User;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDocumentReader;

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
    app()->instance(DocumentReader::class, new FakeDocumentReader);
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
});

test('read again', function (): void {
    $failed = File::factory()->for($this->marco->family)->create(['to_sort' => true, 'reading_status' => ReadingStatus::Failed, 'reading_error' => 'The file could not be read.']);

    $this->from(route('upload'))->post(route('files.read', $failed))->assertRedirect(route('upload'));

    expect($failed->fresh())->reading_status->toBe(ReadingStatus::Queued)->reading_error->toBeNull();
    Queue::assertPushed(ReadFile::class, fn (ReadFile $job): bool => $job->fileId === $failed->id && $job->userId === $this->marco->id);
});

test('a file already waiting in the queue is not queued twice', function (): void {
    $queued = File::factory()->for($this->marco->family)->create(['to_sort' => true, 'reading_status' => ReadingStatus::Queued]);

    $this->from(route('upload'))->post(route('files.read', $queued))->assertRedirect(route('upload'));

    Queue::assertNothingPushed();
});

test('the file of a document is read again too', function (): void {
    $file = File::factory()->for($this->marco->family)->create(['reading_status' => ReadingStatus::Done]);
    $document = Document::factory()->for($this->marco->family)->create(['file_id' => $file->id]);

    $this->from(route('documents.edit', $document))->post(route('files.read', $file))->assertRedirect(route('documents.edit', $document));

    expect($file->fresh()->reading_status)->toBe(ReadingStatus::Queued);
    Queue::assertPushed(ReadFile::class, 1);
});

test('a member reads a file again', function (): void {
    $rosa = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);
    $failed = File::factory()->for($this->marco->family)->create(['to_sort' => true, 'reading_status' => ReadingStatus::Failed]);

    $this->actingAs($rosa)->from(route('upload'))->post(route('files.read', $failed))->assertRedirect(route('upload'));

    Queue::assertPushed(ReadFile::class, 1);
});

test('another family\'s file is 404, also on read', function (): void {
    $rossi = registerFamily('Rossi', 'anna@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): File => File::factory()->for($rossi->family)->create(['to_sort' => true, 'reading_status' => ReadingStatus::Failed]));

    $this->actingAs($this->marco)->post(route('files.read', $theirs))->assertNotFound();
    $this->actingAs($this->marco)->delete(route('upload.destroy', $theirs))->assertNotFound();

    expect(CurrentFamily::using($rossi->family_id, fn (): ?File => $theirs->fresh()))->reading_status->toBe(ReadingStatus::Failed);
    Queue::assertNothingPushed();
});
