<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Document;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A document in the list of *Documents*: what the row shows.
 */
#[TypeScript]
final class DocumentData extends Data
{
    /**
     * @param  int  $id  the id
     * @param  string  $title  the title
     * @param  string|null  $category  the name of its main category
     * @param  string|null  $person  the first name of the person it refers to
     * @param  string|null  $organization  the name of the organization it comes from
     * @param  string|null  $date  the date, ISO
     * @param  string|null  $expiry_date  the expiry, ISO
     * @param  string  $status  `active`, `archived` or `expired`
     * @param  string  $mime_type  the type of its file, for the icon
     * @param  string  $file_url  the address that opens its file
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $category,
        public readonly ?string $person,
        public readonly ?string $organization,
        public readonly ?string $date,
        public readonly ?string $expiry_date,
        public readonly string $status,
        public readonly string $mime_type,
        public readonly string $file_url,
    ) {}

    /**
     * The row of a document, with `person`, `organization` and `file` loaded.
     *
     * @param  Document  $document  the document
     * @param  array<int, list<string>>  $paths  the paths of the categories, from `CategoryTree::paths()`
     */
    public static function fromDocument(Document $document, array $paths): self
    {
        return new self(
            id: $document->id,
            title: $document->title,
            category: $paths[$document->category_id ?? 0][0] ?? null,
            person: $document->person?->first_name,
            organization: $document->organization?->name,
            date: $document->date?->toDateString(),
            expiry_date: $document->expiry_date?->toDateString(),
            status: $document->status->value,
            mime_type: $document->file->mime_type,
            file_url: route('files.show', $document->file),
        );
    }
}
