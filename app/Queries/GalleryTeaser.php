<?php

declare(strict_types=1);

namespace App\Queries;

use App\Data\GalleryTeaserData;
use App\Models\Document;
use App\Models\Person;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the card of the gallery on *Documents* shows, read from the drawings of the family.
 */
final readonly class GalleryTeaser
{
    /** How many pictures the card shows. */
    public const int THUMBNAILS = 4;

    public function __construct(private FilterDocuments $filter) {}

    /**
     * The card of the gallery.
     *
     * @return GalleryTeaserData|null null when there is no gallery category or no drawing in it
     */
    public function for(): ?GalleryTeaserData
    {
        $count = $this->drawings()->count();

        if ($count === 0) {
            return null;
        }

        $newest = $this->drawings()
            ->join('files', 'files.id', '=', 'documents.file_id')
            ->with('file')
            ->reorder()
            ->orderByDesc('files.created_at')
            ->orderByDesc('documents.id')
            ->limit(self::THUMBNAILS)
            ->get();
        $last = $newest->first()?->file->created_at;

        /** @var list<string> $names */
        $names = Person::query()
            ->whereIn('id', $this->drawings()->whereNotNull('person_id')->reorder()->distinct()->select('person_id'))
            ->orderBy('first_name')
            ->pluck('first_name')
            ->all();

        return new GalleryTeaserData(
            count: $count,
            people: $names,
            last_uploaded_on: $last === null ? null : CarbonImmutable::parse($last)->setTimezone('Europe/Rome')->toDateString(),
            thumbnails: array_values($newest->map(static fn (Document $document): string => $document->file->imageUrl())->all()),
            url: route('documents.gallery'),
        );
    }

    /**
     * Whether a person has a drawing in the gallery.
     *
     * @param  int  $personId  the person
     */
    public function personHasDrawings(int $personId): bool
    {
        return $this->drawings()->where('person_id', $personId)->exists();
    }

    /**
     * The documents of the gallery.
     *
     * @return Builder<Document>
     */
    private function drawings(): Builder
    {
        return $this->filter->apply(Document::query()->select('documents.*'), ['gallery' => true]);
    }
}
