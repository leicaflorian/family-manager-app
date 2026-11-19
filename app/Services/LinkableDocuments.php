<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\DocumentRows;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\MergeProp;

/**
 * The props of *Link a document* on a page: a page of the documents of the family that match `link_search`, the next
 * pages added below those already shown, and whether there is any document to link.
 */
final readonly class LinkableDocuments
{
    /** How many documents a page of *Link a document* holds. */
    public const int LINKABLE_PER_PAGE = 15;

    public function __construct(private DocumentRows $documents) {}

    /**
     * The props for the page.
     *
     * @param  Request  $request  the request with the search (`link_search`) and the page (`link_page`)
     * @param  array<string, mixed>  $filters  the filters that leave out the documents already linked
     * @return array{linkable: MergeProp, linkableNext: ?int, linkableSearch: string, hasLinkable: bool}
     */
    public function props(Request $request, array $filters): array
    {
        $search = $request->string('link_search')->trim()->value();
        $page = $this->documents->page([...$filters, 'search' => $search], max(1, $request->integer('link_page', 1)), self::LINKABLE_PER_PAGE);

        return [
            'linkable' => Inertia::merge($page['documents']),
            'linkableNext' => $page['next'],
            'linkableSearch' => $search,
            'hasLinkable' => $this->documents->exist($filters),
        ];
    }
}
