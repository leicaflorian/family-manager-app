<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Actions\Banks\RefreshBanks;
use App\Actions\Banks\SettleFromBank;
use App\Actions\Banks\UndoBankPayment;
use App\Data\BankPlannedChoiceData;
use App\Data\BankTransactionData;
use App\Enums\BankConnectionStatus;
use App\Enums\BankMatchSource;
use App\Enums\BankTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banks\AssignBankTransactionRequest;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Banks\BankFeedAvailability;
use App\Services\Banks\BankMatcher;
use App\Services\Banks\RecurringBankTexts;
use App\Support\Banks\BankTransactionId;
use App\Support\Banks\BankVisitor;
use App\Support\Banks\SyncHours;
use App\Support\Deadline;
use App\Support\PaymentTitle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
            'rows' => Inertia::merge(function () use ($rows, $ignored): array {
                // One read of the last months for the whole page, none for the ignored ones
                $texts = resolve(RecurringBankTexts::class);

                return $rows()->take(self::PER_PAGE)
                    ->map(static fn (BankTransaction $transaction): BankTransactionData => BankTransactionData::fromTransaction($transaction, ! $ignored && $texts->recurs($transaction)))
                    ->values()
                    ->all();
            })->matchOn('id'),
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
            'sync_hours' => SyncHours::all(),
            // The planned items the sheet *It belongs to a planned expense* offers for the transaction of `?assign=`, asked when it opens
            'planned_options' => Inertia::optional(fn (): array => $this->plannedOptions($request->query('assign'))),
            'can_sort' => Gate::allows('create', BankTransaction::class),
            // «It comes back every month: create a planned expense?» is offered only to who can create one
            'can_plan' => Gate::allows('create', PlannedExpense::class) && Gate::allows('create', PlannedIncome::class),
            'can_change_banks' => Gate::allows('create', BankConnection::class),
        ]);
    }

    /**
     * *It belongs to a planned expense*: the person says which planned item the transaction pays. The closest open due
     * date it has is paid, as a rule would pay it. An admin who can change the planned item and finds it without a rule
     * goes on to its form, with the sheet that writes the rule open from this transaction; everyone else stays here.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction or the planned item is not of the family
     * @throws ValidationException (`planned_id`) when the planned item has no due date to pay
     */
    public function assign(AssignBankTransactionRequest $request, SettleFromBank $settle): RedirectResponse
    {
        $transaction = $request->transaction();
        $planned = $request->planned();
        $payment = $settle->handle($transaction, $planned, BankMatchSource::Assigned);

        if (! $payment instanceof Expense && ! $payment instanceof Income) {
            // Linked, put aside or declared pending meanwhile: it is not in the list any more, nothing to pay twice
            if (! BankTransaction::query()->unsorted()->whereKey($transaction->id)->exists()) {
                return to_route('budget.bank.index');
            }

            throw ValidationException::withMessages(['planned_id' => $planned instanceof PlannedExpense
                ? __('This planned expense has no due dates to pay.')
                : __('This planned income has no due dates to pay.')]);
        }

        $title = $payment->occurrence_date === null ? $payment->title : PaymentTitle::for($planned->displayTitle(), $payment->occurrence_date);
        // A payment that was already there (paid by hand) is only linked; an income arrives, it is not paid
        $message = match (true) {
            ! $payment->wasRecentlyCreated => $payment instanceof Income ? 'Already arrived, now linked: :title' : 'Already paid, now linked: :title',
            $payment instanceof Income => 'Arrived: :title',
            default => 'Paid: :title',
        };
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message, ['title' => $title])]);

        if (blank($planned->bank_match) && Gate::allows('update', $planned)) {
            return to_route('budget.planned.edit', ['kind' => $planned instanceof PlannedExpense ? 'expense' : 'income', 'id' => $planned->id, 'rule_from' => $transaction->id]);
        }

        return to_route('budget.bank.index');
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
            BankConnection::query()->readable()->orderBy('id')->get(),
            new BankVisitor((string) $request->ip(), (string) $request->userAgent()),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => RefreshBanks::message($new)]);

        return back(fallback: route('budget.bank.index'));
    }

    /**
     * *It was not this one*: takes back the payment the bank recorded (only who can delete it, an `admin`). The
     * movement goes, the due date is to pay again and the transaction is back in the list.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction is not of the family
     * @throws HttpException (422) if the payment was not recorded by the bank
     */
    public function undo(BankTransaction $transaction, UndoBankPayment $undo): RedirectResponse
    {
        $this->ensureOn();
        $payment = $transaction->expense ?? $transaction->income;

        if ($payment !== null) {
            Gate::authorize('delete', $payment);
        }

        $undo->handle($transaction);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The due date is to be paid again.')]);

        return to_route('budget.bank.index');
    }

    /**
     * *Ignore*: puts a transaction nobody has to deal with aside (a transfer between the family's own accounts, say).
     * The row is locked and checked again under the lock, because a rule or a settling may link it at the same moment.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction is not of the family
     * @throws HttpException (422) if the transaction is linked to an expense or an income, or still pending
     */
    public function ignore(BankTransaction $transaction): RedirectResponse
    {
        $this->ensureOn();
        Gate::authorize('update', $transaction);

        DB::transaction(function () use ($transaction): void {
            $current = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($current->expense_id !== null || $current->income_id !== null) {
                abort(422, __('This movement is already sorted, so it cannot be ignored.'));
            }

            // The next read rewrites a pending row from scratch, so putting it aside would be lost
            if ($current->status === BankTransactionStatus::Pending) {
                abort(422, __('This movement is still pending at the bank, so it cannot be ignored yet.'));
            }

            $current->ignored_at ??= now();
            $current->save();
        });

        return back(fallback: route('budget.bank.index'));
    }

    /**
     * *Put back among the movements*: takes a transaction out of the ignored ones, so it is to sort again.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction is not of the family
     */
    public function restore(BankTransaction $transaction): RedirectResponse
    {
        $this->ensureOn();
        Gate::authorize('update', $transaction);

        DB::transaction(function () use ($transaction): void {
            $current = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $current->ignored_at = null;
            $current->save();
        });

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
     * The planned expenses (or incomes, for a credit) that have not ended and that the transaction can pay now: each
     * with the open due date it would settle, as the matcher chooses it, in alphabetical order. Another family's or a
     * missing transaction gives none.
     *
     * @param  mixed  $id  `?assign=`, the transaction
     * @return list<BankPlannedChoiceData>
     */
    private function plannedOptions(mixed $id): array
    {
        $id = BankTransactionId::parse($id);
        $transaction = $id !== null ? BankTransaction::query()->unsorted()->find($id) : null;

        if (! $transaction instanceof BankTransaction || $transaction->booked_on === null) {
            return [];
        }

        $day = $transaction->booked_on->toDateString();
        $today = CarbonImmutable::today('Europe/Rome');
        $matcher = resolve(BankMatcher::class);
        $query = $transaction->kind() === 'expense' ? PlannedExpense::query() : PlannedIncome::query();
        $options = [];

        foreach ($query->with('organization')->where(static fn (Builder $query): Builder => $query->whereNull('end_date')->orWhere('end_date', '>=', $day))->orderBy('title')->get() as $planned) {
            $deadline = $matcher->deadline($transaction, $planned);

            if ($deadline instanceof Deadline && ! $deadline->isPaid() && ! $deadline->isSkipped()) {
                $options[] = BankPlannedChoiceData::fromDeadline($planned, $deadline, $today);
            }
        }

        return $options;
    }

    /**
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    private function ensureOn(): void
    {
        abort_unless(BankFeedAvailability::on(), 404);
    }
}
