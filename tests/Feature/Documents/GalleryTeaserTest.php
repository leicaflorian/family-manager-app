<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\File;
use App\Models\Person;
use App\Queries\GalleryTeaser;

beforeEach(function (): void {
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $this->teaserFamily = $this->marco->family;
    $this->teaserCategory = DocumentCategory::query()->where('name', 'Drawings and crafts')->sole();
    $this->teaserCategory->update(['gallery' => true]);
});

/**
 * A drawing of the gallery category, uploaded on a given day.
 *
 * @param  array<string, mixed>  $document  the document attributes
 */
function galleryTeaserDrawing(string $uploadedAt, array $document = []): Document
{
    $file = File::factory()->create([
        'family_id' => test()->teaserFamily->id,
        'mime_type' => 'image/jpeg',
        'created_at' => $uploadedAt,
    ]);

    return Document::factory()->create([
        'family_id' => test()->teaserFamily->id,
        'category_id' => test()->teaserCategory->id,
        'file_id' => $file->id,
        ...$document,
    ]);
}

it('sums the drawings, the people and the last upload, with the four newest thumbnails', function (): void {
    $giulia = Person::factory()->for($this->teaserFamily)->create(['first_name' => 'Giulia']);
    $luca = Person::factory()->for($this->teaserFamily)->create(['first_name' => 'Luca']);
    $drawings = [
        galleryTeaserDrawing('2026-01-10 10:00:00', ['person_id' => $giulia->id]),
        galleryTeaserDrawing('2026-02-10 10:00:00', ['person_id' => $luca->id]),
        galleryTeaserDrawing('2026-03-10 10:00:00', ['person_id' => $giulia->id]),
        galleryTeaserDrawing('2026-04-10 10:00:00'),
        galleryTeaserDrawing('2026-05-10 10:00:00'),
    ];

    $teaser = resolve(GalleryTeaser::class)->for();

    expect($teaser?->count)->toBe(5)
        ->and($teaser->people)->toBe(['Giulia', 'Luca'])
        ->and($teaser->last_uploaded_on)->toBe('2026-05-10')
        ->and($teaser->thumbnails)->toHaveCount(4)
        ->and($teaser->thumbnails[0])->toBe($drawings[4]->file->imageUrl())
        ->and($teaser->thumbnails[3])->toBe($drawings[1]->file->imageUrl())
        ->and($teaser->url)->toBe(route('documents.gallery'));
});

it('is empty with a gallery category and no drawings, null without a gallery category', function (): void {
    $teaser = resolve(GalleryTeaser::class)->for();
    expect($teaser?->count)->toBe(0)
        ->and($teaser->people)->toBe([])
        ->and($teaser->last_uploaded_on)->toBeNull()
        ->and($teaser->thumbnails)->toBe([])
        ->and($teaser->url)->toBe(route('documents.gallery'));

    galleryTeaserDrawing('2026-01-10 10:00:00');
    expect(resolve(GalleryTeaser::class)->for()?->count)->toBe(1);

    DocumentCategory::query()->update(['gallery' => false]);
    expect(resolve(GalleryTeaser::class)->for())->toBeNull();
});
