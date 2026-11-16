<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Actions\Agenda\UpdateDeadline;
use App\Actions\Budget\ChangeOccurrence;
use App\Actions\Budget\RecordPayment;
use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\ChangeOccurrenceRequest;
use App\Http\Requests\Budget\RecordPaymentRequest;
use App\Models\Expense;
use App\Models\Income;
use App\Models\InsurancePolicy;
use App\Services\Deadlines;
use App\Support\LocalPath;
use App\Support\PolicyReplacement;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One due date of a planned expense or income. It has no page of its own: its address opens the planned item with the
 * due date's panel (or the movement that paid it), and *I paid it* (*It arrived*) and *Change this due date* send their
 * forms here. The address names the planned item and the day the rules give.
 */
final class DeadlineController extends Controller
{
    public function __construct(private readonly Deadlines $deadlines) {}

    /**
     * The address of a due date, kept for links already out there (search, the Agenda, e-mails): a paid due date leads
     * to the movement that paid it, any other (skipped too, to bring it back) to its planned item, which opens it.
     * `?pay=1` and the page the visit came from go along, so the panel is the payment and saving goes back there.
     *
     * @throws NotFoundHttpException if the address is not a due date of the family
     */
    public function show(Request $request, string $kind, string $id, string $date): RedirectResponse
    {
        $payment = $this->deadlines->findByAddress($kind, $id, $date, withSkipped: true)->payment;

        if ($payment !== null) {
            return to_route('budget.movements.show', ['kind' => $payment instanceof Expense ? 'expense' : 'income', 'id' => $payment->id]);
        }

        return to_route('budget.planned.show', array_filter([
            'kind' => $kind,
            'id' => $id,
            'deadline' => $date,
            'pay' => $request->boolean('pay') ? 1 : null,
            'from' => $this->cameFrom($request, route('budget.planned.show', ['kind' => $kind, 'id' => $id], false)),
        ]));
    }

    /**
     * The page of the app the visit came from, to go back to after saving: none from another site or from the
     * planned item itself, where saving already goes back.
     *
     * @param  Request  $request  the visit
     * @param  string  $planned  the path of the planned item
     */
    private function cameFrom(Request $request, string $planned): ?string
    {
        $referer = parse_url((string) $request->headers->get('referer'));

        if (! is_array($referer) || ($referer['host'] ?? null) !== $request->getHost() || ($referer['path'] ?? '/') === $planned) {
            return null;
        }

        return LocalPath::of(($referer['path'] ?? '/').(isset($referer['query']) ? '?'.$referer['query'] : ''));
    }

    /**
     * *I paid it* / *It arrived*: records the payment and goes back to the planned item, to the page the due date was
     * opened from (`?back=`), or to the Agenda it was sent from.
     * For the premium of a policy near its expiry, *Same company, renewed* also moves the expiry of the policy, in the
     * same transaction, unless it already moved since the page was opened; *I changed company* goes on to the form of
     * the new policy, which takes the payment. When the page was stale (the policy renewed, replaced or cancelled since
     * it opened) the choice records only the payment and leaves the policy alone.
     *
     * @throws NotFoundHttpException if the address is not a due date of the family
     */
    public function payment(RecordPaymentRequest $request, string $kind, string $id, string $date, RecordPayment $record, UpdateDeadline $update): RedirectResponse
    {
        $deadline = $request->deadline();
        $paid = CarbonImmutable::createFromFormat('!Y-m-d', $request->string('date')->toString(), 'Europe/Rome') ?: throw new NotFoundHttpException;
        $account = $request->integer('financial_account_id');
        $receipt = $request->file('receipt');
        $renewal = $request->renewal();
        $policy = $renewal === 'only' ? null : $request->renewalPolicy();

        $payment = DB::transaction(function () use ($record, $update, $request, $deadline, $paid, $account, $receipt, $renewal, $policy): Expense|Income {
            $payment = $record->handle($deadline, $paid, $request->float('amount'), $account === 0 ? null : $account, $receipt instanceof UploadedFile ? $receipt : null);
            $newExpiry = $request->day('new_expiry');

            if ($renewal === 'same' && $policy instanceof InsurancePolicy && $newExpiry instanceof CarbonImmutable) {
                $this->renew($update, $policy, $request->day('policy_expiry'), $newExpiry);
            }

            return $payment;
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => $kind === 'income' ? __('Income recorded.') : __('Payment recorded.')]);

        if ($renewal === 'changed' && $policy instanceof InsurancePolicy && $payment instanceof Expense) {
            return redirect()->to(PolicyReplacement::createUrl($policy, $payment));
        }

        return $request->boolean('from_agenda') ? back() : $this->back($request, $kind, $id);
    }

    /**
     * Renews a policy with the same company: the new expiry, the policy active again and the reminder of the old
     * expiry done. The policy is read again under a lock: when its expiry is no longer the one the page showed,
     * someone already renewed it, and it is left alone.
     *
     * @param  UpdateDeadline  $update  the action that writes the date
     * @param  InsurancePolicy  $policy  the policy
     * @param  CarbonImmutable|null  $shown  the expiry the page showed
     * @param  CarbonImmutable  $expiry  the new expiry
     */
    private function renew(UpdateDeadline $update, InsurancePolicy $policy, ?CarbonImmutable $shown, CarbonImmutable $expiry): void
    {
        $current = InsurancePolicy::query()->lockForUpdate()->find($policy->id);
        $source = DeadlineSources::find('insurance_policies.expiry_date');

        if (! $current instanceof InsurancePolicy || ! $source instanceof DeadlineSource || $current->expiry_date?->toDateString() !== $shown?->toDateString()) {
            return;
        }

        $update->handle($source, $current, $expiry, true, CarbonImmutable::today('Europe/Rome'));
    }

    /**
     * *Change this due date*: saves the exceptions of the due date and goes back to the planned item, or to the page the
     * due date was opened from (`?back=`).
     *
     * @throws NotFoundHttpException if the address is not a due date of the family
     */
    public function exceptions(ChangeOccurrenceRequest $request, string $kind, string $id, string $date, ChangeOccurrence $change): RedirectResponse
    {
        $deadline = $request->deadline();
        $choices = $request->choices();

        $change->handle($deadline->planned, $deadline->occurrence->originalDate, $choices['skip'], $choices['amount'], $choices['date'], $choices['notes']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return $this->back($request, $kind, $id);
    }

    /**
     * Back to the page the due date was opened from (`?back=`, a path of the app only), else to the planned item.
     *
     * @param  Request  $request  the form sent
     * @param  string  $kind  `expense` or `income`
     * @param  string  $id  the id of the planned item
     */
    private function back(Request $request, string $kind, string $id): RedirectResponse
    {
        $path = LocalPath::of($request->query('back'));

        return $path === null ? to_route('budget.planned.show', ['kind' => $kind, 'id' => $id]) : redirect($path);
    }
}
