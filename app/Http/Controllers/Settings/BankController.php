<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Banks\CompleteBankConnection;
use App\Actions\Banks\ConnectBank;
use App\Actions\Banks\DisconnectBank;
use App\Actions\Banks\RefreshBanks;
use App\Data\BankConnectionData;
use App\Data\BankInstitutionData;
use App\Exceptions\Banks\BankFeedFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banks\StartBankConnectionRequest;
use App\Models\BankConnection;
use App\Models\FinancialAccount;
use App\Services\Banks\BankInstitutions;
use App\Support\Banks\BankVisitor;
use App\Support\Banks\SyncHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *Settings → Linked banks*: the banks the family linked, with how they are doing, and the list of banks to link
 * another. Everyone sees them; only an administrator links, links again and unlinks (checked by the policy).
 */
final class BankController extends Controller
{
    /**
     * The page. The list of banks comes only when the page asks for it, as the person opens *Link a bank*.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    public function index(BankInstitutions $banks): Response
    {
        return Inertia::render('settings/Banks', [
            'connections' => BankConnection::query()
                ->with('accounts')
                ->orderBy('institution_name')
                ->orderBy('id')
                ->get()
                ->map(BankConnectionData::fromConnection(...))
                ->all(),
            'can_change' => Gate::allows('create', BankConnection::class),
            'sync_hours' => SyncHours::all(),
            'consent_days' => Config::integer('bank_feed.consent_days'),
            'institutions' => Inertia::optional(fn (): ?array => $this->institutions($banks)),
        ]);
    }

    /**
     * Sends the person to the bank to give the permission: for a new bank and to connect again one already linked.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    public function store(StartBankConnectionRequest $request, ConnectBank $connect): SymfonyResponse|RedirectResponse
    {
        try {
            return Inertia::location($connect->handle($request->institution(), $request));
        } catch (BankFeedFailed $failure) {
            report($failure);

            return $this->failed();
        }
    }

    /**
     * Where the bank sends the person back. A visit of the browser, not of Inertia: it ends in the page of the banks
     * with what happened. Without the secret of the session, with a different one, with it used already or with
     * `error` instead of `code`, nothing is created.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    public function callback(Request $request, CompleteBankConnection $complete): RedirectResponse
    {
        Gate::authorize('create', BankConnection::class);

        // Read once: a second opening of the same address finds nothing
        $pending = $request->session()->pull('bank_connect');
        $state = $request->query('state');
        $code = $request->query('code');

        if (! is_array($pending) || ! is_string($pending['state'] ?? null) || ! is_string($state) || ! hash_equals($pending['state'], $state)
            || $request->query->has('error') || ! is_string($code) || $code === '') {
            return $this->failed();
        }

        try {
            $connection = $complete->handle($code);
        } catch (BankFeedFailed $failure) {
            report($failure);

            return $this->failed();
        }

        $labels = $connection->accounts()->whereNotNull('bank_account_uid')->orderBy('id')->get()
            ->map(static fn (FinancialAccount $account): string => $account->last_four_digits === null ? $account->name : $account->name.' ·· '.$account->last_four_digits);
        $message = match ($labels->count()) {
            0 => __('The bank is connected, but it gave no account we can read.'),
            1 => __(':account connected.', ['account' => $labels->first()]),
            default => __(':count accounts connected.', ['count' => $labels->count()]),
        };
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('banks.index');
    }

    /**
     * Unlinks a bank.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    public function destroy(BankConnection $connection, DisconnectBank $disconnect): RedirectResponse
    {
        Gate::authorize('delete', $connection);

        $disconnect->handle($connection);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bank disconnected.')]);

        return to_route('banks.index');
    }

    /**
     * *Update* on a bank: reads it now, as the person who is looking, and says how many movements are new.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    public function sync(Request $request, BankConnection $connection, RefreshBanks $refresh): RedirectResponse
    {
        Gate::authorize('update', $connection);

        $new = $refresh->handle([$connection], new BankVisitor((string) $request->ip(), (string) $request->userAgent()));
        Inertia::flash('toast', ['type' => 'success', 'message' => RefreshBanks::message($new)]);

        return back(fallback: route('banks.index'));
    }

    /**
     * Back to the page of the banks with the failure.
     */
    private function failed(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __('The connection did not work. Try again.')]);

        return to_route('banks.index');
    }

    /**
     * The banks of the country of the app.
     *
     * @return list<BankInstitutionData>|null null when the provider does not answer: nothing is kept, the next opening asks again
     */
    private function institutions(BankInstitutions $banks): ?array
    {
        try {
            return array_map(BankInstitutionData::fromInstitution(...), $banks->all());
        } catch (BankFeedFailed $failure) {
            report($failure);

            return null;
        }
    }
}
