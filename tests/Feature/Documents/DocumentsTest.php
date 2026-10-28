<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Employment;
use App\Models\Expense;
use App\Models\Home;
use App\Models\User;
use App\Models\Vehicle;
use App\Queries\FilterDocuments;
use App\Services\FileStore;
use App\Support\CurrentFamily;
use Database\Seeders\BianchiFamilySeeder;
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

test('the list shows 15 documents a page, with the address of the next one', function (): void {
    Document::factory()->for($this->marco->family)->count(15)->create();

    $this->get(route('documents.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('documents', 15)
        ->where('total', 16)
        ->where('nextPage', route('documents.index', ['page' => 2]))
        ->where('previousPage', null));
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

test('a link leads to a card page only when the record has one', function (): void {
    $expense = Expense::factory()->for($this->marco->family)->create();
    DB::table('documentables')->insert([
        ['document_id' => $this->document->id, 'documentable_type' => 'home', 'documentable_id' => $this->home->id],
        ['document_id' => $this->document->id, 'documentable_type' => 'expense', 'documentable_id' => $expense->id],
    ]);

    $this->get(route('documents.show', $this->document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('document.links', fn ($links): bool => collect($links)->mapWithKeys(fn ($link): array => [$link['type'] => $link['href']])->all() === [
            'home' => '/cards/home/'.$this->home->id,
            'employment' => '/cards/employment/'.$this->employment->id,
            'expense' => null,
        ]));
});

test('the form proposes the tags already used in the family, once each and in order', function (): void {
    $this->document->update(['tags' => ['salary', '2026']]);
    Document::factory()->for($this->marco->family)->create(['tags' => ['Bills', 'salary']]);
    Document::factory()->for($this->marco->family)->create(['tags' => null]);
    Document::factory()->for(registerFamily('Rossi', 'anna@example.com')->family)->create(['tags' => ['secret']]);

    $this->get(route('documents.edit', $this->document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('options.tags', ['2026', 'Bills', 'salary']));
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

test('the file is changed: the new one takes the place of the previous one, which goes', function (): void {
    $previous = $this->document->file;

    $this->post(route('documents.file', $this->document), ['file' => UploadedFile::fake()->create('Payslip of October.pdf', 50, 'application/pdf')])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('documents.edit', $this->document));

    $file = $this->document->fresh()?->file;
    expect($file?->original_name)->toBe('Payslip of October.pdf')
        ->and($file?->to_sort)->toBeFalse()
        ->and(DB::table('files')->where('id', $previous->id)->count())->toBe(0);
    Storage::disk('local')->assertMissing($previous->path);
    Storage::disk('local')->assertExists((string) $file?->path);
});

test('the new file is a PDF, JPG, PNG or HEIC, and the previous one stays otherwise', function (): void {
    $this->post(route('documents.file', $this->document), ['file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain')])
        ->assertSessionHasErrors(['file' => 'Only PDF, JPG, PNG and HEIC files can be uploaded.']);

    expect($this->document->fresh()?->file_id)->toBe($this->document->file_id);
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
    $this->post(route('documents.file', $theirs), ['file' => UploadedFile::fake()->create('mine.pdf', 10, 'application/pdf')])->assertNotFound();
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

test('it filters the documents of one record', function (): void {
    $this->seed(BianchiFamilySeeder::class);

    // This file's own family is also called Bianchi, so the seeded one is found by its account.
    $seeded = User::query()->where('email', 'marco.bianchi@example.com')->sole()->family_id;

    $titles = CurrentFamily::using($seeded, function (): array {
        $panda = Vehicle::query()->where('name', 'Fiat Panda')->sole();

        return resolve(FilterDocuments::class)
            ->apply(Document::query(), ['record' => ['type' => 'vehicle', 'id' => $panda->id]])
            ->pluck('title')->all();
    });

    expect($titles)->toBe(['Polizza RC auto 2026']);
});
