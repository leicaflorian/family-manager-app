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
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Services\BudgetFormOptions;
use App\Services\Movements;
use App\Support\PlannedKind;
use App\Support\PresetLink;
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
            // As on the page of a movement, the due dates are looked for around the movement's own day
            'occurrences' => fn (): array => $this->occurrences($request, $selected instanceof Income ? 'income' : 'expense', $selected->date ?? CarbonImmutable::today('Europe/Rome'), $selected),
            'options' => BudgetFormOptions::forMovement(),
        ]);
    }

    /**
     * *New expense or income*: the empty form, the one of `?kind=&planned=` for a planned item, or the expense of the
     * card of `?card=type:id` (*Add expense* of a card), with the category of its type. A card of another family is
     * ignored.
     *
     * @throws NotFoundHttpException if the kind is not `expense` or `income` or the planned item is not of the family
     */
    public function create(Request $request, #[CurrentUser] User $user): Response
    {
        $kind = match ($request->query('kind', 'expense')) {
            'expense' => 'expense',
            'income' => 'income',
            default => throw new NotFoundHttpException,
        };
        Gate::authorize('create', Movements::modelClass($kind));

        $planned = is_scalar($request->query('planned')) ? PlannedKind::find($kind, (string) $request->query('planned')) : null;
        $card = $kind === 'expense' ? PresetLink::from($request->query('card')) : null;
        $category = $card === null ? null : ExpenseCategory::firstOfCardType($card['type']);
        $today = CarbonImmutable::today('Europe/Rome');

        return Inertia::render('budget/Movement', [
            'movement' => null,
            'start' => [
                'kind' => $kind,
                'planned_id' => $planned === null ? null : (string) $planned->id,
                'date' => $today->toDateString(),
                'card' => $card === null ? null : PresetLink::key($card['type'], $card['id']),
                'category_id' => $category === null ? null : (string) $category,
            ],
            'occurrences' => fn (): array => $this->occurrences($request, $kind, $today, null, $planned),
            'options' => BudgetFormOptions::forMovement(),
        ]);
    }

    /**
     * Adds the movement and opens the month it belongs to, or the card it is linked to when it comes from *Add expense*
     * of the card (`back_to_card`).
     */
    public function store(SaveMovementRequest $request, SaveMovement $save): RedirectResponse
    {
        $movement = $save->handle($request->kind(), $request->columns());

        if ($request->boolean('back_to_card') && $movement instanceof Expense && $movement->card_type !== null && $movement->card_id !== null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

            return to_route('cards.show', ['type' => $movement->card_type, 'id' => $movement->card_id]);
        }

        return $this->saved($movement);
    }

    /**
     * A movement on its own page, for the phone.
     *
     * @throws NotFoundHttpException if the kind is unknown or the movement is not of the family
     */
    public function show(Request $request, string $kind, string $id, #[CurrentUser] User $user): Response
    {
        $movement = Movements::find($kind, $id);
        $data = MovementData::fromMovement($movement, $user);

        return Inertia::render('budget/Movement', [
            'movement' => $data,
            'start' => ['kind' => $data->kind, 'planned_id' => $data->planned_id === null ? null : (string) $data->planned_id, 'date' => $data->date, 'card' => null, 'category_id' => null],
            'occurrences' => fn (): array => $this->occurrences($request, $data->kind, CarbonImmutable::parse($data->date, 'Europe/Rome'), $movement),
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
     * The due dates a movement of a day can settle. The page asks for another planned item by a partial reload with
     * `?planned_kind=&planned=&planned_date=`; otherwise they are those of the planned item the movement already has
     * (or the one the new form starts from).
     *
     * @param  Request  $request  the request, which may name the planned item
     * @param  string  $kind  `expense` or `income`, the kind of the movement
     * @param  CarbonImmutable  $date  the day of the movement
     * @param  Expense|Income|null  $movement  the movement being changed
     * @param  PlannedExpense|PlannedIncome|null  $default  the planned item of a new form
     * @return list<OptionData>
     *
     * @throws NotFoundHttpException if the planned item named is not of the family
     */
    private function occurrences(Request $request, string $kind, CarbonImmutable $date, Expense|Income|null $movement = null, PlannedExpense|PlannedIncome|null $default = null): array
    {
        $asked = $request->query('planned');

        if (is_string($asked) && ctype_digit($asked)) {
            $plannedKind = $request->query('planned_kind') === 'income' ? 'income' : $kind;
            $day = $request->query('planned_date');
            $parsed = is_string($day) ? CarbonImmutable::createFromFormat('!Y-m-d', $day, 'Europe/Rome') : false;

            return $this->movements->openOccurrences(PlannedKind::find($plannedKind, $asked), $parsed instanceof CarbonImmutable ? $parsed : $date, $movement);
        }

        $planned = $default ?? ($movement instanceof Expense ? $movement->plannedExpense : $movement?->plannedIncome);

        return $planned instanceof PlannedExpense || $planned instanceof PlannedIncome
            ? $this->movements->openOccurrences($planned, $date, $movement)
            : [];
    }
}
