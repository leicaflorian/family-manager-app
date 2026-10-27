<?php

declare(strict_types=1);

namespace App\Queries;

use App\Data\DocumentData;
use App\Models\Document;
use App\Services\CategoryTree;
use Illuminate\Database\Eloquent\Collection;

/**
 * The rows of a list of documents, as *Documents* shows them, for the pages
 * that list some of them (*Home*, *A person*, *An organization*).
 */
final readonly class DocumentRows
{
    /** What a row reads besides the document. */
    private const array RELATIONS = ['person', 'organization', 'file'];

    public function __construct(private FilterDocuments $filter, private CategoryTree $categories) {}

    /**
     * The documents that pass the filters, with what their rows show.
     *
     * @param  array<string, mixed>  $filters  the filters of `FilterDocuments::apply()`
     * @param  bool  $byExpiry  the first to expire first, instead of the most recent first
     * @return list<DocumentData>
     */
    public function of(array $filters, bool $byExpiry = false): array
    {
        $query = $this->filter->apply(Document::query()->with(self::RELATIONS), $filters);

        if ($byExpiry) {
            $query->reorder('expiry_date');
        }

        return $this->rows($query->get());
    }

    /**
     * The last documents uploaded, the most recent first.
     *
     * @param  int  $count  how many
     * @return list<DocumentData>
     */
    public function latest(int $count): array
    {
        return $this->rows(Document::query()->with(self::RELATIONS)->latest()->orderByDesc('id')->limit($count)->get());
    }

    /**
     * The rows of some documents, in their order.
     *
     * @param  Collection<int, Document>  $documents  the documents, with the relations of `RELATIONS`
     * @return list<DocumentData>
     */
    private function rows(Collection $documents): array
    {
        $paths = $this->categories->paths();

        return array_values($documents->map(static fn (Document $document): DocumentData => DocumentData::fromDocument($document, $paths))->all());
    }
}
