<?php

declare(strict_types=1);

use App\Contracts\DocumentReader;
use App\Enums\FileSource;
use App\Enums\ReadingStatus;
use App\Enums\Role;
use App\Jobs\ReadFile;
use App\Models\File;
use App\Models\Organization;
use App\Models\ReceivedMail;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeDocumentReader;

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
    app()->instance(DocumentReader::class, new FakeDocumentReader);
    $this->marco = registerFamily();
    $this->laura = User::factory()->for($this->marco->family)->create(['name' => 'Laura', 'role' => Role::Member, 'email_verified_at' => now()]);
});

/**
 * Makes a file waiting in the Bianchi family.
 *
 * @param  array<string, mixed>  $attributes
 */
function waiting(array $attributes = []): File
{
    return File::factory()->for(test()->marco->family)->create(['to_sort' => true, 'created_by' => test()->marco->id, ...$attributes]);
}

/**
 * The five files of the analysis: a phone photo and its copy, a notice by mail, a file of the council and a warranty.
 *
 * @return array<string, File>
 */
function bianchiToSort(): array
{
    $sum = hash('sha256', 'the pdf from the phone');

    return [
        'phone' => waiting(['original_name' => 'Telefono 2026-10-25 19.04.pdf', 'source' => FileSource::Phone, 'checksum' => $sum, 'created_by' => test()->laura->id]),
        'copy' => waiting(['original_name' => 'Telefono 2026-10-25 19.06.pdf', 'source' => FileSource::Phone, 'checksum' => $sum, 'created_by' => test()->laura->id]),
        'hera' => waiting(['original_name' => 'Avviso - La tua fattura è disponibile.txt', 'mime_type' => 'text/plain', 'source' => FileSource::Mail, 'reading_status' => ReadingStatus::Done, 'created_by' => null]),
        'tari' => waiting(['original_name' => 'Avviso TARI 2026.pdf', 'checksum' => hash('sha256', 'tari')]),
        'warranty' => waiting(['original_name' => 'Garanzia lavatrice.jpg', 'mime_type' => 'image/jpeg', 'checksum' => hash('sha256', 'warranty')]),
    ];
}

