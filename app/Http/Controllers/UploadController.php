<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reading\QueueReading;
use App\Data\PendingFileData;
use App\Enums\FileSource;
use App\Http\Requests\Documents\UploadRequest;
use App\Models\File;
use App\Models\User;
use App\Services\DocumentFormOptions;
use App\Services\FileStore;
use App\Support\PresetLink;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Upload*: the files are saved first and wait, each with the form of its
 * document, while the AI reads them; saving the form files it, discarding it
 * deletes the file.
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
     * Saves the chosen files, which then wait for their document, each with the card
     * the person comes from, so its form starts linked to it; the card stays in the
     * address too, for the next files and for going back to it. Each file is read
     * by itself, in the queue.
     */
    public function store(UploadRequest $request, FileStore $store, QueueReading $queue, #[CurrentUser] User $user): RedirectResponse
    {
        $link = PresetLink::from($request->query('link'));
        $note = $this->note($user);

        foreach ($request->file('files', []) as $file) {
            $queue->handle($store->store($file, toSort: true, link: $link, source: FileSource::Upload, note: $note), $user, app()->getLocale());
        }

        return to_route('upload', PresetLink::query($request->query('link')));
    }

    /**
     * Discards a file still waiting, if the person may (`FilePolicy`): a filed one is no longer here. It goes back
     * to the page it was deleted from, *Upload* or *To sort*.
     */
    public function destroy(File $file, FileStore $store): RedirectResponse
    {
        abort_unless($file->to_sort, 404);
        Gate::authorize('delete', $file);

        $store->delete($file);

        return back(fallback: route('upload'));
    }

    /**
     * How the files arrived, «Uploaded here on 22/10/2026 by Marco»: in the language of the app, like the
     * titles of the reminders, since the whole family reads it; with the first name of the person of the
     * account, as the Home greets it.
     *
     * @param  User  $user  who uploads
     */
    private function note(User $user): string
    {
        return __('Uploaded here on :date by :name', [
            'date' => CarbonImmutable::now('Europe/Rome')->format('d/m/Y'),
            'name' => $user->person->first_name ?? $user->name,
        ], config()->string('app.locale'));
    }
}
