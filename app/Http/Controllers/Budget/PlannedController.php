<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Actions\Budget\SavePlanned;
use App\Contracts\DocumentReader;
use App\Data\BankTransactionData;
use App\Data\DeadlineData;
use App\Data\PlannedFiltersData;
use App\Data\PlannedRowData;
use App\Enums\Feature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\PlannedIndexRequest;
use App\Http\Requests\Budget\SavePlannedRequest;
use App\Models\BankTransaction;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Services\Banks\RecurringBankTexts;
use App\Services\BudgetFormOptions;
use App\Services\Deadlines;
use App\Services\FamilyFeatures;
use App\Services\PlannedSummaries;
use App\Support\Banks\BankMatch;
use App\Support\Banks\BankTransactionId;
use App\Support\Deadline;
use App\Support\LocalPath;
use App\Support\PlannedKind;
use App\Support\PlannedPreset;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *Planned expenses*, *A planned expense* and its form. Everyone sees them; only an `admin` adds, changes and
 * deletes them. The kind in the address is `expense` or `income`.
 */
final class PlannedController extends Controller
{
    public function __construct(private readonly PlannedSummaries $summaries) {}

    /**
     * *Planned expenses*: the active expenses and incomes by next due date, the monthly averages and the ended ones,
     * narrowed by the search and the filters of the address.
     */
    public function index(PlannedIndexRequest $request, #[CurrentUser] User $user): Response
    {
        $filters = $request->filters();

        return Inertia::render('budget/Planned', [
            ...$this->summaries->index(CarbonImmutable::today('Europe/Rome'), $filters),
            'filters' => PlannedFiltersData::fromFilters($filters),
            'canEdit' => $user->can('create', PlannedExpense::class),
        ]);
    }

    /**
     * *New planned expense*: the empty form, filled in from the card of `?card=type:id` (for an income, its employment)
     * or from the bank transaction of `?bank=` whose text comes back every month (*It comes back every month* of *From the bank*).
     *
     * @throws NotFoundHttpException if the kind is not `expense` or `income`
     */
    public function create(Request $request, string $kind): Response
    {
        Gate::authorize('create', PlannedKind::modelClass($kind));

        // The card of an income is its employment
        $preset = $kind === 'expense' ? PlannedPreset::fromQuery($request->query('card')) : PlannedPreset::incomeFromQuery($request->query('card'));

        return $this->form($request, $kind, null, [...PlannedPreset::defaults($kind), ...$preset, ...$this->recurringValues($request->query('bank'), $kind)]);
    }

    /**
     * Adds the planned item and opens its page.
     *
     * @throws NotFoundHttpException if the kind is not `expense` or `income`
     */
    public function store(SavePlannedRequest $request, SavePlanned $save): RedirectResponse
    {
        $planned = $save->handle($request->kind(), $request->columns());

        return to_route('budget.planned.show', ['kind' => $request->kind(), 'id' => $planned->id]);
    }

    /**
     * *A planned expense*: its year, next due dates, exceptions, data and payments.
     *
     * @throws NotFoundHttpException if the kind is unknown or the planned item is not of the family
     */
    public function show(Request $request, string $kind, string $id, #[CurrentUser] User $user, Deadlines $deadlines): Response
    {
        $today = CarbonImmutable::today('Europe/Rome');
        $planned = PlannedKind::find($kind, $id);
        $opening = $this->opening($deadlines, $planned, $request->query('deadline'));

        return Inertia::render('budget/PlannedItem', [
            'kind' => $kind,
            'detail' => $this->summaries->detail($planned, $user, $today),
            'accounts' => BudgetFormOptions::accounts(),
            'opening' => $opening instanceof Deadline ? DeadlineData::fromDeadline($opening, $today) : null,
            'pay' => $opening instanceof Deadline && $request->boolean('pay'),
            'back' => $opening instanceof Deadline ? LocalPath::of($request->query('from')) : null,
        ]);
    }

    /**
     * The due date the page opens on arrival (`?deadline=`), skipped too; none when the day is not one of its due dates.
     *
     * @param  Deadlines  $deadlines  the due dates
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item of the page
     * @param  mixed  $date  the day the rules give, as the address carries it
     */
    private function opening(Deadlines $deadlines, PlannedExpense|PlannedIncome $planned, mixed $date): ?Deadline
    {
        // The address of a due date allows only this shape, and the search assumes it
        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        try {
            return $deadlines->findOnDay($planned, $date, withSkipped: true);
        } catch (NotFoundHttpException) {
            // An old or mistyped link still opens the planned item, only without a panel
            return null;
        }
    }

    /**
     * *Edit a planned expense*: the form with its values.
     *
     * @throws NotFoundHttpException if the kind is unknown or the planned item is not of the family
     */
    public function edit(Request $request, string $kind, string $id): Response
    {
        $planned = PlannedKind::find($kind, $id);
        Gate::authorize('update', $planned);

        return $this->form($request, $kind, $this->summaries->row($planned, CarbonImmutable::today('Europe/Rome')), PlannedPreset::of($planned));
    }

    /**
     * Saves the change and goes back to the page of the planned item.
     *
     * @throws NotFoundHttpException if the kind is unknown or the planned item is not of the family
     */
    public function update(SavePlannedRequest $request, SavePlanned $save): RedirectResponse
    {
        $planned = $save->handle($request->kind(), $request->columns(), $request->planned());

        return to_route('budget.planned.show', ['kind' => $request->kind(), 'id' => $planned->id]);
    }

    /**
     * Deletes the planned item (only an `admin`): its payments stay, without the link to it.
     *
     * @throws NotFoundHttpException if the kind is unknown or the planned item is not of the family
     */
    public function destroy(string $kind, string $id): RedirectResponse
    {
        $planned = PlannedKind::find($kind, $id);
        Gate::authorize('delete', $planned);

        $planned->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deleted.')]);

        return to_route('budget.planned.index');
    }

    /**
     * The form, new (`$planned` null) or changed.
     *
     * @param  Request  $request  the request, for `?rule_from=` (the transaction the rule is written from)
     * @param  string  $kind  `expense` or `income`
     * @param  PlannedRowData|null  $planned  the planned item to change, null for a new one
     * @param  array<string, mixed>  $values  the starting values of the fields
     */
    private function form(Request $request, string $kind, ?PlannedRowData $planned, array $values): Response
    {
        $bankOn = resolve(FamilyFeatures::class)->on(Feature::BankFeed);
        $ai = $bankOn && resolve(DocumentReader::class)->isActive();
        $example = $bankOn ? $this->ruleExample($request->query('rule_from')) : null;

        // Without the reading the AI cannot write the rule: the field starts from the words of the transaction
        if ($example instanceof BankTransactionData && ! $ai && blank($values['bank_match'] ?? null)) {
            $values['bank_match'] = BankMatch::fromText($example->text);
        }

        return Inertia::render('budget/EditPlanned', [
            'kind' => $kind,
            'planned' => $planned,
            'values' => $values,
            'options' => BudgetFormOptions::for($kind),
            // The field *How it shows in the bank* needs the link on; the AI button, the reading too
            'bank' => ['on' => $bankOn, 'ai' => $ai],
            'rule_example' => $example,
        ]);
    }

    /**
     * The transaction `?rule_from=` points to, for the sheet that writes the rule from it; null when it is missing,
     * not a number or not of the family (the form opens all the same).
     *
     * @param  mixed  $id  the value of the address
     */
    private function ruleExample(mixed $id): ?BankTransactionData
    {
        $id = BankTransactionId::parse($id);

        if ($id === null) {
            return null;
        }

        $transaction = BankTransaction::query()->with('financialAccount')->find($id);

        return $transaction instanceof BankTransaction ? BankTransactionData::fromTransaction($transaction) : null;
    }

    /**
     * The starting values the transaction of `?bank=` gives: the ones of its text that comes back every month, for the
     * kind its sign says. Nothing when the link to the bank is off or the transaction is missing, not of the family,
     * not to sort any more, the other direction or not recurring (the form opens empty all the same).
     *
     * @param  mixed  $id  the value of the address
     * @param  string  $kind  `expense` or `income`
     * @return array<string, mixed>
     */
    private function recurringValues(mixed $id, string $kind): array
    {
        $id = resolve(FamilyFeatures::class)->on(Feature::BankFeed) ? BankTransactionId::parse($id) : null;

        if ($id === null) {
            return [];
        }

        $transaction = BankTransaction::query()->unsorted()->find($id);

        if (! $transaction instanceof BankTransaction || $transaction->kind() !== $kind) {
            return [];
        }

        $chain = resolve(RecurringBankTexts::class)->chain($transaction);

        return $chain === null ? [] : PlannedPreset::fromBank($transaction, $chain);
    }
}
