<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the reading proposes for the form of a document: the values of its fields, the cards it links to
 * and the lines the box on top of the form shows.
 */
#[TypeScript]
final class DocumentProposalData extends Data
{
    /**
     * @param  string|null  $title  the title of the document
     * @param  int|null  $category_id  its document category
     * @param  int|null  $person_id  the person it is about
     * @param  int|null  $organization_id  who made it
     * @param  string|null  $date  `YYYY-MM-DD`
     * @param  string|null  $expiry_date  `YYYY-MM-DD`, always empty for the kinds of paper that do not expire
     * @param  list<array{type: string, id: int}>  $links  the cards to link, `type` as the morph map writes it (`utility`)
     * @param  string  $headline  what the paper is, in a sentence of the language of the person (`I found an electricity bill`)
     * @param  list<FoundLineData>  $found  the lines under the headline: who sent it, how much, due on
     */
    public function __construct(
        public readonly ?string $title,
        public readonly ?int $category_id,
        public readonly ?int $person_id,
        public readonly ?int $organization_id,
        public readonly ?string $date,
        public readonly ?string $expiry_date,
        public readonly array $links,
        public readonly string $headline,
        public readonly array $found,
    ) {}
}
