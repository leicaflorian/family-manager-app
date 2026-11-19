<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Actions\Budget\DeleteMovement;
use App\Actions\Budget\SaveMovement;
use App\Actions\Documents\LinkDocuments;
use App\Data\MovementData;
use App\Data\MovementFiltersData;
use App\Data\OptionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\MovementIndexRequest;
use App\Http\Requests\Budget\SaveMovementRequest;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Services\BudgetFormOptions;
use App\Services\FileStore;
use App\Services\LinkableDocuments;
use App\Services\Movements;
use App\Support\ImportPage;
use App\Support\LocalPath;
use App\Support\PlannedKind;
use App\Support\PresetLink;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *Movements*: the expenses and incomes of a month, and the page to add, change and
 * delete one. Everyone of the family sees them, adds them and changes them; only an `admin` deletes them.
 */
final class MovementController extends Controller
{
    public function __construct(private readonly Movements $movements, private readonly LinkableDocuments $linkable) {}

    /**
     * *Movements*: the month with its figures and list, narrowed by kind and account. Every row opens the page of its
     * movement.
     *
     * @throws NotFoundHttpException if the month, the kind or the account of the address is wrong
     */
    public function index(MovementIndexRequest $request, #[CurrentUser] User $user): Response
    {
        $month = $request->month();
        $filters = $request->filters();

        return Inertia::render('budget/Movements', [
            ...$this->movements->month($month, $request->kind(), $request->account(), $user),
            'kind' => $request->kind(),
            'account' => $request->account(),
            'filters' => MovementFiltersData::fromFilters($filters),
            'search' => $filters->isSearch() ? $this->movements->search($filters, $user) : null,
            // For the account filter
            'accounts' => BudgetFormOptions::accounts(),
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
            'back' => LocalPath::of($request->query('from')),
            ...$this->linkable->props($request, []),
            'canDeleteDocuments' => $user->can('deleteAny', Document::class),
        ]);
    }

    /**
     * Adds the movement with the documents chosen in the form. With files, they wait for their document and *Import*
     * opens with the movement as the link of each; otherwise it goes back to the card of *Add expense* (`back_to_card`),
     * to the page the form came from (`from`) or to the month of the movement.
     */
    public function store(SaveMovementRequest $request, SaveMovement $save, LinkDocuments $links, FileStore $store): RedirectResponse
    {
        // Looked up first: a document of another family answers 404 before anything is saved
        $link = $request->documentsToLink();

        $movement = DB::transaction(function () use ($request, $save, $links, $link): Expense|Income {
            $movement = $save->handle($request->kind(), $request->columns());
            $links->handle($movement, $link, new Collection);

            return $movement;
        });

        return $this->saved($movement, $request, $store);
    }

    /**
     * A movement on its own page, with a page of the documents of the family not yet linked to it (*Link a document*)
     * and the way back in `?from=`.
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
            'back' => LocalPath::of($request->query('from')),
            ...$this->linkable->props($request, ['not_record' => PresetLink::movement($movement)]),
            'canDeleteDocuments' => $user->can('deleteAny', Document::class),
        ]);
    }

    /**
     * Saves the change, links and unlinks the documents chosen in the form and goes on as an added movement does.
     *
     * @throws NotFoundHttpException if the kind is unknown or the movement is not of the family
     */
    public function update(SaveMovementRequest $request, SaveMovement $save, LinkDocuments $links, FileStore $store): RedirectResponse
    {
        $movement = $request->movement();
        // Looked up first: a document of another family answers 404 before anything is saved
        $link = $request->documentsToLink();
        $unlink = $request->documentsToUnlink();

        $saved = DB::transaction(function () use ($request, $save, $links, $link, $unlink, $movement): Expense|Income {
            $saved = $save->handle($request->kind(), $request->columns(), $movement);
            $links->handle($saved, $link, $unlink);

            return $saved;
        });

        return $this->saved($saved, $request, $store);
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
     * What comes after a save: *Import* with the files sent (each linked to the movement), the card of *Add expense*
     * (`back_to_card`), the page the form came from (`from`), or the month of the movement.
     *
     * @param  Expense|Income  $movement  the movement just saved
     * @param  SaveMovementRequest  $request  the request, with the files, `back_to_card` and `from`
     * @param  FileStore  $store  where the files are kept
     */
    private function saved(Expense|Income $movement, SaveMovementRequest $request, FileStore $store): RedirectResponse
    {
        $ids = [];

        foreach ($request->uploadedFiles() as $file) {
            $ids[] = $store->store($file, toSort: true, link: PresetLink::movement($movement))->id;
        }

        if ($ids !== []) {
            return ImportPage::open($ids, ($movement instanceof Expense ? 'expense' : 'income').":{$movement->id}");
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        if ($request->boolean('back_to_card') && $movement instanceof Expense && $movement->card_type !== null && $movement->card_id !== null) {
            return to_route('cards.show', ['type' => $movement->card_type, 'id' => $movement->card_id]);
        }

        $back = $request->back();

        return $back === null
            ? to_route('budget.movements.index', ['month' => $movement->date?->format('Y-m')])
            : redirect($back);
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
