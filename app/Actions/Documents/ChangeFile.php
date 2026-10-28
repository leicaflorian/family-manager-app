<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\Document;
use App\Services\FileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Puts a new file on a document and removes the previous one.
 */
final readonly class ChangeFile
{
    public function __construct(private FileStore $files) {}

    /**
     * Saves the new file and deletes the previous one, row and content.
     *
     * @param  Document  $document  the document
     * @param  UploadedFile  $file  the uploaded file
     */
    public function handle(Document $document, UploadedFile $file): void
    {
        DB::transaction(function () use ($document, $file): void {
            $previous = $document->file;
            $document->update(['file_id' => $this->files->store($file)->id]);
            $this->files->delete($previous);
        });
    }
}
