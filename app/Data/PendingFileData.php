<?php

declare(strict_types=1);

namespace App\Data;

use App\Data\Reading\DocumentProposalData;
use App\Enums\FileSource;
use App\Enums\ReadingStatus;
use App\Models\File;
use App\Support\FileTitle;
use App\Support\PresetLink;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A file of *Upload* that waits for its document, with the title proposed for it, where its reading stands and
 * what the reading proposes.
 */
#[TypeScript]
final class PendingFileData extends Data
{
    /**
     * @param  int  $id  the id of the file
     * @param  string  $name  the original name
     * @param  string  $mime_type  the real type, for the preview
     * @param  int  $size  the size in bytes
     * @param  string  $url  the address that opens the file
     * @param  string  $title  the proposed title, from the name
     * @param  string|null  $preview_url  the address of the preview image, null when the file has none
     * @param  list<array{type: string, id: int}>  $links  the card the file was uploaded from, linked from the start of its form
     * @param  ReadingStatus|null  $reading_status  where the AI reading stands, null when it was never asked
     * @param  string|null  $reading_error  why the file is not read, in the language of who uploaded it
     * @param  DocumentProposalData|null  $proposal  what the reading proposes for the form, once the file is read
     * @param  FileSource  $source  where the file came from
     * @param  string  $source_label  where it came from, in words
     * @param  string|null  $note  how it arrived («Uploaded here on 22/10/2026 by Marco»)
     * @param  string|null  $copy_of  the name of the older file it is a copy of, null when it is no copy
     * @param  int|null  $pages  how many pages it has, when known: one for a photo
     * @param  bool  $can_delete  whether whoever signed in may delete it
     * @param  string  $created_on  the day it arrived, `yyyy-mm-dd`
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $mime_type,
        public readonly int $size,
        public readonly string $url,
        public readonly string $title,
        public readonly ?string $preview_url,
        public readonly array $links,
        public readonly ?ReadingStatus $reading_status,
        public readonly ?string $reading_error,
        public readonly ?DocumentProposalData $proposal,
        public readonly FileSource $source,
        public readonly string $source_label,
        public readonly ?string $note,
        public readonly ?string $copy_of,
        public readonly ?int $pages,
        public readonly bool $can_delete,
        public readonly string $created_on,
    ) {}

    /**
     * The files waiting for their document, the oldest first, with their copies found in one query.
     *
     * @param  int|null  $limit  how many at most, null for all
     * @return array<int, self>
     */
    public static function waiting(?int $limit = null): array
    {
        return self::fromFiles(File::query()->toSort()->with('document')->oldest('id')->limit($limit)->get());
    }

    /**
     * The data of many files waiting for their document, with their copies found in one query.
     *
     * @param  Collection<int, File>  $files  the files, with their `document` loaded
     * @return array<int, self>
     */
    public static function fromFiles(Collection $files): array
    {
        File::findCopies($files);

        return $files->map(self::fromFile(...))->values()->all();
    }

    /**
     * The data of a file waiting for its document.
     *
     * @param  File  $file  the file
     */
    public static function fromFile(File $file): self
    {
        $link = PresetLink::ofFile($file);
        $proposal = $file->reading['proposal'] ?? null;

        return new self(
            id: $file->id,
            name: $file->original_name,
            mime_type: $file->mime_type,
            size: $file->size,
            url: route('files.show', $file),
            title: FileTitle::from($file->original_name),
            preview_url: $file->hasPreview() ? route('files.preview', $file) : null,
            links: $link === null ? [] : [$link],
            reading_status: $file->reading_status,
            reading_error: $file->reading_error,
            // A file read again keeps the old reading until the new one is done: only a done reading proposes
            proposal: $file->reading_status === ReadingStatus::Done && is_array($proposal) ? DocumentProposalData::from($proposal) : null,
            source: $file->source,
            source_label: $file->source->label(),
            note: $file->note,
            copy_of: $file->copyOf()?->original_name,
            pages: str_starts_with($file->mime_type, 'image/') ? 1 : null,
            can_delete: Gate::allows('delete', $file),
            created_on: $file->created_at?->format('Y-m-d') ?? '',
        );
    }
}
