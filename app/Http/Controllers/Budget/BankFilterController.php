<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Actions\Banks\SaveBankFilter;
use App\Contracts\DocumentReader;
use App\Data\BankFilterData;
use App\Enums\BankFilterAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banks\BankFilterPreviewRequest;
use App\Http\Requests\Banks\SaveBankFilterRequest;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\PlannedExpense;
use App\Services\Banks\BankFilterPreview;
use App\Services\Banks\BankRows;
use App\Services\BudgetFormOptions;
use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankFilterDraft;
use App\Support\Banks\BankText;
use App\Support\PlannedKind;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *From the bank → Filters*: the form of a filter, new or changed, with the movements it takes while it is written,
 * and saving and deleting it. Everyone of the family sees the filters; who saves which one says `BankFilterPolicy`.
 * Every address is 404 while the link to the bank is off.
 */
final class BankFilterController extends Controller
{
    /**
     * The form of a new filter, filled when it comes from somewhere: `?planned=expense:12` pays that planned item
     * (only for who may create a filter that pays), `?from=<movement>` takes the words, the direction and the account
     * of a movement, with `COMMISSIONI` among the words it must not have.
     *
     * @throws NotFoundHttpException while the link to the bank is off, or when the planned item or the movement is not of the family
     */
    public function create(Request $request, BankFilterPreview $preview, BankRows $rows): Response
    {
        Gate::authorize('viewAny', BankFilter::class);

        return $this->form(null, $this->draft($request), $preview, $rows);
    }

    /**
     * Saves a new filter and lets it sort at once what it takes; the toast says how many.
     *
     * @throws NotFoundHttpException while the link to the bank is off, or when an id named is not of the family
     */
    public function store(SaveBankFilterRequest $request, SaveBankFilter $save): RedirectResponse
    {
        return $this->saved($save->handle($request->validated()));
    }

    /**
     * The form of a saved filter, with the movements it takes now.
     *
     * @throws NotFoundHttpException while the link to the bank is off, or when the filter is not of the family
     */
    public function edit(BankFilter $filter, BankFilterPreview $preview, BankRows $rows): Response
    {
        Gate::authorize('view', $filter);

        return $this->form($filter, null, $preview, $rows);
    }

    /**
     * Saves a changed filter and lets it sort at once what it takes now; the toast says how many.
     *
     * @throws NotFoundHttpException while the link to the bank is off, or when the filter or an id named is not of the family
     */
    public function update(SaveBankFilterRequest $request, BankFilter $filter, SaveBankFilter $save): RedirectResponse
    {
        return $this->saved($save->handle($request->validated(), $filter));
    }

    /**
     * Deletes a filter. What it sorted stays as it is: the expenses it created and the links it made remain, the
     * movements only lose the name of the filter.
     *
     * @throws NotFoundHttpException while the link to the bank is off, or when the filter is not of the family
     */
    public function destroy(BankFilter $filter): RedirectResponse
    {
        Gate::authorize('delete', $filter);

        $filter->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Filter deleted. What it sorted stays as it is.')]);

        return to_route('budget.bank.index', ['show' => 'filters']);
    }

    /**
     * The movements a filter being written takes and what saving it would do. Writes nothing.
     *
     * @throws NotFoundHttpException while the link to the bank is off, or when an id named is not of the family
     */
    public function preview(BankFilterPreviewRequest $request, BankFilterPreview $preview): JsonResponse
    {
        return response()->json($preview->for($request->criteria(), $request->filterColumns(), $request->changing()));
    }

    /**
     * The page of the form: the filter or the proposal, the preview of what it takes, the choices of its fields.
     *
     * @param  BankFilter|null  $filter  the filter being changed
     * @param  BankFilterDraft|null  $draft  the proposal of a new filter
     */
    private function form(?BankFilter $filter, ?BankFilterDraft $draft, BankFilterPreview $preview, BankRows $rows): Response
    {
        $canPay = Gate::allows('create', [BankFilter::class, BankFilterAction::Pay]);
        $columns = $filter?->only($filter->getFillable()) ?? $draft?->toArray() ?? [];
        $criteria = $filter?->criteria() ?? BankFilterCriteria::fromArray([
            'contains' => $columns['contains'] ?? null,
            'excludes' => $columns['excludes'] ?? null,
            'direction' => $columns['direction'] ?? null,
            'accountId' => $columns['financial_account_id'] ?? null,
            'amountMin' => $columns['amount_min'] ?? null,
            'amountMax' => $columns['amount_max'] ?? null,
            'pattern' => $columns['pattern'] ?? null,
        ]);
        $options = BudgetFormOptions::forMovement();

        return Inertia::render('budget/BankFilter', [
            'filter' => $filter instanceof BankFilter ? BankFilterData::fromFilter(
                $filter->load(['financialAccount', 'plannedExpense', 'plannedIncome', 'transferAccount'])->loadCount('transactions'),
            ) : null,
            'draft' => $draft?->toData(),
            'preview' => $preview->for($criteria, $columns, $filter?->id),
            // The accounts the bank reads, to choose the one the filter reads, and the open ones for a transfer
            'options' => [
                ...$options,
                'accounts' => $rows->accounts(),
                'transfer_accounts' => $rows->transferAccounts(),
                'planned' => $canPay ? $options['planned'] : [],
            ],
            'can_pay' => $canPay,
            'ai' => resolve(DocumentReader::class)->isActive(),
        ]);
    }

    /**
     * The proposal of a new filter from the address, null when it comes from nowhere. A planned item is taken only by
     * who may create a filter that pays it.
     *
     * @throws ModelNotFoundException if the movement is not of the family
     * @throws NotFoundHttpException if the planned item is not of the family
     */
    private function draft(Request $request): ?BankFilterDraft
    {
        $planned = null;
        $from = null;
        $plannedKey = $request->query('planned');
        $fromId = $request->query('from');

        if (is_string($plannedKey) && preg_match('/^(expense|income):(\d+)$/', $plannedKey, $parts) === 1
            && Gate::allows('create', [BankFilter::class, BankFilterAction::Pay])) {
            $planned = PlannedKind::find($parts[1], $parts[2]);
        }

        if (is_string($fromId) && ctype_digit($fromId)) {
            $from = BankTransaction::query()->findOrFail((int) $fromId);
        }

        if ($planned === null && ! $from instanceof BankTransaction) {
            return null;
        }

        $counterparty = trim((string) $from?->counterparty);

        return new BankFilterDraft(
            name: $planned?->displayTitle() ?? mb_substr($counterparty, 0, 100),
            contains: $from instanceof BankTransaction ? BankText::words($from->text) : [],
            excludes: $from instanceof BankTransaction ? ['COMMISSIONI'] : [],
            direction: $planned === null ? $from->kind() : ($planned instanceof PlannedExpense ? 'expense' : 'income'),
            accountId: $from?->financial_account_id,
            action: $planned === null ? null : BankFilterAction::Pay,
            plannedExpenseId: $planned instanceof PlannedExpense ? $planned->id : null,
            plannedIncomeId: $planned !== null && ! $planned instanceof PlannedExpense ? $planned->id : null,
        );
    }

    /**
     * Back to the tab *Filters* after a save, with how many movements the filter sorted.
     *
     * @param  array{filter: BankFilter, settled: int}  $result  what the save gave
     */
    private function saved(array $result): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            '{0} Filter saved.|{1} Filter saved: it sorted 1 movement.|[2,*] Filter saved: it sorted :count movements.',
            $result['settled'],
        )]);

        return to_route('budget.bank.index', ['show' => 'filters']);
    }
}
