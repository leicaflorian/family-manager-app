<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reading\QueueReading;
use App\Enums\FileSource;
use App\Http\Requests\Documents\UploadRequest;
use App\Models\File;
use App\Models\User;
use App\Services\FileStore;
use App\Support\ImportPage;
use App\Support\PresetLink;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Upload*: the files are saved and wait in *To sort* while the AI reads them; *Import more files* then
 * opens with them, to check and file each as a document. Discarding a waiting file deletes it.
 */
final class UploadController extends Controller
{
    /**
     * The page that takes the files, with the card the person comes from (`?link=<type>:<id>`), if it is one
     * of the family.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('documents/Upload', [
            'presetLinks' => array_filter([PresetLink::from($request->query('link'))]),
        ]);
    }

    /**
     * Saves the chosen files, which then wait for their document, each with the card
     * the person comes from, so its form starts linked to it, and opens *Import* with
     * them; the card stays in the address too, for going back to it. Each file is read
     * by itself, in the queue.
     */
    public function store(UploadRequest $request, FileStore $store, QueueReading $queue, #[CurrentUser] User $user): RedirectResponse
    {
        $link = PresetLink::from($request->query('link'));
        $note = $this->note($user);
        $ids = [];

        foreach ($request->file('files', []) as $file) {
            $stored = $store->store($file, toSort: true, link: $link, source: FileSource::Upload, note: $note);
            $queue->handle($stored, $user, app()->getLocale());
            $ids[] = $stored->id;
        }

        return ImportPage::open($ids, $request->query('link'));
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

        return back(fallback: route('to-sort'));
    }

    /**
     * How the files arrived, «Uploaded here on 22/10/2026 by Marco»: in the language of the request (`SetLocale`),
     * like the titles of the reminders; with the first name of the person of the account, as the Home greets it.
     *
     * @param  User  $user  who uploads
     */
    private function note(User $user): string
    {
        return __('Uploaded here on :date by :name', [
            'date' => CarbonImmutable::now('Europe/Rome')->format('d/m/Y'),
            'name' => $user->person->first_name ?? $user->name,
        ]);
    }
}
