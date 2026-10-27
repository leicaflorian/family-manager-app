<?php

declare(strict_types=1);

namespace App\Queries;

use App\Data\DocumentData;
use App\Models\Document;
use App\Services\CategoryTree;

/**
 * The rows of a list of documents, as *Documents* shows them, for the pages
 * that list some of them (*A person*, *An organization*).
 */
final readonly class DocumentRows
{
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
        $query = $this->filter->apply(Document::query()->with(['person', 'organization', 'file']), $filters);
        $paths = $this->categories->paths();

        if ($byExpiry) {
            $query->reorder('expiry_date');
        }

        return array_values($query->get()
            ->map(static fn (Document $document): DocumentData => DocumentData::fromDocument($document, $paths))
            ->all());
    }
}
