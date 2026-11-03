<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\Document;
use App\Models\File;
use App\Services\DocumentLinks;
use Illuminate\Support\Facades\DB;

/**
 * Files a file that waits for its document: the document with its links, and the file no longer waits nor
 * remembers the card it was uploaded from.
 */
final readonly class FileDocument
{
    /**
     * @param  DocumentLinks  $links  the links of the documents
     */
    public function __construct(private DocumentLinks $links) {}

    /**
     * Creates the document of the file, in one transaction.
     *
     * @param  File  $file  the waiting file
     * @param  array{fields: array<string, mixed>, links: list<array{type: string, id: int}>}  $data  the validated columns of the document and the cards it links to
     * @return Document the new document
     */
    public function handle(File $file, array $data): Document
    {
        return DB::transaction(function () use ($file, $data): Document {
            $document = Document::query()->create([...$data['fields'], 'file_id' => $file->id]);
            $file->update(['to_sort' => false, 'link_type' => null, 'link_id' => null]);
            $this->links->sync($document, $data['links']);

            return $document;
        });
    }
}
