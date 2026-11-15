<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What the server writes in the head of a page: title, description, robots rules and the preview of a shared link.
 * Built by {@see PageMeta}; it never reaches the pages, which set their own title once Vue starts.
 */
final readonly class PageHead
{
    /**
     * @param  string  $title  the whole title, with the name of the app («Accedi - Fameja.app»)
     * @param  string  $description  the sentence that search engines and link previews show
     * @param  bool  $indexable  whether search engines may list the page
     * @param  string|null  $canonical  the address of the page without the query, only for a page that can be indexed
     * @param  string  $image  the absolute address of the preview image, 1200 × 630 px
     * @param  string  $locale  the language of the page for Open Graph (`it_IT`, `en_US`)
     */
    public function __construct(
        public string $title,
        public string $description,
        public bool $indexable,
        public ?string $canonical,
        public string $image,
        public string $locale,
    ) {}
}
