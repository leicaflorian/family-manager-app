<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Queries\DocumentRows;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Support\Header;

/**
 * The page of a list of documents that a page of the app sends a page at a time.
 */
trait PagesDocuments
{
    /**
     * The page of a list of documents to send: the one in the request only on
     * the partial reload of *Show more*, which asks for that list; a full visit
     * always starts from the first, even with a number left in the address.
     *
     * @param  Request  $request  the request
     * @param  string  $list  the list, also the name of its prop and of its parameter (`personal_page`)
     */
    private function pageAsked(Request $request, string $list): int
    {
        $asked = explode(',', (string) $request->header(Header::PARTIAL_ONLY, ''));

        return in_array($list, $asked, true) ? max(1, $request->integer($list.'_page')) : 1;
    }

    /**
     * The three props of a list of documents sent a page at a time: the rows
     * (merged by id, so a partial reload adds the next page), the number of the
     * next page and the total. They are lazy and share one query per request.
     *
     * @param  Request  $request  the request
     * @param  DocumentRows  $rows  the rows of the documents
     * @param  string  $list  the list, also the prefix of its props (`personal`, `personalNext`, `personalTotal`)
     * @param  array<string, mixed>  $filters  the filters of `FilterDocuments::apply()`
     * @param  int  $perPage  how many documents a page holds
     * @return array<string, mixed> the props, to spread into the page
     */
    private function documentPages(Request $request, DocumentRows $rows, string $list, array $filters, int $perPage): array
    {
        $page = null;
        $load = function () use (&$page, $rows, $filters, $request, $list, $perPage): array {
            return $page ??= $rows->page($filters, $this->pageAsked($request, $list), $perPage);
        };

        return [
            $list => Inertia::merge(fn (): array => $load()['documents'])->matchOn('id'),
            $list.'Next' => fn (): ?int => $load()['next'],
            $list.'Total' => fn (): int => $load()['total'],
        ];
    }
}
