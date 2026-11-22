<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Document;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A document pinned at the top of a person: its row, the area of its card type
 * (for the icon) and who pinned it.
 */
#[TypeScript]
final class PinnedDocumentData extends Data
{
    /**
     * @param  DocumentData  $document  the row of the document
     * @param  string|null  $area  the area of the card type of its category, null when the category has none
     * @param  string|null  $pinned_by  the name of the account that pinned it, null when the account is gone
     */
    public function __construct(
        public readonly DocumentData $document,
        public readonly ?string $area,
        public readonly ?string $pinned_by,
    ) {}

    /**
     * The pinned document, with `person`, `organization`, `file` and `pinner` loaded.
     *
     * @param  Document  $document  the document
     * @param  array<int, list<string>>  $paths  the paths of the categories, from `CategoryTree::paths()`
     * @param  string|null  $area  the area of its category, from `DocumentRows::pinned()`
     */
    public static function fromDocument(Document $document, array $paths, ?string $area): self
    {
        return new self(DocumentData::fromDocument($document, $paths), $area, $document->pinner?->name);
    }
}
