<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Document;
use App\Models\File;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A file a received mail saved, with where it is now: in *To sort*, a document, kept elsewhere, or gone.
 */
#[TypeScript]
final class ReceivedMailFileData extends Data
{
    /**
     * @param  string  $name  the name of the file as it was saved
     * @param  'to_sort'|'document'|'saved'|'gone'  $state  where it is now: waiting in *To sort*, the file of a document, kept without a document, or deleted
     * @param  string|null  $url  where it leads: *To sort*, the document or the file; null once it is gone
     * @param  string|null  $document_title  the title of the document it became
     */
    public function __construct(
        public readonly string $name,
        #[LiteralTypeScriptType("'to_sort' | 'document' | 'saved' | 'gone'")]
        public readonly string $state,
        public readonly ?string $url,
        public readonly ?string $document_title,
    ) {}

    /**
     * A file the mail saved.
     *
     * @param  string  $name  the name written on the mail
     * @param  File|null  $file  the file, null once it was deleted; best with `document` loaded
     */
    public static function fromSaved(string $name, ?File $file): self
    {
        $document = $file?->document;

        return match (true) {
            ! $file instanceof File => new self($name, 'gone', null, null),
            $document instanceof Document => new self($name, 'document', route('documents.show', $document), $document->title),
            $file->to_sort => new self($name, 'to_sort', route('to-sort'), null),
            default => new self($name, 'saved', route('files.show', $file), null),
        };
    }
}
