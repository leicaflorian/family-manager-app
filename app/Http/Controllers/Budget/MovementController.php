<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Actions\Budget\DeleteMovement;
use App\Actions\Budget\SaveMovement;
use App\Data\MovementData;
use App\Data\OptionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\MovementIndexRequest;
use App\Http\Requests\Budget\SaveMovementRequest;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Services\BudgetFormOptions;
use App\Services\Movements;
use App\Support\PlannedKind;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *Movements*: the expenses and incomes of a month, and the panel (or the page, on the phone) to add, change and
 * delete one. Everyone of the family sees them, adds them and changes them; only an `admin` deletes them.
 */
final class MovementController extends Controller
{
    public function __construct(private readonly Movements $movements) {}

    /**
     * *Movements*: the month with its figures and list, narrowed by kind and account, and on the computer the
     * movement of `?movement=kind:id` open on the right.
     *
     * @throws NotFoundHttpException if the month, the kind, the account or the movement of the address is wrong
     */
    public function index(MovementIndexRequest $request, #[CurrentUser] User $user): Response
    {
        $month = $request->month();
        $selected = $request->movement();

        return Inertia::render('budget/Movements', [
            ...$this->movements->month($month, $request->kind(), $request->account(), $user),
            'kind' => $request->kind(),
            'account' => $request->account(),
            'selected' => $selected instanceof Expense || $selected instanceof Income ? MovementData::fromMovement($selected, $user) : null,
            'occurrences' => $selected instanceof Expense || $selected instanceof Income ? $this->occurrences($selected) : [],
            'options' => BudgetFormOptions::forMovement(),
        ]);
    }

    /**
     * *New expense or income*: the empty form, or the one of `?kind=&planned=` for a planned item.
     *
     * @throws NotFoundHttpException if the kind is not `expense` or `income` or the planned item is not of the family
     */
    public function create(Request $request, #[CurrentUser] User $user): Response
    {
        $kind = $request->query('kind') === 'income' ? 'income' : 'expense';
        Gate::authorize('create', Movements::modelClass($kind));

        $planned = is_scalar($request->query('planned')) ? PlannedKind::find($kind, (string) $request->query('planned')) : null;
        $today = CarbonImmutable::today('Europe/Rome');

        return Inertia::render('budget/Movement', [
            'movement' => null,
            'start' => ['kind' => $kind, 'planned_id' => $planned === null ? null : (string) $planned->id, 'date' => $today->toDateString()],
            'occurrences' => $planned === null ? [] : $this->movements->openOccurrences($planned, $today),
            'options' => BudgetFormOptions::forMovement(),
        ]);
    }

    /**
     * Adds the movement and opens the month it belongs to.
     */
    public function store(SaveMovementRequest $request, SaveMovement $save): RedirectResponse
    {
        return $this->saved($save->handle($request->kind(), $request->columns()));
    }

    /**
     * A movement on its own page, for the phone.
     *
     * @throws NotFoundHttpException if the kind is unknown or the movement is not of the family
     */
    public function show(string $kind, string $id, #[CurrentUser] User $user): Response
    {
        $movement = Movements::find($kind, $id);
        $data = MovementData::fromMovement($movement, $user);

        return Inertia::render('budget/Movement', [
            'movement' => $data,
            'start' => ['kind' => $data->kind, 'planned_id' => $data->planned_id === null ? null : (string) $data->planned_id, 'date' => $data->date],
            'occurrences' => $this->occurrences($movement),
            'options' => BudgetFormOptions::forMovement(),
        ]);
    }

    /**
     * Saves the change and opens the month of the movement.
     *
     * @throws NotFoundHttpException if the kind is unknown or the movement is not of the family
     */
    public function update(SaveMovementRequest $request, SaveMovement $save): RedirectResponse
    {
        $movement = $request->movement();

        return $this->saved($save->handle($request->kind(), $request->columns(), $movement));
    }

    /**
     * Deletes the movement (only an `admin`); its documents stay.
     *
     * @throws NotFoundHttpException if the kind is unknown or the movement is not of the family
     */
    public function destroy(string $kind, string $id, DeleteMovement $delete): RedirectResponse
    {
        $movement = Movements::find($kind, $id);
        Gate::authorize('delete', $movement);

        $delete->handle($movement);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deleted.')]);

        return to_route('budget.movements.index', ['month' => $movement->date?->format('Y-m')]);
    }

    /**
     * Back to the list, on the month of the movement and with the movement open.
     *
     * @param  Expense|Income  $movement  the movement just saved
     */
    private function saved(Expense|Income $movement): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return to_route('budget.movements.index', [
            'month' => $movement->date?->format('Y-m'),
            'movement' => ($movement instanceof Expense ? 'expense' : 'income').":{$movement->id}",
        ]);
    }

    /**
     * The due dates that the movement can settle, if it belongs to a planned item.
     *
     * @param  Expense|Income  $movement  the movement
     * @return list<OptionData>
     */
    private function occurrences(Expense|Income $movement): array
    {
        $planned = $movement instanceof Expense ? $movement->plannedExpense : $movement->plannedIncome;

        return $planned instanceof PlannedExpense || $planned instanceof PlannedIncome
            ? $this->movements->openOccurrences($planned, CarbonImmutable::parse($movement->date, 'Europe/Rome'), $movement)
            : [];
    }
}
