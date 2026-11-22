<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Jobs\ReadFile;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\File;
use App\Models\Person;
use App\Models\User;
use App\Queries\FilterDocuments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $this->drawingFamily = $this->marco->family;
    $this->drawingCategory = DocumentCategory::query()->where('name', 'Drawings and crafts')->sole();
    $this->drawingGiulia = Person::factory()->for($this->drawingFamily)->create(['first_name' => 'Giulia']);
});

it('stores the drawing in the gallery category without a reading', function (): void {
    $this->post(route('documents.gallery.store'), [
        'file' => UploadedFile::fake()->image('sun.jpg'),
        'person_id' => $this->drawingGiulia->id,
        'title' => 'The sun',
        'notes' => 'At school',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $document = Document::query()->sole();
    expect($document->title)->toBe('The sun')
        ->and($document->category_id)->toBe($this->drawingCategory->id)
        ->and($document->person_id)->toBe($this->drawingGiulia->id)
        ->and($document->notes)->toBe('At school')
        ->and($document->status->value)->toBe('active')
        ->and(File::query()->sole()->to_sort)->toBeFalse();
    Queue::assertNotPushed(ReadFile::class);
    Queue::assertNothingPushed();
});

it('names an empty title after the child', function (): void {
    $this->post(route('documents.gallery.store'), ['file' => UploadedFile::fake()->image('a.png'), 'person_id' => $this->drawingGiulia->id])
        ->assertSessionHasNoErrors();
    $this->post(route('documents.gallery.store'), ['file' => UploadedFile::fake()->image('b.png'), 'title' => ' '])
        ->assertSessionHasNoErrors();

    expect(Document::query()->orderBy('id')->pluck('title')->all())->toBe(['Drawing by Giulia', 'Drawing']);
});

it('answers 422 without a gallery category', function (): void {
    $this->drawingCategory->update(['gallery' => false]);

    $this->post(route('documents.gallery.store'), ['file' => UploadedFile::fake()->image('a.png')])->assertSessionHasErrors('file');

    expect(Document::query()->count())->toBe(0)->and(File::query()->count())->toBe(0);
});

it('answers 422 for a person of another family', function (): void {
    $stranger = User::factory()->create();
    $foreign = Person::factory()->create(['family_id' => $stranger->family_id]);

    $this->post(route('documents.gallery.store'), ['file' => UploadedFile::fake()->image('a.png'), 'person_id' => $foreign->id])
        ->assertSessionHasErrors('person_id');

    expect(Document::query()->count())->toBe(0);
});

it('lets a member add', function (): void {
    $this->actingAs(User::factory()->for($this->drawingFamily)->create(['role' => Role::Member]));

    $this->post(route('documents.gallery.store'), ['file' => UploadedFile::fake()->image('a.png')])->assertSessionHasNoErrors();

    expect(Document::query()->count())->toBe(1);
});

it('requires a valid file', function (): void {
    $this->post(route('documents.gallery.store'), [])->assertSessionHasErrors('file');
    $this->post(route('documents.gallery.store'), ['file' => UploadedFile::fake()->create('a.zip', 10, 'application/zip')])->assertSessionHasErrors('file');
});

it('keeps the new drawing out of Documents', function (): void {
    $this->post(route('documents.gallery.store'), ['file' => UploadedFile::fake()->image('a.png')])->assertSessionHasNoErrors();

    $inList = resolve(FilterDocuments::class)->apply(Document::query(), ['gallery' => false])->count();
    $inGallery = resolve(FilterDocuments::class)->apply(Document::query(), ['gallery' => true])->count();

    expect($inList)->toBe(0)->and($inGallery)->toBe(1);
});
