<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A document that a card expects and does not have yet, in *Something is missing* of the Home.
 */
#[TypeScript]
final class MissingDocumentData extends Data
{
    /**
     * @param  string  $key  what tells it from the others of the Home, for the list of the page: the page that uploads it and its place among those of the same card
     * @param  string  $card  the name of the card, for example «Fiat Panda»
     * @param  string  $href  the page that uploads the document with the card already among its links
     * @param  string  $document  the name of the document, for example «Vehicle registration document»
     */
    public function __construct(
        public readonly string $key,
        public readonly string $card,
        public readonly string $href,
        public readonly string $document,
    ) {}
}
