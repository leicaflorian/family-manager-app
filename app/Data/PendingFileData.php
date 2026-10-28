<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\File;
use App\Support\FileTitle;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A file of *Upload* that waits for its document, with the title proposed for it.
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
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $mime_type,
        public readonly int $size,
        public readonly string $url,
        public readonly string $title,
        public readonly ?string $preview_url,
    ) {}

    /**
     * The data of a file waiting for its document.
     *
     * @param  File  $file  the file
     */
    public static function fromFile(File $file): self
    {
        return new self(
            id: $file->id,
            name: $file->original_name,
            mime_type: $file->mime_type,
            size: $file->size,
            url: route('files.show', $file),
            title: FileTitle::from($file->original_name),
            preview_url: $file->hasPreview() ? route('files.preview', $file) : null,
        );
    }
}
