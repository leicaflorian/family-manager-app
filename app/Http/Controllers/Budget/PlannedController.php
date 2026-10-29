<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Actions\Budget\SavePlanned;
use App\Data\PlannedRowData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\SavePlannedRequest;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Services\BudgetFormOptions;
use App\Services\PlannedSummaries;
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
     * *Planned expenses*: the active expenses and incomes by next due date, the monthly averages and the ended ones.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('budget/Planned', [
            ...$this->summaries->index(CarbonImmutable::today('Europe/Rome')),
            'canEdit' => $user->can('create', PlannedExpense::class),
        ]);
    }

    /**
     * *New planned expense*: the empty form, or filled in from the card of `?card=type:id` (for an income, its employment).
     *
     * @throws NotFoundHttpException if the kind is not `expense` or `income`
     */
    public function create(Request $request, string $kind): Response
    {
        Gate::authorize('create', PlannedKind::modelClass($kind));

        // The card of an income is its employment
        $preset = $kind === 'expense' ? PlannedPreset::fromQuery($request->query('card')) : PlannedPreset::incomeFromQuery($request->query('card'));

        return $this->form($kind, null, [...PlannedPreset::defaults($kind), ...$preset]);
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
    public function show(string $kind, string $id, #[CurrentUser] User $user): Response
    {
        return Inertia::render('budget/PlannedItem', [
            'kind' => $kind,
            'detail' => $this->summaries->detail(PlannedKind::find($kind, $id), $user, CarbonImmutable::today('Europe/Rome')),
            'accounts' => BudgetFormOptions::accounts(),
        ]);
    }

    /**
     * *Edit a planned expense*: the form with its values.
     *
     * @throws NotFoundHttpException if the kind is unknown or the planned item is not of the family
     */
    public function edit(string $kind, string $id): Response
    {
        $planned = PlannedKind::find($kind, $id);
        Gate::authorize('update', $planned);

        return $this->form($kind, $this->summaries->row($planned, CarbonImmutable::today('Europe/Rome')), PlannedPreset::of($planned));
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

        return to_route('budget.planned.index');
    }

    /**
     * The form, new (`$planned` null) or changed.
     *
     * @param  string  $kind  `expense` or `income`
     * @param  PlannedRowData|null  $planned  the planned item to change, null for a new one
     * @param  array<string, mixed>  $values  the starting values of the fields
     */
    private function form(string $kind, ?PlannedRowData $planned, array $values): Response
    {
        return Inertia::render('budget/EditPlanned', [
            'kind' => $kind,
            'planned' => $planned,
            'values' => $values,
            'options' => BudgetFormOptions::for($kind),
        ]);
    }
}
