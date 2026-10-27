<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PendingFileData;
use App\Http\Requests\Documents\UploadRequest;
use App\Models\File;
use App\Services\DocumentFormOptions;
use App\Services\FileStore;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Upload*: the files are saved first and wait, each with the form of its
 * document; saving the form files it, discarding it deletes the file.
 */
final class UploadController extends Controller
{
    /**
     * The files waiting for their document, oldest first, with the choices of the form.
     */
    public function index(DocumentFormOptions $options): Response
    {
        return Inertia::render('documents/Upload', [
            'pending' => File::query()->toSort()->oldest('id')->get()
                ->map(PendingFileData::fromFile(...))
                ->values()->all(),
            ...$options->all(),
        ]);
    }

    /**
     * Saves the chosen files, which then wait for their document.
     */
    public function store(UploadRequest $request, FileStore $store): RedirectResponse
    {
        foreach ($request->file('files', []) as $file) {
            $store->store($file, toSort: true);
        }

        return to_route('upload');
    }

    /**
     * Discards a file still waiting: a filed one is no longer here.
     */
    public function destroy(File $file, FileStore $store): RedirectResponse
    {
        abort_unless($file->to_sort, 404);

        $store->delete($file);

        return to_route('upload');
    }
}
