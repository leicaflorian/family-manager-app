<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Actions\Banks\AcceptBankProposal;
use App\Actions\Banks\AttachBankFee;
use App\Actions\Banks\LinkRecordedMovement;
use App\Actions\Banks\MarkBankTransfer;
use App\Actions\Banks\RefreshBanks;
use App\Actions\Banks\RejectBankProposal;
use App\Actions\Banks\RestoreBankTransaction;
use App\Actions\Banks\SettleFromBank;
use App\Actions\Banks\UndoBankPayment;
use App\Data\BankFeeData;
use App\Data\BankFilterData;
use App\Data\BankGroupData;
use App\Data\BankGroupRowData;
use App\Data\BankTransactionData;
use App\Enums\BankConnectionStatus;
use App\Enums\BankFilterAction;
use App\Enums\BankMatchSource;
use App\Enums\BankTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banks\AssignBankTransactionRequest;
use App\Http\Requests\Banks\AttachBankFeeRequest;
use App\Http\Requests\Banks\BankCandidatesRequest;
use App\Http\Requests\Banks\BankListRequest;
use App\Http\Requests\Banks\BankProposalRequest;
use App\Http\Requests\Banks\LeaveBankGroupRequest;
use App\Http\Requests\Banks\MarkBankTransferRequest;
use App\Models\BankConnection;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Services\Banks\BankFees;
use App\Services\Banks\BankFilterPreview;
use App\Services\Banks\BankFilters;
use App\Services\Banks\BankGroupProposals;
use App\Services\Banks\BankGroups;
use App\Services\Banks\BankLinkSearch;
use App\Services\Banks\BankProposals;
use App\Services\Banks\BankRows;
use App\Services\Banks\BankSimilar;
use App\Services\Banks\RecurringBankTexts;
use App\Support\Banks\BankFilterDraft;
use App\Support\Banks\BankGroup;
use App\Support\Banks\BankText;
use App\Support\Banks\BankVisitor;
use App\Support\Banks\SyncHours;
use App\Support\PaymentTitle;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Support\SessionKey;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
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
     * `direction` (`?show=ignored` is *All* with the status *ignored*); *Filters* lists no movement, only the filters
     * (`bank_filters`).
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
        $bankGroups = resolve(BankGroups::class);
        // The movements the groups are made from, read once for `rows`, `groups`, `counts` and `group_rows`
        $grouped = null;
        $groupsRead = static function () use (&$grouped, $show, $bankGroups): array {
            if ($grouped === null) {
                $read = $show === 'unsorted' ? $bankGroups->read() : null;
                $grouped = ['read' => $read, 'groups' => $read === null ? [] : $bankGroups->all($read)];
            }

            return $grouped;
        };
        // What each group proposes, for `groups` and `group_rows`, left without a Pay for who cannot create a filter that pays
        $proposed = null;
        $proposalsRead = static function () use (&$proposed, $groupsRead): array {
            return $proposed ??= resolve(BankGroupProposals::class)->visibleFor(new SupportCollection($groupsRead()['groups']));
        };
        // One query for both `rows` and `next`, run the first time either is asked
        $rows = function () use (&$block, $show, $all, $filters, $page, $bankFees, $bankGroups, $groupsRead): array {
            return $block ??= match ($show) {
                'all' => [...$all->page($filters, $page), 'fees' => [], 'orphans' => []],
                'filters' => ['rows' => new Collection, 'more' => false, 'fees' => [], 'orphans' => []],
                default => $this->unsortedBlock($page, $bankFees, $bankGroups, $groupsRead()['read']),
            };
        };

        return Inertia::render('budget/BankTransactions', [
            'show' => $show,
            'rows' => Inertia::merge(function () use ($rows, $show, $groupsRead): array {
                // One read of the last months for the whole page, none for the other lists
                $texts = resolve(RecurringBankTexts::class);
                // One read of the filters for the whole page: a row to sort that two filters take names them
                $bankFilters = resolve(BankFilters::class);
                $block = $rows();
                $fees = $block['fees'];
                $orphans = $block['orphans'];
                // «Maybe it is…» under the lone movements to sort, read once for the block
                $proposals = $show === 'unsorted' ? resolve(BankProposals::class)->for($block['rows'], $groupsRead()['read']) : [];

                return $block['rows']
                    ->map(static fn (BankTransaction $transaction): BankTransactionData => BankTransactionData::fromTransaction(
                        $transaction,
                        $show === 'unsorted' && $texts->recurs($transaction),
                        $show === 'all',
                        $fees[$transaction->id] ?? null,
                        in_array($transaction->id, $orphans, true),
                        $proposals[$transaction->id] ?? null,
                        $show === 'unsorted' ? $bankFilters->namesWhenShared($transaction) : null,
                    ))
                    ->values()
                    ->all();
            })->matchOn('id'),
            'next' => fn (): ?int => $rows()['more'] ? $page + 1 : null,
            // The tab *To sort*: the groups of movements that look alike, biggest first, with what the system proposes for each
            'groups' => fn (): array => $this->groupsData($groupsRead()['groups'], $proposalsRead()),
            // The movements of the group of `?group=` and what its proposal would do with each, asked by the page when a group opens
            'group_rows' => Inertia::optional(fn (): array => $this->groupRows($groupsRead()['groups'], $proposalsRead(), $filters->query('group'), $bankFees)),
            'counts' => function () use ($show, $groupsRead, $bankGroups): array {
                $grouped = $groupsRead();

                if ($show !== 'unsorted') {
                    return ['unsorted' => 0, 'groups' => 0, 'lone' => 0];
                }

                return [
                    'unsorted' => BankTransaction::query()->unsorted()->count(),
                    'groups' => count($grouped['groups']),
                    'lone' => $bankGroups->lone($grouped['read'])->count(),
                ];
            },
            // The list *All*: what the filters find, the values chosen and the accounts to choose from
            'total' => fn (): ?array => $show === 'all' ? $all->total($filters) : null,
            'filters' => fn (): ?array => $show === 'all' ? $filters->chosen() : null,
            'accounts' => fn (): array => $show === 'all' ? $all->accounts() : [],
            // The tab *Filters*: every filter of the family by name, with how many movements of the last 90 days it settled and what it lacks
            'bank_filters' => fn (): array => $show === 'filters' ? array_values(BankFilter::query()
                ->forPage()
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(BankFilterData::fromFilter(...))
                ->all()) : [],
            // *It is a transfer*: the open accounts of the family, to choose the one on the other side
            'accounts_all' => fn (): array => $show === 'unsorted' ? $all->transferAccounts() : [],
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
     * @return JsonResponse `{planned: [...], recorded: [...]}`; with `fee=1` (the commission left alone) only the recorded expenses, and `organization`, the name that gave the first list
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction is not of the family
     */
    public function candidates(BankCandidatesRequest $request, BankLinkSearch $search): JsonResponse
    {
        return response()->json($request->forFee()
            ? $search->searchForFee($request->transaction(), $request->searchText())
            : $search->search($request->transaction(), $request->searchText()));
    }

    /**
     * *It belongs to an expense*: the person says which planned item the transaction pays, or which expense or income
     * already recorded it is. Of a planned item, the due date chosen is paid, or else the closest open one, as a rule
     * would pay it. Whoever may make a filter that pays and finds the planned item without one goes on to the
     * form of the new filter (`?planned=` and `?from=`), filled from this transaction; everyone else stays here.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction or the planned item is not of the family
     * @throws ValidationException (`planned_id`) when the planned item has no due date to pay; (`movement_id`) when the recorded movement cannot be linked
     */
    public function assign(AssignBankTransactionRequest $request, SettleFromBank $settle, LinkRecordedMovement $link, BankSimilar $similar): SymfonyResponse
    {
        $transaction = $request->transaction();
        $movement = $request->movement();

        if ($movement instanceof Expense || $movement instanceof Income) {
            $linked = $link->handle($transaction, $movement);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Linked: :title', ['title' => $linked instanceof Expense ? $linked->title : $linked->label()])]);
            $similar->offer($transaction);

            return $this->answer(to_route('budget.bank.index'));
        }

        $planned = $request->planned();
        $payment = $settle->handle($transaction, $planned, BankMatchSource::Assigned, $request->occurrence());

        if (! $payment instanceof Expense && ! $payment instanceof Income) {
            // Linked, put aside or declared pending meanwhile: it is not in the list any more, nothing to pay twice
            if (! BankTransaction::query()->unsorted()->whereKey($transaction->id)->exists()) {
                return $this->answer(to_route('budget.bank.index'));
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

        // Without a filter that pays it, whoever may make one is led to the form of the new filter, filled from here
        if (Gate::allows('create', [BankFilter::class, BankFilterAction::Pay]) && ! BankFilter::existsPayingFor($planned)) {
            return $this->answer(to_route('budget.bank.filters.create', ['planned' => ($planned instanceof PlannedExpense ? 'expense:' : 'income:').$planned->id, 'from' => $transaction->id]));
        }

        // The form of the new filter already takes the similar movements, so the page has nothing more to offer
        $similar->offer($transaction);

        return $this->answer(to_route('budget.bank.index'));
    }

    /**
     * *Yes* on «Maybe it is…»: links the expense or income already recorded, pays the due date or marks the transfer
     * the proposal of the movement says (`key`). After a due date the filter that pays it from then on may be saved,
     * and the message says so (`filter_id` in the message, for *Edit the filter*).
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction is not of the family
     * @throws ValidationException (`key`) when the key is not, or not any more, the proposal of the movement
     */
    public function accept(BankProposalRequest $request, AcceptBankProposal $accept, #[CurrentUser] User $user): RedirectResponse
    {
        $key = $request->proposalKey();
        $result = $accept->handle($request->transaction(), $key, $user);
        $payment = $result['payment'];
        $filter = $result['filter'];

        if ($payment === null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Marked as a transfer between your accounts.')]);

            return to_route('budget.bank.index');
        }

        $planned = $payment instanceof Expense ? $payment->plannedExpense : $payment->plannedIncome;
        $title = str_starts_with($key, 'planned_') && $planned !== null && $payment->occurrence_date !== null
            ? PaymentTitle::for($planned->displayTitle(), $payment->occurrence_date)
            : $payment->label();
        $message = match (true) {
            ! str_starts_with($key, 'planned_') => 'Linked: :title',
            $payment instanceof Income => $filter !== null ? 'Arrived: :title · from now on it is recorded by itself' : 'Arrived: :title',
            default => $filter !== null ? 'Paid: :title · from now on it pays itself' : 'Paid: :title',
        };
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message, ['title' => $title]), ...($filter !== null ? ['filter_id' => $filter->id] : [])]);

        return to_route('budget.bank.index');
    }

    /**
     * *No* on «Maybe it is…»: the movement remembers the key, so the proposal does not come back and the next
     * candidate can be proposed.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction is not of the family
     */
    public function reject(BankProposalRequest $request, RejectBankProposal $reject): RedirectResponse
    {
        $reject->handle($request->transaction(), $request->proposalKey());

        return back(fallback: route('budget.bank.index'));
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
     * *It was not this one*: takes back what the bank did. A payment it recorded goes (only who can delete it, an
     * `admin`): the due date is to pay again. A link to an expense or income that was already there only comes off
     * (who can change the transaction, a `member` too) and the movement stays. The transaction is back in the list.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction is not of the family
     * @throws HttpException (422) if the payment was not recorded by the bank
     */
    public function undo(BankTransaction $transaction, UndoBankPayment $undo): RedirectResponse
    {
        $payment = $transaction->expense ?? $transaction->income;

        // A link to a movement that was already there only comes off (who can change the transaction may); a payment the bank recorded is deleted
        $detach = $transaction->matched_by === BankMatchSource::Duplicate;

        if ($detach) {
            Gate::authorize('update', $transaction);
        } elseif ($payment !== null) {
            Gate::authorize('delete', $payment);
        }

        $undo->handle($transaction, $detach);
        Inertia::flash('toast', ['type' => 'success', 'message' => $detach ? __('The movement is unlinked and is to sort again.') : __('The due date is to be paid again.')]);

        return to_route('budget.bank.index');
    }

    /**
     * *Ignore*: puts a transaction nobody has to deal with aside (a transfer between the family's own accounts, say).
     * The row is locked and checked again under the lock, because a rule or a settling may link it at the same moment.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction is not of the family
     * @throws HttpException (422) if the transaction is linked to an expense or an income, or still pending
     */
    public function ignore(BankTransaction $transaction): SymfonyResponse
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

        return $this->answer(back(fallback: route('budget.bank.index')));
    }

    /**
     * *It is not of this group*: the movement leaves its group and never comes back to it (`group:<key>` among its
     * rejected proposals); it is a lone movement to sort from now on. The form of a filter asks it as JSON and gets no
     * body; the page of the groups comes back to where it was.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction is not of the family
     */
    public function leave(LeaveBankGroupRequest $request, RejectBankProposal $reject): SymfonyResponse
    {
        $reject->handle($request->transaction(), 'group:'.$request->groupKey());

        return $this->answer(back(fallback: route('budget.bank.index')));
    }

    /**
     * *Put back among the movements*: takes a transaction out of the ignored ones, or a transfer back to a movement
     * to sort (both halves of the transfer), so it is to sort again.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction is not of the family
     */
    public function restore(BankTransaction $transaction, RestoreBankTransaction $restore): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        $restore->handle($transaction);

        return back(fallback: route('budget.bank.index'));
    }

    /**
     * *It is a transfer*: the person says the movement went to or came from another account of the family, so it is
     * not an expense nor an income. The other half is paired when it is already on the list.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction or the account is not of the family
     * @throws ValidationException (`account_id`) when the account is the one of the movement
     */
    public function transfer(MarkBankTransferRequest $request, MarkBankTransfer $mark, BankSimilar $similar): SymfonyResponse
    {
        $transaction = $request->transaction();
        $mark->handle($transaction, $request->account());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marked as a transfer between your accounts.')]);
        $similar->offer($transaction);

        return $this->answer(to_route('budget.bank.index'));
    }

    /**
     * *It is the commission of…*: the person says which expense a commission left alone belongs to; its amount joins
     * the amount and the fee of the expense.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family, or when the transaction or the expense is not of the family
     * @throws ValidationException (`expense_id`) when the movement is not a commission to sort or the expense has a commission from the bank
     */
    public function fee(AttachBankFeeRequest $request, AttachBankFee $attach): RedirectResponse
    {
        $transaction = $request->transaction();
        $expense = $request->expense();

        // The action checks under its locks that the commission is still to sort and the expense has none
        $attached = BankText::isFee($transaction->text) && $attach->handle($transaction, $expense);

        if (! $attached) {
            $taken = BankTransaction::query()->where('expense_id', $expense->id)->where('matched_by', BankMatchSource::Fee)->exists();

            throw ValidationException::withMessages(['expense_id' => $taken
                ? __('This expense already has a commission from the bank.')
                : __('This movement is not a commission to sort.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Linked: :title', ['title' => $expense->title])]);

        return to_route('budget.bank.index');
    }

    /**
     * The answer of an action on a movement: the page it leads to for a visit, an empty answer for the form of a
     * filter, which asks with JSON and stays where it is. The form shows its own message, so the toast and the offer
     * of similar movements written for the next page are taken back; anything else flashed before stays.
     *
     * @param  RedirectResponse  $redirect  where a visit goes
     */
    private function answer(RedirectResponse $redirect): SymfonyResponse
    {
        if (! request()->expectsJson()) {
            return $redirect;
        }

        $flashed = session()->get(SessionKey::FLASH_DATA, []);
        $kept = is_array($flashed) ? Arr::except($flashed, ['toast', 'similar']) : [];

        if ($kept === []) {
            session()->forget(SessionKey::FLASH_DATA);
        } else {
            session()->put(SessionKey::FLASH_DATA, $kept);
        }

        return response()->noContent();
    }

    /**
     * A block of the list *To sort*, newest first (a pending one by the day the bank gave), with one row more than
     * shown to know whether another block follows. A commission paired with a direct debit is not a row: its debit
     * carries it (`fees`, by the id of the debit). One whose debit is still to sort, on this block or another, is left
     * out of its own block and shown under that debit; `BankFees::onPage()` decides both from `debitOf()`, so it shows
     * once. One with no debit to sort is a row, an orphan (`orphans`, by id).
     *
     * @param  int  $page  the number of the block, from 1
     * @param  BankGroups  $groups  gives the movements that are in no group
     * @param  array{0: SupportCollection<int, BankTransaction>, 1: list<int>}|null  $read  what `BankGroups::read()` gave
     * @return array{rows: Collection<int, BankTransaction>, more: bool, fees: array<int, BankTransaction>, orphans: list<int>}
     */
    private function unsortedBlock(int $page, BankFees $bankFees, BankGroups $groups, ?array $read): array
    {
        $rows = $groups->lone($read)
            ->with('financialAccount')
            ->reorder()
            ->orderByRaw('coalesce(booked_on, value_on) desc')
            ->orderByDesc('id')
            ->skip(($page - 1) * BankRows::PER_PAGE)
            ->take(BankRows::PER_PAGE + 1)
            ->get();

        $shown = $rows->take(BankRows::PER_PAGE)->values();
        $found = $bankFees->onPage($shown);
        $rest = $shown->reject(static fn (BankTransaction $row): bool => in_array($row->id, $found['hidden'], true))->values();

        return ['rows' => $rest, 'more' => $rows->count() > BankRows::PER_PAGE, 'fees' => $found['fees'], 'orphans' => $found['orphans']];
    }

    /**
     * The groups of the tab *To sort* as the page gets them, with the proposal of each (read once for all of them).
     *
     * @param  list<BankGroup>  $groups  the groups, biggest first
     * @param  array<string, BankFilterDraft|null>  $proposals  what each proposes (`BankGroupProposals::visibleFor()`)
     * @return list<BankGroupData>
     */
    private function groupsData(array $groups, array $proposals): array
    {
        $texts = resolve(RecurringBankTexts::class);

        return array_map(static function (BankGroup $group) use ($proposals, $texts): BankGroupData {
            $chain = $texts->groupChain($group->transactions);
            $newest = $chain === null ? null : end($chain);

            return BankGroupData::fromGroup(
                $group,
                $proposals[$group->key] ?? null,
                $newest === null || $newest === false ? null : route('budget.planned.create', ['kind' => $group->kind, 'bank' => $newest->id], false),
            );
        }, $groups);
    }

    /**
     * The movements of the group a key names, newest first, each with what the proposal of the group would do with
     * it. Nothing when no key is given or the group is gone.
     *
     * @param  list<BankGroup>  $groups  the groups, biggest first
     * @param  array<string, BankFilterDraft|null>  $proposals  what each proposes (`BankGroupProposals::visibleFor()`)
     * @param  mixed  $key  the value of `?group=`
     * @return list<BankGroupRowData>
     */
    private function groupRows(array $groups, array $proposals, mixed $key, BankFees $bankFees): array
    {
        $group = is_string($key) ? array_find($groups, static fn (BankGroup $group): bool => $group->key === $key) : null;

        if (! $group instanceof BankGroup) {
            return [];
        }

        $draft = BankFilterDraft::fromGroup($group, $proposals[$group->key] ?? null);
        $rows = new Collection($group->transactions->all());
        $forecasts = resolve(BankFilterPreview::class)->forGroup($rows, $draft);
        $fees = $bankFees->onPage($rows)['fees'];

        return array_values($rows->map(static fn (BankTransaction $row): BankGroupRowData => new BankGroupRowData(
            BankTransactionData::fromTransaction($row),
            isset($fees[$row->id]) ? BankFeeData::fromTransaction($fees[$row->id]) : null,
            $forecasts[$row->id]['will'],
            $forecasts[$row->id]['title'],
            $forecasts[$row->id]['on'],
        ))->all());
    }
}
