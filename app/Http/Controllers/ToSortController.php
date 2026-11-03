<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reading\QueueReading;
use App\Data\ToSortPageData;
use App\Http\Requests\Documents\ReadToSortRequest;
use App\Models\File;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *To sort*: the files waiting for their document, whoever sent them (the upload, the mail, the phone), with
 * their copies, and the reading of the ones chosen.
 */
final class ToSortController extends Controller
{
    /**
     * The files waiting, all or only those of `?source=` (anything but a source with a tab shows all of them).
     */
    public function index(Request $request): Response
    {
        return Inertia::render('documents/ToSort', ToSortPageData::for($request->query('source'))->toArray());
    }

    /**
     * Has the files chosen read: those not read yet, never a copy and never a file that already has its document.
     * The reading goes on in the queue; the person goes on to *Import more files*, which asks for its state.
     */
    public function read(ReadToSortRequest $request, QueueReading $queue, #[CurrentUser] User $user): RedirectResponse
    {
        $ids = $request->ids();
        $files = File::query()->toSort()->notCopies()->whereKey($ids)->get()
            ->sortBy(static fn (File $file): int|false => array_search($file->id, $ids, true));

        foreach ($files as $file) {
            $queue->handle($file, $user, app()->getLocale());
        }

        return $files->isEmpty()
            ? to_route('to-sort')
            : to_route('import', ['files' => $files->pluck('id')->implode(',')]);
    }
}
