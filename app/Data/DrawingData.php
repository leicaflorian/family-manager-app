<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Document;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A drawing of the gallery: a document of the gallery category with its file, the child and the age they had that day.
 */
#[TypeScript]
final class DrawingData extends Data
{
    /**
     * @param  int  $id  the id of the document
     * @param  string  $title  the title
     * @param  string|null  $notes  the notes
     * @param  int|null  $person_id  the child
     * @param  string|null  $person_name  the first name of the child
     * @param  int|null  $age  the years the child had on the upload day, null without a person or a birth date
     * @param  string  $uploaded_on  the upload day in Rome, `yyyy-mm-dd`
     * @param  int|null  $pages  the pages of a PDF, null when unknown or for an image
     * @param  bool  $is_favorite  whether it is a favourite
     * @param  string  $image_url  the address that shows it as an image
     * @param  string  $file_url  the address that opens its file
     * @param  string  $download_url  the address that downloads its file
     * @param  string  $document_url  the page of the document
     * @param  bool  $can_favorite  whether the account can mark it as a favourite
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $notes,
        public readonly ?int $person_id,
        public readonly ?string $person_name,
        public readonly ?int $age,
        public readonly string $uploaded_on,
        public readonly ?int $pages,
        public readonly bool $is_favorite,
        public readonly string $image_url,
        public readonly string $file_url,
        public readonly string $download_url,
        public readonly string $document_url,
        public readonly bool $can_favorite,
    ) {}

    /**
     * The drawing of a document with `person` and `file` loaded.
     *
     * @param  Document  $document  the document
     * @param  User  $user  the account that looks at it
     */
    public static function fromDocument(Document $document, User $user): self
    {
        $file = $document->file;
        $uploaded = CarbonImmutable::parse($file->created_at)->setTimezone('Europe/Rome');
        $birth = $document->person?->birth_date;

        return new self(
            id: $document->id,
            title: $document->title,
            notes: $document->notes,
            person_id: $document->person_id,
            person_name: $document->person?->first_name,
            age: $birth === null ? null : (int) $birth->startOfDay()->diffInYears($uploaded->startOfDay(), true),
            uploaded_on: $uploaded->toDateString(),
            pages: $file->page_count,
            is_favorite: $document->is_favorite,
            image_url: $file->imageUrl(),
            file_url: route('files.show', $file),
            download_url: route('files.download', $file),
            document_url: route('documents.show', $document),
            can_favorite: $user->can('update', $document),
        );
    }
}
