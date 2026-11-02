<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ReminderNotice;
use App\Models\User;
use App\Services\Reminders\NoticeList;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *Notices*, the page of the bell: each account sees and marks only its own, whatever the role.
 */
final class NoticeController extends Controller
{
    public function __construct(private readonly NoticeList $list) {}

    /**
     * The notices of the account, the new ones and the seen ones.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        $lists = $this->list->for($user->id, CarbonImmutable::today('Europe/Rome'));

        return Inertia::render('notices/Notices', ['unread' => $lists['unread'], 'seen' => $lists['seen']]);
    }

    /**
     * Marks one notice as read and goes to the card, the document or the reminder it is about.
     *
     * @throws NotFoundHttpException if the notice is of another account: it does not exist for this one
     */
    public function read(ReminderNotice $notice): RedirectResponse
    {
        // 404, not 403: a notice of somebody else must not be told apart from one that does not exist
        throw_unless(Gate::allows('update', $notice), NotFoundHttpException::class);

        $notice->read_at ??= now();
        $notice->save();

        return redirect($this->list->hrefOf($notice, CarbonImmutable::today('Europe/Rome')));
    }

    /**
     * Marks every notice of the account as read; the notices of the others stay as they are.
     */
    public function readAll(#[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('viewAny', ReminderNotice::class);

        ReminderNotice::query()->where('user_id', $user->id)->unread()->update(['read_at' => now()]);

        return to_route('notices');
    }
}
