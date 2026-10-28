<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Employment;
use App\Models\File;
use App\Models\Organization;
use App\Models\Person;
use App\Support\CurrentFamily;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $family = $this->marco->family;

    $this->laura = Person::factory()->for($family)->create(['first_name' => 'Laura', 'last_name' => 'Bianchi']);
    $this->comune = Organization::factory()->for($family)->create(['name' => 'Comune di Bologna']);
    $this->employment = Employment::factory()->for($family)->create(['person_id' => $this->laura->id, 'organization_id' => $this->comune->id]);
    $this->payslips = DocumentCategory::query()->where('name', 'Payslips')->sole();
});

/**
 * Uploads one file and returns the row the upload created.
 */
function uploadOne(string $name, int $kilobytes = 120): File
{
    test()->post(route('upload.store'), ['files' => [UploadedFile::fake()->create($name, $kilobytes, 'application/pdf')]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('upload'));

    return File::query()->latest('id')->firstOrFail();
}

test('an uploaded file waits with the title taken from its name', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');

    Storage::disk('local')->assertExists($file->path);
    $this->get(route('upload'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('documents/Upload')
        ->has('pending', 1)
        ->where('pending.0.id', $file->id)
        ->where('pending.0.name', 'Busta paga novembre 2026.pdf')
        ->where('pending.0.title', 'Busta paga novembre 2026')
        ->has('links', 1)
        ->where('links.0.type', 'employment')
        ->where('links.0.options.0.value', (string) $this->employment->id)
        ->where('links.0.options.0.label', 'Laura - Comune di Bologna'));
});

test('saved with the employment of Laura, the document is among its documents', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');

    $this->post(route('documents.store'), [
        'file_id' => $file->id,
        'title' => 'Busta paga novembre 2026',
        'category_id' => $this->payslips->id,
        'person_id' => $this->laura->id,
        'organization_id' => $this->comune->id,
        'date' => '2026-11-27',
        'status' => 'active',
        'links' => [['type' => 'employment', 'id' => $this->employment->id]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));

    $document = Document::query()->where('title', 'Busta paga novembre 2026')->sole();
    expect($document)->file_id->toBe($file->id)->category_id->toBe($this->payslips->id)->person_id->toBe($this->laura->id)
        ->and($this->employment->documents()->pluck('documents.id')->all())->toBe([$document->id]);
    $this->get(route('upload'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('pending', 0));
});

test('with another file still waiting, saving goes back to Upload', function (): void {
    $first = uploadOne('Busta paga novembre 2026.pdf');
    uploadOne('Busta paga dicembre 2026.pdf');

    $this->post(route('documents.store'), ['file_id' => $first->id, 'title' => 'Busta paga novembre 2026', 'status' => 'active'])
        ->assertSessionHasNoErrors()->assertRedirect(route('upload'));
});

test('an organization of another family is a validation error', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');
    $rossi = registerFamily('Rossi', 'anna@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): Organization => Organization::factory()->for($rossi->family)->create(['name' => 'Comune di Roma']));

    $this->post(route('documents.store'), ['file_id' => $file->id, 'title' => 'Busta paga', 'status' => 'active', 'organization_id' => $theirs->id])
        ->assertSessionHasErrors('organization_id');

    expect(Document::query()->count())->toBe(0);
});

test('a link to a record of another family, or of a type without documents, is a validation error', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');
    $rossi = registerFamily('Rossi', 'anna@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): Employment => Employment::factory()->for($rossi->family)->create());

    $this->post(route('documents.store'), [
        'file_id' => $file->id,
        'title' => 'Busta paga',
        'status' => 'active',
        'links' => [['type' => 'employment', 'id' => $theirs->id], ['type' => 'person', 'id' => $this->laura->id]],
    ])->assertSessionHasErrors(['links.0.id', 'links.1.type']);
});

test('a file that already has its document is not filed twice', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');
    $this->post(route('documents.store'), ['file_id' => $file->id, 'title' => 'Busta paga', 'status' => 'active'])->assertSessionHasNoErrors();

    $this->post(route('documents.store'), ['file_id' => $file->id, 'title' => 'Busta paga again', 'status' => 'active'])
        ->assertSessionHasErrors('file_id');
});

test('the title is required', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');

    $this->post(route('documents.store'), ['file_id' => $file->id, 'title' => '', 'status' => 'active'])->assertSessionHasErrors('title');
});

test('a file of 26 MB is rejected with a message in Italian', function (): void {
    $this->withHeader('Accept-Language', 'it')
        ->post(route('upload.store'), ['files' => [UploadedFile::fake()->create('Scansione.pdf', 26 * 1024, 'application/pdf')]])
        ->assertSessionHasErrors(['files.0' => 'Ogni file può essere al massimo di 25 MB.']);

    expect(File::query()->count())->toBe(0);
});

test('a waiting file that is discarded leaves the disk too', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');

    $this->delete(route('upload.destroy', $file))->assertRedirect(route('upload'));

    expect($file->fresh())->toBeNull();
    Storage::disk('local')->assertMissing($file->path);
});

test('a file that has its document is not discarded from Upload', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');
    $this->post(route('documents.store'), ['file_id' => $file->id, 'title' => 'Busta paga', 'status' => 'active']);

    $this->delete(route('upload.destroy', $file))->assertNotFound();

    expect($file->fresh())->not->toBeNull();
});

test('a waiting file has a preview address only when it has a preview', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');
    $this->get(route('upload'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('pending.0.preview_url', route('files.preview', $file)));

    $file->forceFill(['preview_failed' => true])->save();
    $this->get(route('upload'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('pending.0.preview_url', null));

    $file->forceFill(['preview_failed' => false, 'mime_type' => 'image/png'])->save();
    $this->get(route('upload'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('pending.0.preview_url', null));
});
