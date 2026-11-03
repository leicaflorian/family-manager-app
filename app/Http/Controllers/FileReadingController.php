<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reading\QueueReading;
use App\Models\File;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * *Read again*: the reading of a file of the family, waiting or already of a document, goes back in the queue.
 */
final class FileReadingController extends Controller
{
    /**
     * Queues the reading again and goes back to the page; a file already waiting in the queue stays as it is.
     *
     * @param  File  $file  the file, of the family (another family's answers 404)
     * @param  QueueReading  $queue  the queue of the readings
     * @param  User  $user  whoever asks, the reader of the file
     */
    public function __invoke(File $file, QueueReading $queue, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('read', $file);

        $queue->handle($file, $user, app()->getLocale(), again: true);

        return back();
    }
}
