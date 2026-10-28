<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Cards\DeleteCard;
use App\Actions\Cards\SaveCard;
use App\Cards\CardTypes;
use App\Data\DocumentData;
use App\Enums\Area;
use App\Http\Requests\Cards\SaveCardRequest;
use App\Models\User;
use App\Queries\DocumentRows;
use App\Services\CardDetails;
use App\Services\CardSummaries;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *New card*, *A card* and its form: everyone sees the cards, only an `admin`
 * adds, changes and deletes them. The card type comes from the address.
 */
final class CardController extends Controller
{
    public function __construct(
        private readonly CardDetails $details,
        private readonly CardSummaries $summaries,
        private readonly DocumentRows $documents,
    ) {}

    /**
     * *New card*: the choice of the type (only the ones of `?area=` if given),
     * or the empty form of the type in `?type=`, with the values of the query as presets.
     */
    public function create(Request $request): Response
    {
        $type = $request->query('type');

        if (is_string($type)) {
            $card = CardTypes::findOrFail($type);
            Gate::authorize('create', $card->model());

            return Inertia::render('cards/EditCard', $this->details->form($card, null, $request->query()));
        }

        Gate::authorize('create', CardTypes::all()[0]->model());
        $area = $request->query('area');
        $types = is_string($area) ? CardTypes::of(Area::tryFrom($area) ?? abort(404)) : CardTypes::all();

        return Inertia::render('cards/NewCard', ['types' => AreaController::typeData($types)]);
    }

    /**
     * Adds the card and opens it.
     */
    public function store(SaveCardRequest $request, SaveCard $save): RedirectResponse
    {
        $type = $request->cardType();
        $card = $save->handle($type, $request->columns());

        return to_route('cards.show', ['type' => $type->type(), 'id' => $card->getKey()]);
    }

    /**
     * *A card*: its detail, and the documents of the family that can still be linked to it.
     */
    public function show(string $type, string $id, #[CurrentUser] User $user): Response
    {
        $card = CardTypes::findOrFail($type)->findOrFail($id);
        $detail = $this->details->of($card, $user);
        $linked = array_map(static fn (DocumentData $document): int => $document->id, $detail->documents);

        return Inertia::render('cards/Card', [
            'card' => $detail,
            'linkable' => array_values(array_filter(
                $this->documents->of([]),
                static fn (DocumentData $document): bool => ! in_array($document->id, $linked, true),
            )),
        ]);
    }

    /**
     * *Edit card*: the form with the data of the card.
     */
    public function edit(string $type, string $id): Response
    {
        $description = CardTypes::findOrFail($type);
        $card = $description->findOrFail($id);
        Gate::authorize('update', $card);

        return Inertia::render('cards/EditCard', [
            ...$this->details->form($description, $card, []),
            'card' => $this->summaries->row($card),
        ]);
    }

    /**
     * Saves the change and opens the card.
     */
    public function update(SaveCardRequest $request, SaveCard $save): RedirectResponse
    {
        $type = $request->cardType();
        $card = $request->card() ?? abort(404);
        $save->handle($type, $request->columns(), $card);

        return to_route('cards.show', ['type' => $type->type(), 'id' => $card->getKey()]);
    }

    /**
     * Deletes the card (only an `admin`) and goes back to its area; the documents stay.
     */
    public function destroy(string $type, string $id, DeleteCard $delete): RedirectResponse
    {
        $description = CardTypes::findOrFail($type);
        $card = $description->findOrFail($id);
        Gate::authorize('delete', $card);

        $delete->handle($card);

        return to_route('areas.show', $description->area());
    }
}
