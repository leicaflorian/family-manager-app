<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\File;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $this->galleryFamily = $this->marco->family;
    $this->galleryCategory = DocumentCategory::query()->where('name', 'Drawings and crafts')->sole();
    $this->giulia = Person::factory()->for($this->galleryFamily)->create(['first_name' => 'Giulia', 'birth_date' => '2016-05-24']);
    $this->luca = Person::factory()->for($this->galleryFamily)->create(['first_name' => 'Luca', 'birth_date' => null]);
});

/**
 * A drawing of the Bianchi gallery, uploaded on a given day.
 *
 * @param  array<string, mixed>  $document  the document attributes
 * @param  array<string, mixed>  $file  the file attributes
 */
function galleryDrawing(string $uploadedAt, array $document = [], array $file = []): Document
{
    $stored = File::factory()->create([
        'family_id' => test()->galleryFamily->id,
        'mime_type' => 'image/jpeg',
        'created_at' => $uploadedAt,
        ...$file,
    ]);

    return Document::factory()->create([
        'family_id' => test()->galleryFamily->id,
        'category_id' => test()->galleryCategory->id,
        'file_id' => $stored->id,
        ...$document,
    ]);
}

it('groups the drawings by upload year, newest first', function (): void {
    $old = galleryDrawing('2025-03-10 10:00:00', ['title' => 'Sun']);
    $first = galleryDrawing('2026-02-01 10:00:00', ['title' => 'House']);
    $second = galleryDrawing('2026-06-01 10:00:00', ['title' => 'Cat']);

    $this->get(route('documents.gallery'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('documents/Gallery')
        ->where('hasGallery', true)
        ->where('total', 3)
        ->has('years', 2)
        ->where('years.0.year', 2026)
        ->where('years.0.count', 2)
        ->where('years.0.drawings.0.id', $second->id)
        ->where('years.0.drawings.1.id', $first->id)
        ->where('years.1.year', 2025)
        ->where('years.1.count', 1)
        ->where('years.1.drawings.0.id', $old->id)
        ->where('years.0.drawings.0.image_url', route('files.preview', $second->file_id))
        ->where('years.0.drawings.0.file_url', route('files.show', $second->file_id))
        ->where('years.0.drawings.0.download_url', route('files.download', $second->file_id))
        ->where('years.0.drawings.0.document_url', route('documents.show', $second)));
});

it('filters the favourites and the person', function (): void {
    $a = galleryDrawing('2026-02-01 10:00:00', ['person_id' => $this->giulia->id, 'is_favorite' => true]);
    $b = galleryDrawing('2026-02-02 10:00:00', ['person_id' => $this->giulia->id]);
    $c = galleryDrawing('2026-02-03 10:00:00', ['person_id' => $this->luca->id, 'is_favorite' => true]);

    $this->get(route('documents.gallery', ['filter' => 'favorites']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('total', 2)
        ->where('filters.filter', 'favorites')
        ->where('years.0.drawings', fn ($drawings): bool => collect($drawings)->pluck('id')->sort()->values()->all() === collect([$a->id, $c->id])->sort()->values()->all()));

    $this->get(route('documents.gallery', ['person' => $this->giulia->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('total', 2)
        ->where('filters.person', $this->giulia->id)
        ->where('years.0.drawings', fn ($drawings): bool => collect($drawings)->pluck('id')->sort()->values()->all() === collect([$a->id, $b->id])->sort()->values()->all()));

    $this->get(route('documents.gallery', ['filter' => 'favorites', 'person' => $this->giulia->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('total', 1)->where('years.0.drawings.0.id', $a->id));
});

it('lists as people only those with drawings, by name', function (): void {
    $anna = Person::factory()->for($this->galleryFamily)->create(['first_name' => 'Anna']);
    Person::factory()->for($this->galleryFamily)->create(['first_name' => 'Zoe']);
    galleryDrawing('2026-02-01 10:00:00', ['person_id' => $this->luca->id]);
    galleryDrawing('2026-02-02 10:00:00', ['person_id' => $anna->id]);
    galleryDrawing('2026-02-03 10:00:00', ['person_id' => null]);

    $this->get(route('documents.gallery'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('people', 2)
        ->where('people.0', ['id' => $anna->id, 'name' => 'Anna'])
        ->where('people.1', ['id' => $this->luca->id, 'name' => 'Luca'])
        ->where('addOptions.people', fn ($people): bool => collect($people)->pluck('name')->contains('Zoe') && collect($people)->pluck('name')->contains('Luca')));
});

it('selects the drawing asked, else null, also for one of another family or outside the gallery', function (): void {
    $drawing = galleryDrawing('2026-02-01 10:00:00', ['title' => 'House']);
    $elsewhere = Document::factory()->for($this->galleryFamily)->create();
    $stranger = User::factory()->create();
    $foreign = CurrentFamily::using($stranger->family_id, function () use ($stranger): Document {
        $category = DocumentCategory::query()->create(['name' => 'Their gallery', 'gallery' => true]);

        return Document::factory()->create(['family_id' => $stranger->family_id, 'category_id' => $category->id]);
    });

    $this->get(route('documents.gallery', ['drawing' => $drawing->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('selected.id', $drawing->id)->where('selected.title', 'House'));

    foreach ([$elsewhere->id, $foreign->id, 999999] as $id) {
        $this->get(route('documents.gallery', ['drawing' => $id]))->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('selected', null)->where('total', 1));
    }
});

it('gives the age on the upload day, null without a birth date', function (): void {
    galleryDrawing('2026-05-23 10:00:00', ['person_id' => $this->giulia->id, 'title' => 'Before']);
    galleryDrawing('2026-05-24 23:30:00', ['person_id' => $this->giulia->id, 'title' => 'On']);
    galleryDrawing('2026-05-25 10:00:00', ['person_id' => $this->luca->id, 'title' => 'No birth']);
    galleryDrawing('2026-05-26 10:00:00', ['person_id' => null, 'title' => 'Nobody']);

    $this->get(route('documents.gallery'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('years.0.drawings', function ($drawings): bool {
            $byTitle = collect($drawings)->keyBy('title');

            return $byTitle['Before']['age'] === 9
                && $byTitle['On']['age'] === 10
                && $byTitle['On']['person_name'] === 'Giulia'
                && $byTitle['No birth']['age'] === null
                && $byTitle['Nobody']['age'] === null
                && $byTitle['Nobody']['person_name'] === null;
        }));
});

it('gives the upload day in the Rome time zone', function (): void {
    // The row is stored as 23:30 UTC on 31 December, which is already 1 January in Rome
    $previous = date_default_timezone_get();
    date_default_timezone_set('UTC');
    config(['app.timezone' => 'UTC']);

    try {
        galleryDrawing('2025-12-31 23:30:00', ['title' => 'New year']);
        $response = $this->get(route('documents.gallery'));
    } finally {
        date_default_timezone_set($previous);
    }

    $response->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('years.0.year', 2026)
        ->where('years.0.drawings.0.uploaded_on', '2026-01-01'));
});

it('takes pages from the page count', function (): void {
    galleryDrawing('2026-02-01 10:00:00', ['title' => 'Booklet'], ['mime_type' => 'application/pdf', 'page_count' => 4]);
    galleryDrawing('2026-02-02 10:00:00', ['title' => 'Single']);

    $this->get(route('documents.gallery'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('years.0.drawings', function ($drawings): bool {
            $byTitle = collect($drawings)->keyBy('title');

            return $byTitle['Booklet']['pages'] === 4 && $byTitle['Single']['pages'] === null;
        }));
});

it('is empty without a gallery category', function (): void {
    galleryDrawing('2026-02-01 10:00:00');
    $this->galleryCategory->update(['gallery' => false]);

    $this->get(route('documents.gallery'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('hasGallery', false)
        ->where('total', 0)
        ->where('years', [])
        ->where('people', [])
        ->where('selected', null));
});

it('shows a member that she can add and favourite', function (): void {
    $this->actingAs(User::factory()->for($this->galleryFamily)->create(['role' => Role::Member]));
    galleryDrawing('2026-02-01 10:00:00');

    $this->get(route('documents.gallery'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('canAdd', true)
        ->where('canManageCategories', false)
        ->where('years.0.drawings.0.can_favorite', true));
});

it('tells an admin that she can choose the gallery category', function (): void {
    $this->get(route('documents.gallery'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('canManageCategories', true));
});

it('ignores filter values that are not valid, without leaving the page', function (array $query): void {
    $drawing = galleryDrawing('2026-02-01 10:00:00', ['person_id' => $this->giulia->id]);

    $this->get(route('documents.gallery', $query))
        ->assertOk()
        ->assertSessionHasNoErrors()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('filters', ['filter' => 'all', 'person' => null])
            ->where('total', 1)
            ->where('selected', null)
            ->where('years.0.drawings.0.id', $drawing->id));
})->with([
    'a filter that does not exist' => [['filter' => 'nothing']],
    'a person that is not a number' => [['person' => 'giulia']],
    'a drawing that is not a number' => [['drawing' => 'abc']],
    'a list instead of a value' => [['person' => ['1'], 'drawing' => ['2'], 'filter' => ['favorites']]],
]);

it('does not take the heart from a mass assignment, only from the favourite action', function (): void {
    $drawing = galleryDrawing('2026-02-01 10:00:00');

    $drawing->fill(['is_favorite' => true, 'title' => 'The sun'])->save();

    expect($drawing->fresh()?->is_favorite)->toBeFalse()
        ->and($drawing->fresh()?->title)->toBe('The sun');
});

it('marks and unmarks a favourite', function (): void {
    $drawing = galleryDrawing('2026-02-01 10:00:00');

    $this->from('/back')->put(route('documents.favorite', $drawing))->assertRedirect('/back');
    expect($drawing->fresh()->is_favorite)->toBeTrue();

    $this->delete(route('documents.unfavorite', $drawing))->assertRedirect();
    expect($drawing->fresh()->is_favorite)->toBeFalse();
});

it('lets a member mark a favourite', function (): void {
    $drawing = galleryDrawing('2026-02-01 10:00:00');
    $this->actingAs(User::factory()->for($this->galleryFamily)->create(['role' => Role::Member]));

    $this->put(route('documents.favorite', $drawing))->assertRedirect();

    expect($drawing->fresh()->is_favorite)->toBeTrue();
});

it('refuses a favourite outside the gallery', function (): void {
    $document = Document::factory()->for($this->galleryFamily)->create();

    $this->put(route('documents.favorite', $document))->assertSessionHasErrors('document');

    expect($document->fresh()->is_favorite)->toBeFalse();
});

it('answers 404 for the favourite of another family', function (): void {
    $stranger = User::factory()->create();
    $foreign = Document::factory()->create(['family_id' => $stranger->family_id]);

    $this->put(route('documents.favorite', $foreign))->assertNotFound();
    $this->delete(route('documents.unfavorite', $foreign))->assertNotFound();
});

it('shares whether the family has a gallery, for the sidebar', function (): void {
    $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('has_gallery', true));

    $this->galleryCategory->update(['gallery' => false]);

    $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('has_gallery', false));
});
