<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Document;
use App\Models\File;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
    $this->marco = registerFamily();
    $this->laura = User::factory()->for($this->marco->family)->create(['role' => Role::Member, 'email_verified_at' => now()]);
});

/**
 * Makes a file waiting in the Bianchi family.
 *
 * @param  array<string, mixed>  $attributes
 */
function waitingFile(array $attributes = []): File
{
    return File::factory()->for(test()->marco->family)->create(['to_sort' => true, 'created_by' => test()->marco->id, ...$attributes]);
}

/**
 * One entry of the body of `POST import`.
 *
 * @param  array<string, mixed>  $document
 * @return array{file_id: int, document: array<string, mixed>}
 */
function importEntry(File $file, array $document = []): array
{
    return ['file_id' => $file->id, 'document' => ['title' => 'Doc '.$file->id, 'status' => 'active', ...$document]];
}

test('the page opens with the files asked for, in that order, and the choices of the form', function (): void {
    $first = waitingFile(['original_name' => 'a.pdf']);
    $second = waitingFile(['original_name' => 'b.pdf']);

    $this->actingAs($this->marco)->get(route('import', ['files' => "{$second->id},{$first->id}"]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('documents/Import')
            ->where('files.0.id', $second->id)->where('files.1.id', $first->id)
            ->has('statuses')->has('categories')->has('people')->has('organizations')->has('links')->has('tags')
            ->where('failures', []));
});

test('a file of another family is a 404 before anything else', function (): void {
    $mine = waitingFile();
    $foreign = File::factory()->create(['to_sort' => true]);

    $this->actingAs($this->marco)->get(route('import', ['files' => "{$mine->id},{$foreign->id}"]))->assertNotFound();
});

test('a file that is no longer waiting is a 404', function (): void {
    $filed = File::factory()->for($this->marco->family)->create(['to_sort' => false]);

    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $filed->id]))->assertNotFound();
});

test('without files the page goes back to the files to sort', function (): void {
    $this->actingAs($this->marco)->get(route('import'))->assertRedirect(route('to-sort'));
});

test('three files read become three documents', function (): void {
    $files = [waitingFile(), waitingFile(), waitingFile()];
    $comune = Organization::factory()->for($this->marco->family)->create();

    $this->actingAs($this->laura)->post(route('import.store'), ['files' => [
        importEntry($files[0], ['title' => 'Luce', 'organization_id' => $comune->id, 'tags' => ['bills']]),
        importEntry($files[1], ['title' => 'Gas']),
        importEntry($files[2], ['title' => 'Acqua', 'notes' => 'Paid']),
    ]])->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));

    expect(Document::query()->orderBy('id')->pluck('title')->all())->toBe(['Luce', 'Gas', 'Acqua'])
        ->and(Document::query()->where('file_id', $files[0]->id)->sole()->organization_id)->toBe($comune->id)
        ->and(File::query()->toSort()->count())->toBe(0);
});

test('a file left out stays in To sort', function (): void {
    $kept = waitingFile();
    $left = waitingFile();

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($kept)]])->assertSessionHasNoErrors();

    expect(Document::query()->count())->toBe(1)
        ->and($left->fresh()->to_sort)->toBeTrue()
        ->and($kept->fresh()->to_sort)->toBeFalse();
});

test('a file of another family in the body is a 422 and nothing is saved', function (): void {
    $mine = waitingFile();
    $foreign = File::factory()->create(['to_sort' => true]);

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($mine), importEntry($foreign)]])
        ->assertSessionHasErrors('files.1.file_id');

    expect(Document::query()->count())->toBe(0)->and($mine->fresh()->to_sort)->toBeTrue();
});

test('the rules of a document apply to each file and nothing is saved when one is wrong', function (): void {
    $one = waitingFile();
    $two = waitingFile();
    $foreignOrganization = Organization::factory()->create();

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [
        importEntry($one),
        importEntry($two, ['title' => '', 'organization_id' => $foreignOrganization->id]),
    ]])->assertSessionHasErrors(['files.1.document.title', 'files.1.document.organization_id']);

    expect(Document::query()->count())->toBe(0);
});

test('at least one file is needed', function (): void {
    $this->actingAs($this->marco)->post(route('import.store'), ['files' => []])->assertSessionHasErrors('files');
    $this->actingAs($this->marco)->post(route('import.store'))->assertSessionHasErrors('files');
});

test('a file already filed is not imported twice', function (): void {
    $file = waitingFile();

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($file)]])->assertSessionHasNoErrors();
    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($file)]])->assertSessionHasErrors('files.0.file_id');

    expect(Document::query()->count())->toBe(1);
});

test('the same file twice in one request makes one document and the row says why', function (): void {
    $file = waitingFile();
    $other = waitingFile();

    $response = $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($file), importEntry($file, ['title' => 'Again']), importEntry($other)]]);

    $response->assertSessionHasErrors('files.1.file_id');
    expect(Document::query()->count())->toBe(0);
});

test('a file that fails to save leaves the others saved, and the page says why', function (): void {
    $good = waitingFile();
    $bad = waitingFile();
    Document::creating(function (Document $document): void {
        if ($document->title === 'Boom') {
            throw new RuntimeException('The disk is full');
        }
    });

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($bad, ['title' => 'Boom']), importEntry($good)]])
        ->assertRedirect(route('import', ['files' => (string) $bad->id]))
        ->assertSessionHas('import_failures.'.$bad->id, 'This file could not be saved.');

    expect(Document::query()->count())->toBe(1)
        ->and(Document::query()->sole()->file_id)->toBe($good->id)
        ->and($bad->fresh()->to_sort)->toBeTrue();
});

test('a file filed by someone else while the others are saved is told, not imported twice', function (): void {
    $good = waitingFile();
    $taken = waitingFile();
    Document::created(function () use ($taken): void {
        File::query()->whereKey($taken->id)->update(['to_sort' => false]);
    });

    $this->actingAs($this->marco)->post(route('import.store'), ['files' => [importEntry($good), importEntry($taken)]])
        ->assertRedirect(route('documents.index'));

    expect(Document::query()->count())->toBe(1);
});

test('the failures come back to the page', function (): void {
    $file = waitingFile();

    $this->actingAs($this->marco)->withSession(['import_failures' => [$file->id => 'It could not be saved.']])
        ->get(route('import', ['files' => (string) $file->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('failures', [$file->id => 'It could not be saved.']));
});

test('guests are sent to sign in', function (): void {
    $this->get(route('import'))->assertRedirect(route('login'));
    $this->post(route('import.store'), [])->assertRedirect(route('login'));
});

test('to-sort read opens the import page with the files queued, copies left out', function (): void {
    $sum = hash('sha256', 'same');
    $original = waitingFile(['checksum' => $sum]);
    $copy = waitingFile(['checksum' => $sum]);
    $other = waitingFile(['checksum' => hash('sha256', 'other')]);

    $this->actingAs($this->marco)->post(route('to-sort.read'), ['ids' => [$original->id, $copy->id, $other->id]])
        ->assertRedirect(route('import', ['files' => "{$original->id},{$other->id}"]));
});

test('a member can import: members add documents', function (): void {
    $file = waitingFile();

    $this->actingAs($this->laura)->post(route('import.store'), ['files' => [importEntry($file)]])->assertSessionHasNoErrors();

    expect(Document::query()->count())->toBe(1);
});
