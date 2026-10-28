<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PendingFileData;
use App\Http\Requests\Documents\UploadRequest;
use App\Models\File;
use App\Services\DocumentFormOptions;
use App\Services\FileStore;
use App\Support\PresetLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Upload*: the files are saved first and wait, each with the form of its
 * document; saving the form files it, discarding it deletes the file.
 */
final class UploadController extends Controller
{
    /**
     * The files waiting for their document, oldest first, with the choices of the form
     * and the card the person comes from (`?link=<type>:<id>`), if it is one of the family.
     */
    public function index(Request $request, DocumentFormOptions $options): Response
    {
        return Inertia::render('documents/Upload', [
            'pending' => PendingFileData::waiting(),
            'presetLinks' => array_filter([PresetLink::from($request->query('link'))]),
            ...$options->all(),
        ]);
    }

    /**
     * Saves the chosen files, which then wait for their document; the card the person
     * comes from stays in the address, so the form of every file starts linked to it.
     */
    public function store(UploadRequest $request, FileStore $store): RedirectResponse
    {
        foreach ($request->file('files', []) as $file) {
            $store->store($file, toSort: true);
        }

        return to_route('upload', PresetLink::query($request->query('link')));
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
