<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Data\PendingFileData;
use App\Http\Requests\Documents\UploadRequest;
use App\Models\File;
use App\Services\DocumentFormOptions;
use App\Services\FileStore;
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
            'pending' => File::query()->toSort()->oldest('id')->get()
                ->map(PendingFileData::fromFile(...))
                ->values()->all(),
            'presetLinks' => $this->presetLinks($request->query('link')),
            ...$options->all(),
        ]);
    }

    /**
     * The link of the query as the form takes it: empty when it is malformed,
     * of an unknown type or of a card the family does not have (nothing leaks).
     *
     * @param  mixed  $link  the `link` of the query, `<type>:<id>`
     * @return list<array{type: string, id: int}>
     */
    private function presetLinks(mixed $link): array
    {
        if (! is_string($link) || preg_match('/^([a-z_]+):(\d+)$/', $link, $parts) !== 1) {
            return [];
        }

        $card = CardTypes::find($parts[1]);
        $id = (int) $parts[2];

        if (! $card instanceof CardType || ! $card->model()::query()->whereKey($id)->exists()) {
            return [];
        }

        return [['type' => $card->type(), 'id' => $id]];
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
