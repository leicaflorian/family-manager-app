<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
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
}