test('the example of the analysis: five files, one copy, four to sort', function (): void {
    $files = bianchiToSort();

    $this->actingAs($this->marco)->get(route('to-sort'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('documents/ToSort')
        ->where('to_sort_count', 4)
        ->where('source', null)
        ->where('filters', [
            ['value' => null, 'label' => 'All', 'count' => 5],
            ['value' => 'mail', 'label' => 'By mail', 'count' => 1],
            ['value' => 'phone', 'label' => 'From the phone', 'count' => 2],
            ['value' => 'upload', 'label' => 'Uploaded here', 'count' => 2],
        ])
        ->where('files.3.source_label', 'From the phone')
        ->where('files.2.source_label', 'By mail')
        ->has('files', 5)
        ->where('files.0.id', $files['warranty']->id)
        ->where('files.3.id', $files['copy']->id)
        ->where('files.3.copy_of', 'Telefono 2026-10-25 19.04.pdf')
        ->where('files.4.copy_of', null)
        ->where('files.1.id', $files['tari']->id)
        ->where('files.1.created_on', now()->timezone('Europe/Rome')->toDateString())
        ->where('files.2.reading_status', 'done'));
});

test('the filters show only the files of one source', function (): void {
    $files = bianchiToSort();

    $this->actingAs($this->marco)->get(route('to-sort', ['source' => 'mail']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('source', 'mail')
        ->has('files', 1)
        ->where('files.0.id', $files['hera']->id)
        ->where('filters.0.count', 5));

    $this->actingAs($this->marco)->get(route('to-sort', ['source' => 'phone']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('files', 2));
});

test('a source that does not exist shows everything', function (): void {
    bianchiToSort();

    $this->actingAs($this->marco)->get(route('to-sort', ['source' => 'fax']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('source', null)
        ->has('files', 5));
});

test('a source that is not a tab shows everything too', function (): void {
    bianchiToSort();
    waiting(['source' => FileSource::Scanner]);

    $this->actingAs($this->marco)->get(route('to-sort', ['source' => 'scanner']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('source', null)
        ->has('files', 6)
        ->where('filters.0.count', 6));
});

test('a source sent as a list shows everything instead of failing', function (): void {
    bianchiToSort();

    $this->actingAs($this->marco)->get('/to-sort?source[]=mail')->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('source', null)
        ->has('files', 5));
});

test('a source with no files has a zero and the others are not touched', function (): void {
    waiting(['source' => FileSource::Upload]);

    $this->actingAs($this->marco)->get(route('to-sort'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('filters.0.count', 1)->where('filters.1.count', 0)->where('filters.2.count', 0)->where('filters.3.count', 1));
});

test('only the files of the family waiting are listed', function (): void {
    waiting();
    File::factory()->create(['to_sort' => true]);
    File::factory()->for($this->marco->family)->create(['to_sort' => false]);

    $this->actingAs($this->marco)->get(route('to-sort'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('files', 1)->where('filters.0.count', 1));
});

test('a member can delete only his files and the copies', function (): void {
    $files = bianchiToSort();

    $this->actingAs($this->laura)->get(route('to-sort'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('files.4.id', $files['phone']->id)
        ->where('files.4.can_delete', true)
        ->where('files.3.id', $files['copy']->id)
        ->where('files.3.can_delete', true)
        ->where('files.0.can_delete', false)
        ->where('files.1.can_delete', false)
        ->where('files.2.can_delete', false));
});

test('a member deletes a copy someone else uploaded', function (): void {
    $files = bianchiToSort();
    $marcosCopy = waiting(['checksum' => hash('sha256', 'tari'), 'created_by' => $this->marco->id]);

    $this->actingAs($this->laura)->from(route('to-sort'))->delete(route('upload.destroy', $marcosCopy))
        ->assertRedirect(route('to-sort'));
    $this->actingAs($this->laura)->delete(route('upload.destroy', $files['tari']))->assertForbidden();

    expect($marcosCopy->fresh())->toBeNull()->and($files['tari']->fresh())->not->toBeNull();
});

test('an admin can delete every file', function (): void {
    bianchiToSort();

    $this->actingAs($this->marco)->get(route('to-sort'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('files.0.can_delete', true)->where('files.2.can_delete', true)->where('files.4.can_delete', true));
});

test('deleting a file goes back to the page it was deleted from', function (): void {
    $file = waiting();

    $this->actingAs($this->marco)->from(route('to-sort'))->delete(route('upload.destroy', $file))->assertRedirect(route('to-sort'));

    expect($file->fresh())->toBeNull();
});

test('read queues the files that are not read, and a file already read is not read again', function (): void {
    $files = bianchiToSort();

    $this->actingAs($this->laura)->post(route('to-sort.read'), ['ids' => [$files['hera']->id, $files['tari']->id, $files['warranty']->id]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('import', ['files' => "{$files['hera']->id},{$files['tari']->id},{$files['warranty']->id}"]));

    Queue::assertPushed(ReadFile::class, 2);
    expect($files['hera']->fresh()->reading_status)->toBe(ReadingStatus::Done)
        ->and($files['tari']->fresh()->reading_status)->toBe(ReadingStatus::Queued)
        ->and($files['warranty']->fresh()->reading_status)->toBe(ReadingStatus::Queued)
        ->and($files['phone']->fresh()->reading_status)->toBeNull();
});

test('read leaves out a copy', function (): void {
    $files = bianchiToSort();

    $this->actingAs($this->marco)->post(route('to-sort.read'), ['ids' => [$files['phone']->id, $files['copy']->id]])->assertSessionHasNoErrors();

    Queue::assertPushed(ReadFile::class, 1);
    expect($files['copy']->fresh()->reading_status)->toBeNull();
});

test('read does not touch a file that is no longer waiting, and goes back to the files to sort', function (): void {
    $filed = File::factory()->for($this->marco->family)->create(['to_sort' => false]);

    $this->actingAs($this->marco)->post(route('to-sort.read'), ['ids' => [$filed->id]])->assertSessionHasNoErrors()->assertRedirect(route('to-sort'));

    Queue::assertNothingPushed();
});

test('an id of another family in the body is refused with 422 and nothing is queued', function (): void {
    $mine = waiting();
    $foreign = File::factory()->create(['to_sort' => true]);

    $this->actingAs($this->marco)->post(route('to-sort.read'), ['ids' => [$mine->id, $foreign->id]])->assertSessionHasErrors('ids.1');

    Queue::assertNothingPushed();
});

test('read needs at least one file', function (): void {
    $this->actingAs($this->marco)->post(route('to-sort.read'), ['ids' => []])->assertSessionHasErrors('ids');
    $this->actingAs($this->marco)->post(route('to-sort.read'))->assertSessionHasErrors('ids');
});

test('the page and read need an account', function (): void {
    $this->post(route('to-sort.read'), ['ids' => [1]])->assertRedirect(route('login'));
    $this->get(route('to-sort'))->assertRedirect(route('login'));
});

/**
 * The first file of *To sort* as the page sends it.
 *
 * @return array<string, mixed>
 */
function firstSent(): array
{
    return test()->actingAs(test()->marco)->get(route('to-sort'))->viewData('page')['props']['files'][0];
}

test('the note of a file uploaded here says when and who', function (): void {
    waiting(['source' => FileSource::Upload, 'note' => null, 'created_at' => '2026-10-22 10:15:00']);

    expect(firstSent())->note->toBe('Uploaded here on 22/10/2026 by Marco')->short_note->toBe('Marco');
});

test('the note of a file from the phone carries the caption', function (): void {
    waiting(['source' => FileSource::Phone, 'note' => 'dentista Giulia', 'created_by' => $this->laura->id, 'created_at' => '2026-10-25 19:04:00']);

    expect(firstSent())->note->toBe('Uploaded from the phone on 25/10/2026 by Laura: «dentista Giulia»')->short_note->toBe('Laura · «dentista Giulia»');

    File::query()->update(['note' => null]);

    expect(firstSent())->note->toBe('Uploaded from the phone on 25/10/2026 by Laura')->short_note->toBe('Laura');
});

test('the note of a scanned file says who scanned it', function (): void {
    waiting(['source' => FileSource::Scanner, 'note' => null, 'created_by' => $this->laura->id, 'created_at' => '2026-10-25 19:04:00']);

    expect(firstSent())->note->toBe('Scanned on 25/10/2026 by Laura')->short_note->toBe('Laura');
});

test('the short note of a mail is the organization, then the sender', function (): void {
    $hera = Organization::factory()->for($this->marco->family)->create(['name' => 'Hera']);
    $mail = fn (array $attributes): ReceivedMail => ReceivedMail::factory()->for($this->marco->family)->create($attributes);
    $mailFile = fn (ReceivedMail $received, array $attributes = []): File => waiting(['source' => FileSource::Mail, 'note' => 'Saved note', 'created_by' => null, 'received_mail_id' => $received->id, ...$attributes]);

    $mailFile($mail(['organization_id' => $hera->id]));
    expect(firstSent())->short_note->toBe('Hera')->note->toBe('Saved note');

    $mailFile($mail(['sender' => 'Comune di Bologna', 'sender_address' => 'tributi@comune.bologna.it']));
    expect(firstSent())->short_note->toBe('Comune di Bologna');

    $mailFile($mail(['sender' => '', 'sender_address' => 'tributi@comune.bologna.it']));
    expect(firstSent())->short_note->toBe('tributi@comune.bologna.it');

    $mailFile($mail(['organization_id' => $hera->id]), ['mime_type' => 'text/plain']);
    expect(firstSent())->short_note->toBe('Hera · mail without attachment')->note->toBe('Saved note');
});

test('a file without who uploaded it keeps its saved note', function (): void {
    waiting(['source' => FileSource::Upload, 'created_by' => null, 'note' => 'Caricato manualmente il 01/02/2025 da Marco']);

    expect(firstSent())->note->toBe('Caricato manualmente il 01/02/2025 da Marco')->short_note->toBeNull();

    File::query()->update(['note' => null]);

    expect(firstSent())->note->toBeNull()->short_note->toBeNull();
});

test('the filter of the files uploaded here is called Uploaded here', function (): void {
    $this->actingAs($this->marco)->get(route('to-sort'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('filters.3.label', 'Uploaded here'));
});
