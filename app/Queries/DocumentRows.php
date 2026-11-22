<?php

declare(strict_types=1);

namespace App\Queries;

use App\Cards\CardTypes;
use App\Data\DocumentData;
use App\Data\PinnedDocumentData;
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
     * @return list<DocumentData>
     */
    public function of(array $filters): array
    {
        return $this->rows($this->filter->apply(Document::query()->with(self::RELATIONS), $filters)->get());
    }

    /**
     * A page of the documents that pass the filters, and the number of the next.
     *
     * @param  array<string, mixed>  $filters  the filters of `FilterDocuments::apply()`
     * @param  int  $page  the number of the page, from 1
     * @param  int  $perPage  how many documents a page holds
     * @return array{documents: list<DocumentData>, next: ?int, total: int}
     */
    public function page(array $filters, int $page, int $perPage): array
    {
        $paginator = $this->filter->apply(Document::query()->with(self::RELATIONS), $filters)
            ->paginate($perPage, page: $page);

        return [
            'documents' => $this->rows($paginator->getCollection()),
            'next' => $paginator->hasMorePages() ? $page + 1 : null,
            'total' => $paginator->total(),
        ];
    }

    /**
     * The personal documents of a person that are pinned, in the order they were
     * pinned, with the area of their card type and who pinned them.
     *
     * @param  int  $personId  the person
     * @return list<PinnedDocumentData>
     */
    public function pinned(int $personId): array
    {
        $documents = $this->filter->apply(Document::query()->with([...self::RELATIONS, 'pinner']), ['person' => $personId, 'linked' => false, 'pinned' => true])->get();
        $paths = $this->categories->paths();
        $areas = $this->areas();

        return array_values($documents->map(static fn (Document $document): PinnedDocumentData => PinnedDocumentData::fromDocument(
            $document,
            $paths,
            $areas[$document->category_id ?? 0] ?? null,
        ))->all());
    }

    /**
     * The area of the card type of every category, from its own type or the nearest one up the tree.
     *
     * @return array<int, string> the area value by category id; categories with no card type up the tree are missing
     */
    private function areas(): array
    {
        $areas = [];

        foreach ($this->categories->cardTypes() as $id => $type) {
            $area = CardTypes::find($type)?->area()->value;
            if ($area !== null) {
                $areas[$id] = $area;
            }
        }

        return $areas;
    }

    /**
     * Whether at least one document passes the filters.
     *
     * @param  array<string, mixed>  $filters  the filters of `FilterDocuments::apply()`
     */
    public function exist(array $filters): bool
    {
        return $this->filter->apply(Document::query()->select('documents.id'), $filters)->exists();
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
     * The rows of some documents already found, in their order (the documents linked to a new card from the start).
     *
     * @param  Collection<int, Document>  $documents  the documents
     * @return list<DocumentData>
     */
    public function ofModels(Collection $documents): array
    {
        return $this->rows($documents->load(self::RELATIONS));
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
