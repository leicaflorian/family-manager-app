<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Documents\SaveDocumentRequest;
use App\Models\Document;
use App\Models\File;
use App\Services\DocumentLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * The documents of the family.
 */
final class DocumentController extends Controller
{
    /**
     * Files a file of *Upload*: the document, with its links. Back to
     * *Upload* while other files wait, otherwise to *Documents*.
     */
    public function store(SaveDocumentRequest $request, DocumentLinks $links): RedirectResponse
    {
        DB::transaction(function () use ($request, $links): void {
            $document = Document::query()->create([...$request->fields(), 'file_id' => $request->integer('file_id')]);
            $document->file->update(['to_sort' => false]);
            $links->sync($document, $request->links());
        });

        return File::query()->toSort()->exists() ? to_route('upload') : to_route('documents.index');
    }
}
