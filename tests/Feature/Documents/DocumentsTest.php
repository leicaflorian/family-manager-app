<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Employment;
use App\Models\Home;
use App\Models\User;
use App\Services\FileStore;
use App\Support\CurrentFamily;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $family = $this->marco->family;

    $this->employment = Employment::factory()->for($family)->create();
    $this->home = Home::factory()->for($family)->create(['name' => 'Via Roma 12']);
    $this->payslips = DocumentCategory::query()->where('name', 'Payslips')->sole();
    $this->electricity = DocumentCategory::query()->where('name', 'Electricity')->sole();

    $file = resolve(FileStore::class)->store(UploadedFile::fake()->create('Payslip.pdf', 40, 'application/pdf'));
    $this->document = Document::factory()->for($family)->create(['title' => 'Payslip of October', 'file_id' => $file->id, 'category_id' => $this->payslips->id, 'date' => '2026-10-23']);
    $this->document->employments()->attach($this->employment);
});

test('the list shows the documents with the filters and their choices', function (): void {
    Document::factory()->for($this->marco->family)->create(['title' => 'Electricity bill', 'category_id' => $this->electricity->id, 'date' => '2026-10-26']);

    $this->get(route('documents.index', ['category' => $this->payslips->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('documents/Documents')
        ->has('documents', 1)
        ->where('documents.0.title', 'Payslip of October')
        ->where('documents.0.category', 'Work')
        ->where('total', 1)
        ->where('filters.category', $this->payslips->id)
        ->where('hasDocuments', true)
        ->has('options.categories', 10)
        ->has('options.statuses', 3));
});

test('the list asks for valid filters', function (): void {
    $this->get(route('documents.index', ['status' => 'lost', 'category' => 'x']))->assertSessionHasErrors(['status', 'category']);
});

test('the page of a document shows its data, its file and its links', function (): void {
    $this->get(route('documents.show', $this->document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('documents/Document')
        ->where('document.title', 'Payslip of October')
        ->where('document.category', 'Work › Payslips')
        ->where('document.file.name', 'Payslip.pdf')
        ->where('document.file.url', route('files.show', $this->document->file_id))
        ->where('document.links.0.type', 'employment')
        ->where('document.tags', [])
        ->where('document.can_delete', true));
});

test('the change sets category and links', function (): void {
    $this->get(route('documents.edit', $this->document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('documents/EditDocument')
        ->where('document.id', $this->document->id)
        ->has('options.links', 2));

    $this->put(route('documents.update', $this->document), [
        'title' => 'Payslip of October 2026',
        'category_id' => $this->electricity->id,
        'status' => 'archived',
        'tags' => ['salary', '2026'],
        'links' => [['type' => 'home', 'id' => $this->home->id]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('documents.show', $this->document));

    $document = $this->document->fresh();
    expect($document)->title->toBe('Payslip of October 2026')->category_id->toBe($this->electricity->id)->tags->toBe(['salary', '2026'])
        ->and($document->employments()->count())->toBe(0)
        ->and($document->homes()->pluck('homes.id')->all())->toBe([$this->home->id]);
});

test('the deletion by an admin removes the document, its links and its file', function (): void {
    $path = $this->document->file->path;

    $this->delete(route('documents.destroy', $this->document))->assertRedirect(route('documents.index'));

    expect(Document::query()->find($this->document->id))->toBeNull()
        ->and(DB::table('documentables')->where('document_id', $this->document->id)->count())->toBe(0)
        ->and(DB::table('files')->where('id', $this->document->file_id)->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

test('a member who calls the deletion gets 403, and the document stays', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['email_verified_at' => now()]);

    $this->actingAs($member)->delete(route('documents.destroy', $this->document))->assertForbidden();

    expect($this->document->fresh())->not->toBeNull();
    $this->actingAs($member)->get(route('documents.show', $this->document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('document.can_delete', false));
});

test('a document of the Rossis answers 404 to the Bianchis', function (): void {
    $rossi = registerFamily('Rossi', 'anna@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): Document => Document::factory()->for($rossi->family)->create());

    $this->get(route('documents.show', $theirs))->assertNotFound();
    $this->get(route('documents.edit', $theirs))->assertNotFound();
    $this->put(route('documents.update', $theirs), ['title' => 'Mine', 'status' => 'active'])->assertNotFound();
    $this->delete(route('documents.destroy', $theirs))->assertNotFound();
});

test('without documents the list says so', function (): void {
    $this->document->delete();

    $this->get(route('documents.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('documents', 0)
        ->where('hasDocuments', false));
});

test('the file of a document has a preview address only when it has a preview', function (): void {
    $file = $this->document->file;
    $this->get(route('documents.show', $this->document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('document.file.preview_url', route('files.preview', $file)));

    $file->forceFill(['preview_failed' => true])->save();
    $this->get(route('documents.show', $this->document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('document.file.preview_url', null));

    $file->forceFill(['preview_failed' => false, 'mime_type' => 'image/png'])->save();
    $this->get(route('documents.show', $this->document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('document.file.preview_url', null));
});
