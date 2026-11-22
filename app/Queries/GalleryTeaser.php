<?php

declare(strict_types=1);

namespace App\Queries;

use App\Data\GalleryTeaserData;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Person;
use App\Services\CategoryTree;

/**
 * What the card of the gallery on *Documents* shows, read from the drawings of the family.
 */
final readonly class GalleryTeaser
{
    /** How many pictures the card shows. */
    public const int THUMBNAILS = 4;

    public function __construct(private GalleryDrawings $drawings, private CategoryTree $categories) {}

    /**
     * The card of the gallery.
     *
     * @return GalleryTeaserData|null null when there is no gallery category; with no drawing yet, a card with count 0
     */
    public function for(): ?GalleryTeaserData
    {
        if (! $this->categories->galleryCategory() instanceof DocumentCategory) {
            return null;
        }

        $drawings = $this->drawings->query();
        $count = (clone $drawings)->count();

        if ($count === 0) {
            return new GalleryTeaserData(count: 0, people: [], last_uploaded_on: null, thumbnails: [], url: route('documents.gallery'));
        }

        $newest = (clone $drawings)->limit(self::THUMBNAILS)->get();
        $last = $newest->first()?->file->uploadedAt();

        /** @var list<string> $names */
        $names = Person::query()
            ->whereIn('id', (clone $drawings)->whereNotNull('person_id')->reorder()->distinct()->select('person_id'))
            ->orderBy('first_name')
            ->pluck('first_name')
            ->all();

        return new GalleryTeaserData(
            count: $count,
            people: $names,
            last_uploaded_on: $last?->toDateString(),
            thumbnails: array_values($newest->map(static fn (Document $document): string => $document->file->imageUrl())->all()),
            url: route('documents.gallery'),
        );
    }

    /**
     * How many drawings of a person are in the gallery.
     *
     * @param  int  $personId  the person
     */
    public function personDrawingsCount(int $personId): int
    {
        return $this->drawings->query($personId)->count();
    }
}
