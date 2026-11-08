<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Mail\DeleteReceivedMail;
use App\Actions\Mail\SaveAsNotice;
use App\Data\ReceivedMailData;
use App\Data\ReceivedMailRowData;
use App\Models\FamilySetting;
use App\Models\ReceivedMail;
use App\Support\Mail\MailText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * *Received mail*: the mails that reached the address for the bills, newest first, and one of them open with what
 * was done with it and its text. Everyone of the family sees them; only an `admin` deletes one or saves it as a
 * notice.
 */
final class ReceivedMailController extends Controller
{
    /** How many mails the list loads at a time. */
    private const int PER_PAGE = 50;

    /**
     * The list, with `?search=` in the sender, the subject and the recipients, `?page=` for the next mails.
     *
     * @param  Request  $request  the request with the search and the page
     */
    public function index(Request $request): Response
    {
        return $this->page($request, null);
    }

    /**
     * The same page with a mail open.
     *
     * @param  Request  $request  the request with the search and the page of the list
     * @param  ReceivedMail  $receivedMail  the mail, of the family (another family's answers 404)
     */
    public function show(Request $request, ReceivedMail $receivedMail): Response
    {
        return $this->page($request, $receivedMail);
    }

    /**
     * The text of the mail, a whole page for the frame of the page: the HTML as it came, or the plain text with
     * `?plain=1` or when there is no HTML. The mail was written by strangers: the answer is sandboxed by its own
     * policy, so it stays isolated even opened outside the frame.
     *
     * @param  Request  $request  the request, with `?plain=1` for the plain text
     * @param  ReceivedMail  $receivedMail  the mail, of the family (another family's answers 404)
     */
    public function text(Request $request, ReceivedMail $receivedMail): HttpResponse
    {
        $document = MailText::document($receivedMail->body ?? [], $request->boolean('plain'));
        abort_if($document === null, 404);

        return response($document, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => 'sandbox allow-popups allow-popups-to-escape-sandbox; '.MailText::POLICY,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Deletes the mail, then goes back to the list.
     *
     * @param  ReceivedMail  $receivedMail  the mail, of the family (another family's answers 404)
     * @param  DeleteReceivedMail  $delete  the deletion
     *
     * @throws Throwable when the mail is not deleted
     */
    public function destroy(ReceivedMail $receivedMail, DeleteReceivedMail $delete): RedirectResponse
    {
        Gate::authorize('delete', $receivedMail);

        $delete->handle($receivedMail);

        return to_route('mail.index');
    }

    /**
     * *Salva come avviso*, then back to the mail; a mail that has no notice to save goes back with the reason.
     *
     * @param  ReceivedMail  $receivedMail  the mail, of the family (another family's answers 404)
     * @param  SaveAsNotice  $save  the saving
     *
     * @throws Throwable when the notice is not saved
     */
    public function notice(ReceivedMail $receivedMail, SaveAsNotice $save): RedirectResponse
    {
        Gate::authorize('update', $receivedMail);

        $save->handle($receivedMail);

        return back(fallback: route('mail.show', $receivedMail));
    }

    /**
     * The page: a block of the list (added below those already shown) and the mail open, if any.
     *
     * @param  Request  $request  the request with the search and the page
     * @param  ReceivedMail|null  $open  the mail open
     */
    private function page(Request $request, ?ReceivedMail $open): Response
    {
        $search = $request->string('search')->trim()->value();
        $page = max(1, $request->integer('page'));
        $like = '%'.addcslashes($search, '%_\\').'%';
        $mails = ReceivedMail::query()
            ->with(['files', 'organization'])
            ->when($search !== '', static fn (Builder $query): Builder => $query->where(static fn (Builder $query): Builder => $query
                ->whereLike('sender', $like)
                ->orWhereLike('subject', $like)
                // json, compared by MySQL in a binary collation: read as text in lower case
                ->orWhereRaw('lower(cast(recipients as char)) like ?', [mb_strtolower($like)])
                ->orWhereHas('organization', static fn (Builder $query): Builder => $query->whereLike('name', $like))))
            ->latest('received_at')
            ->orderByDesc('id')
            ->skip(($page - 1) * self::PER_PAGE)
            ->take(self::PER_PAGE + 1)
            ->get();

        return Inertia::render('mail/ReceivedMails', [
            'address' => FamilySetting::query()->value('mail_inbox_recipient'),
            'search' => $search,
            'mails' => Inertia::merge(fn (): array => $mails->take(self::PER_PAGE)->map(ReceivedMailRowData::fromMail(...))->values()->all())->matchOn('id'),
            'next' => $mails->count() > self::PER_PAGE ? $page + 1 : null,
            'mail' => $open instanceof ReceivedMail ? ReceivedMailData::fromMail($open) : null,
        ]);
    }
}
