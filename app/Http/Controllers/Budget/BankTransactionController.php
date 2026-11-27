<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Actions\Banks\LinkRecordedMovement;
use App\Actions\Banks\RefreshBanks;
use App\Actions\Banks\SettleFromBank;
use App\Actions\Banks\UndoBankPayment;
use App\Data\BankTransactionData;
use App\Enums\BankConnectionStatus;
use App\Enums\BankMatchSource;
use App\Enums\BankTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banks\AssignBankTransactionRequest;
use App\Http\Requests\Banks\BankCandidatesRequest;
use App\Http\Requests\Banks\BankListRequest;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Banks\BankFees;
use App\Services\Banks\BankLinkSearch;
use App\Services\Banks\BankRows;
use App\Services\Banks\RecurringBankTexts;
use App\Support\Banks\BankText;
use App\Support\Banks\BankVisitor;
use App\Support\Banks\SyncHours;
use App\Support\PaymentTitle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
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
    /**
     * The page: a block of the list (added below the ones already shown) under `?show=unsorted`, `?show=all` or
     * `?show=filters`. *All* lists every transaction the bank gave, filtered by `q`, `account`, `status`, `month` and
     * `direction` (`?show=ignored` is *All* with the status *ignored*); *Filters* lists no movement until its task.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    public function index(BankListRequest $filters, BankRows $all, BankFees $bankFees): Response
    {
        $show = match ($filters->query('show')) {
            'all', 'ignored' => 'all',
            'filters' => 'filters',
            default => 'unsorted',
        };
        $page = max(1, $filters->integer('page'));
        $block = null;
        // One query for both `rows` and `next`, run the first time either is asked
        $rows = function () use (&$block, $show, $all, $filters, $page, $bankFees): array {
            return $block ??= match ($show) {
                'all' => [...$all->page($filters, $page), 'fees' => [], 'orphans' => []],
                'filters' => ['rows' => new Collection, 'more' => false, 'fees' => [], 'orphans' => []],
                default => $this->unsortedBlock($page, $bankFees),
            };
        };

        return Inertia::render('budget/BankTransactions', [
            'show' => $show,
            'rows' => Inertia::merge(function () use ($rows, $show): array {
                // One read of the last months for the whole page, none for the other lists
                $texts = resolve(RecurringBankTexts::class);
                $block = $rows();
                $fees = $block['fees'];
                $orphans = $block['orphans'];

                // A commission never comes back every month: it is the debit that does
                return $block['rows']
                    ->map(static fn (BankTransaction $transaction): BankTransactionData => BankTransactionData::fromTransaction(
                        $transaction,
                        $show === 'unsorted' && ! BankText::isFee($transaction->text) && $texts->recurs($transaction),
                        $show === 'all',
                        $fees[$transaction->id] ?? null,
                        in_array($transaction->id, $orphans, true),
                    ))
                    ->values()
                    ->all();
            })->matchOn('id'),
            'next' => fn (): ?int => $rows()['more'] ? $page + 1 : null,
            // The list *All*: what the filters find, the values chosen and the accounts to choose from
            'total' => fn (): ?array => $show === 'all' ? $all->total($filters) : null,
            'filters' => fn (): ?array => $show === 'all' ? $filters->chosen() : null,
            'accounts' => fn (): array => $show === 'all' ? $all->accounts() : [],
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
            'can_sort' => Gate::allows('create', BankTransaction::class),
            // «It comes back every month: create a planned expense?» is offered only to who can create one
            'can_plan' => Gate::allows('create', PlannedExpense::class) && Gate::allows('create', PlannedIncome::class),
            'can_change_banks' => Gate::allows('create', BankConnection::class),
        ]);
    }

    /**
     * The search of *It belongs to an expense*: the planned items and the recorded expenses (or incomes, for a credit)
     * the transaction can be linked to, for the words in `q`.
     *
     * @return JsonResponse `{planned: [...], recorded: [...]}`
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction is not of the family
     */
    public function candidates(BankCandidatesRequest $request, BankLinkSearch $search): JsonResponse
    {
        return response()->json($search->search($request->transaction(), $request->searchText()));
    }

    /**
     * *It belongs to an expense*: the person says which planned item the transaction pays, or which expense or income
     * already recorded it is. Of a planned item, the due date chosen is paid, or else the closest open one, as a rule
     * would pay it. An admin who can change the planned item and finds it without a rule
     * goes on to its form, with the sheet that writes the rule open from this transaction; everyone else stays here.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction or the planned item is not of the family
     * @throws ValidationException (`planned_id`) when the planned item has no due date to pay; (`movement_id`) when the recorded movement cannot be linked
     */
    public function assign(AssignBankTransactionRequest $request, SettleFromBank $settle, LinkRecordedMovement $link): RedirectResponse
    {
        $transaction = $request->transaction();
        $movement = $request->movement();

        if ($movement instanceof Expense || $movement instanceof Income) {
            $linked = $link->handle($transaction, $movement);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Linked: :title', ['title' => $linked instanceof Expense ? $linked->title : $linked->label()])]);

            return to_route('budget.bank.index');
        }

        $planned = $request->planned();
        $payment = $settle->handle($transaction, $planned, BankMatchSource::Assigned, $request->occurrence());

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
        Gate::authorize('update', $transaction);

        DB::transaction(function () use ($transaction): void {
            $current = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $current->ignored_at = null;
            $current->save();
        });

        return back(fallback: route('budget.bank.index'));
    }

    /**
     * A block of the list *To sort*, newest first (a pending one by the day the bank gave), with one row more than
     * shown to know whether another block follows. A commission paired with a direct debit is not a row: its debit
     * carries it (`fees`, by the id of the debit). One whose debit is still to sort but on another block is left out
     * of this one, and shows under its debit on the block of the debit (`pairs()` reads the commissions that sit
     * outside the block). One with no debit to sort is a row, an orphan (`orphans`, by id).
     *
     * @param  int  $page  the number of the block, from 1
     * @return array{rows: Collection<int, BankTransaction>, more: bool, fees: array<int, BankTransaction>, orphans: list<int>}
     */
    private function unsortedBlock(int $page, BankFees $bankFees): array
    {
        $rows = BankTransaction::query()
            ->with('financialAccount')
            ->whereNull('ignored_at')
            ->whereNull('expense_id')
            ->whereNull('income_id')
            ->orderByRaw('coalesce(booked_on, value_on) desc')
            ->orderByDesc('id')
            ->skip(($page - 1) * BankRows::PER_PAGE)
            ->take(BankRows::PER_PAGE + 1)
            ->get();

        $shown = $rows->take(BankRows::PER_PAGE)->values();
        $fees = $bankFees->pairs($shown);
        $paired = array_map(static fn (BankTransaction $fee): int => $fee->id, $fees);
        $orphans = [];
        $rest = $shown->filter(function (BankTransaction $row) use ($bankFees, $paired, &$orphans): bool {
            if (! BankText::isFee($row->text)) {
                return true;
            }

            // Shown under its debit
            if (in_array($row->id, $paired, true)) {
                return false;
            }

            // Its debit may be on another block: then the commission is shown there, under the debit
            if ($bankFees->debitOf($row) instanceof BankTransaction) {
                return false;
            }

            $orphans[] = $row->id;

            return true;
        })->values();

        return ['rows' => $rest, 'more' => $rows->count() > BankRows::PER_PAGE, 'fees' => $fees, 'orphans' => $orphans];
    }
}
