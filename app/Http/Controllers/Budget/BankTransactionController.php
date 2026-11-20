<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Actions\Banks\RefreshBanks;
use App\Data\BankTransactionData;
use App\Enums\BankConnectionStatus;
use App\Http\Controllers\Controller;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Services\Banks\BankFeedAvailability;
use App\Support\Banks\BankVisitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *Budget → From the bank*: the transactions the bank gave that nothing recognised, newest first, and the ones the
 * person put aside. Everyone of the family sees them and asks for a refresh; the page is 404 while the link to the
 * bank is off.
 */
final class BankTransactionController extends Controller
{
    /** How many rows a block of the list has. */
    private const int PER_PAGE = 50;

    /**
     * The page: a block of the list (added below the ones already shown) under `?show=unsorted` or `?show=ignored`.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    public function index(Request $request): Response
    {
        $this->ensureOn();
        Gate::authorize('viewAny', BankTransaction::class);

        $ignored = $request->query('show') === 'ignored';
        $page = max(1, $request->integer('page'));
        $block = null;
        // One query for both `rows` and `next`, run the first time either is asked
        $rows = function () use (&$block, $ignored, $page): Collection {
            return $block ??= $this->block($ignored, $page);
        };

        return Inertia::render('budget/BankTransactions', [
            'show' => $ignored ? 'ignored' : 'unsorted',
            'rows' => Inertia::merge(fn (): array => $rows()->take(self::PER_PAGE)->map(BankTransactionData::fromTransaction(...))->values()->all())->matchOn('id'),
            'next' => fn (): ?int => $rows()->count() > self::PER_PAGE ? $page + 1 : null,
            'banks_to_reconnect' => fn (): array => BankConnection::query()
                ->where('status', BankConnectionStatus::NeedsReconnect)
                ->orderBy('institution_name')
                ->pluck('institution_name')
                ->unique()
                ->values()
                ->all(),
            // When a bank was last read (`yyyy-mm-ddThh:mm`, Rome) and at which hours of the day they are read by themselves
            'last_synced_at' => fn (): ?string => BankConnection::query()->whereNotNull('last_synced_at')->latest('last_synced_at')->first()?->last_synced_at?->setTimezone('Europe/Rome')->format('Y-m-d\TH:i'),
            'sync_hours' => array_map(static fn (mixed $time): int => (int) (is_string($time) ? $time : 0), array_values(Config::array('bank_feed.sync_times'))),
            'can_sort' => Gate::allows('create', BankTransaction::class),
            'can_change_banks' => Gate::allows('create', BankConnection::class),
        ]);
    }

    /**
     * *Update*: reads every bank that is connected now, as the person who is looking, and says how many movements are new.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    public function sync(Request $request, RefreshBanks $refresh): RedirectResponse
    {
        $this->ensureOn();
        Gate::authorize('create', BankTransaction::class);

        $new = $refresh->handle(
            BankConnection::query()->whereIn('status', [BankConnectionStatus::Active, BankConnectionStatus::Error])->orderBy('id')->get(),
            new BankVisitor((string) $request->ip(), (string) $request->userAgent()),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => RefreshBanks::message($new)]);

        return back(fallback: route('budget.bank.index'));
    }

    /**
     * A block of the list, newest first (a pending one by the day the bank gave), with one row more than shown to know
     * whether another block follows.
     *
     * @param  bool  $ignored  the ones put aside instead of the ones to sort
     * @param  int  $page  the number of the block, from 1
     * @return Collection<int, BankTransaction>
     */
    private function block(bool $ignored, int $page): Collection
    {
        return BankTransaction::query()
            ->with('financialAccount')
            ->when(
                $ignored,
                static fn (Builder $query): Builder => $query->ignored(),
                static fn (Builder $query): Builder => $query->whereNull('ignored_at')->whereNull('expense_id')->whereNull('income_id'),
            )
            ->orderByRaw('coalesce(booked_on, value_on) desc')
            ->orderByDesc('id')
            ->skip(($page - 1) * self::PER_PAGE)
            ->take(self::PER_PAGE + 1)
            ->get();
    }

    /**
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    private function ensureOn(): void
    {
        abort_unless(BankFeedAvailability::on(), 404);
    }
}
