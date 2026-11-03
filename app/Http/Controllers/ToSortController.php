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
     * The reading goes on in the queue; the page asks for its state.
     */
    public function read(ReadToSortRequest $request, QueueReading $queue, #[CurrentUser] User $user): RedirectResponse
    {
        $files = File::query()->toSort()->notCopies()->whereKey($request->ids())->get();

        foreach ($files as $file) {
            $queue->handle($file, $user, app()->getLocale());
        }

        // Task 13 (*Import more files*) changes this line: it opens that page with the ids
        return to_route('to-sort');
    }
}
