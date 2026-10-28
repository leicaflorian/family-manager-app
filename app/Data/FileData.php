<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\File;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The file of a document: what the preview and the buttons need.
 */
#[TypeScript]
final class FileData extends Data
{
    /**
     * @param  string  $name  the original name
     * @param  string  $mime_type  the real type
     * @param  int  $size  the size in bytes
     * @param  string  $url  the address that opens it
     * @param  string  $download_url  the address that downloads it
     * @param  string|null  $preview_url  the address of the preview image, null when the file has none
     */
    public function __construct(
        public readonly string $name,
        public readonly string $mime_type,
        public readonly int $size,
        public readonly string $url,
        public readonly string $download_url,
        public readonly ?string $preview_url,
    ) {}

    /**
     * The data of a file.
     *
     * @param  File  $file  the file
     */
    public static function fromFile(File $file): self
    {
        return new self($file->original_name, $file->mime_type, $file->size, route('files.show', $file), route('files.download', $file), $file->hasPreview() ? route('files.preview', $file) : null);
    }
}
