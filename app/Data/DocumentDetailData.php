<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Document;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A document as *A document* and *Edit a document* show it: its data, its
 * file, its links and what the person may do with it.
 */
#[TypeScript]
final class DocumentDetailData extends Data
{
    /**
     * @param  int  $id  the id
     * @param  string  $title  the title
     * @param  int|null  $category_id  the category
     * @param  string|null  $category  the path of the category, «Work › Payslips»
     * @param  int|null  $person_id  the person it refers to
     * @param  string|null  $person  the full name of that person
     * @param  string|null  $person_initials  the initials of that person, for the avatar
     * @param  int|null  $organization_id  the organization it comes from
     * @param  string|null  $organization  the name of that organization
     * @param  string|null  $date  the date, ISO
     * @param  string|null  $expiry_date  the expiry, ISO
     * @param  string  $status  `active`, `archived` or `expired`
     * @param  string  $status_label  the status in the current language
     * @param  list<string>  $tags  the tags
     * @param  string|null  $notes  the notes
     * @param  FileData  $file  the file
     * @param  list<DocumentLinkData>  $links  the linked records
     * @param  bool  $can_edit  the person may change it
     * @param  bool  $can_delete  the person may delete it (an `admin`)
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?int $category_id,
        public readonly ?string $category,
        public readonly ?int $person_id,
        public readonly ?string $person,
        public readonly ?string $person_initials,
        public readonly ?int $organization_id,
        public readonly ?string $organization,
        public readonly ?string $date,
        public readonly ?string $expiry_date,
        public readonly string $status,
        public readonly string $status_label,
        public readonly array $tags,
        public readonly ?string $notes,
        public readonly FileData $file,
        public readonly array $links,
        public readonly bool $can_edit,
        public readonly bool $can_delete,
    ) {}

    /**
     * The data of a document, with `person`, `organization` and `file` loaded.
     *
     * @param  Document  $document  the document
     * @param  list<string>|null  $path  the path of its category, from `CategoryTree::paths()`
     * @param  list<DocumentLinkData>  $links  its links, from `DocumentLinks::of()`
     * @param  User  $user  who looks at it
     */
    public static function fromDocument(Document $document, ?array $path, array $links, User $user): self
    {
        return new self(
            id: $document->id,
            title: $document->title,
            category_id: $document->category_id,
            category: $path === null ? null : implode(' › ', $path),
            person_id: $document->person_id,
            person: $document->person === null ? null : "{$document->person->first_name} {$document->person->last_name}",
            person_initials: $document->person?->initials,
            organization_id: $document->organization_id,
            organization: $document->organization?->name,
            date: $document->date?->toDateString(),
            expiry_date: $document->expiry_date?->toDateString(),
            status: $document->status->value,
            status_label: $document->status->label(),
            tags: array_values(array_filter($document->tags ?? [], is_string(...))),
            notes: $document->notes,
            file: FileData::fromFile($document->file),
            links: $links,
            can_edit: $user->can('update', $document),
            can_delete: $user->can('delete', $document),
        );
    }
}
