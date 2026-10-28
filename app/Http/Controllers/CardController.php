<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Cards\DeleteCard;
use App\Actions\Cards\SaveCard;
use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Enums\Area;
use App\Http\Requests\Cards\SaveCardRequest;
use App\Models\Document;
use App\Models\User;
use App\Queries\DocumentRows;
use App\Services\CardDetails;
use App\Services\CardSummaries;
use App\Services\FileStore;
use App\Support\PresetLink;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Model;
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
    /** How many documents a page of *Link a document* holds. */
    private const int LINKABLE_PER_PAGE = 15;

    public function __construct(
        private readonly CardDetails $details,
        private readonly CardSummaries $summaries,
        private readonly DocumentRows $documents,
    ) {}

    /**
     * *New card*: the choice of the type (only the ones of `?area=` if given),
     * or the empty form of the type in `?card=`, with the values of the query as presets.
     */
    public function create(Request $request): Response
    {
        $type = $request->query('card');

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
     * Adds the card and opens it. With files, they wait for their document and
     * *Upload* opens with the card among the links of each.
     */
    public function store(SaveCardRequest $request, SaveCard $save, FileStore $store): RedirectResponse
    {
        $type = $request->cardType();

        return $this->openWithFiles($type, $save->handle($type, $request->columns()), $request, $store);
    }

    /**
     * *A card*: its detail and, for *Link a document*, a page of the documents
     * of the family not yet linked to it, found with `link_search`.
     */
    public function show(Request $request, string $type, string $id, #[CurrentUser] User $user): Response
    {
        $card = CardTypes::findOrFail($type)->findOrFail($id);
        $detail = $this->details->of($card, $user);
        $notLinked = ['not_record' => ['type' => $detail->card->type, 'id' => $detail->card->id]];
        $search = $request->string('link_search')->trim()->value();
        $page = $this->documents->page([...$notLinked, 'search' => $search], max(1, $request->integer('link_page', 1)), self::LINKABLE_PER_PAGE);

        return Inertia::render('cards/Card', [
            'card' => $detail,
            // The next pages are added below those already shown.
            'linkable' => Inertia::merge($page['documents']),
            'linkableNext' => $page['next'],
            'linkableSearch' => $search,
            'hasLinkable' => $this->documents->exist($notLinked),
        ]);
    }

    /**
     * *Edit card*: the form with the data of the card, its documents and
     * whether they can be deleted.
     */
    public function edit(string $type, string $id, #[CurrentUser] User $user): Response
    {
        $description = CardTypes::findOrFail($type);
        $card = $description->findOrFail($id);
        Gate::authorize('update', $card);

        $row = $this->summaries->row($card);

        return Inertia::render('cards/EditCard', [
            ...$this->details->form($description, $card, []),
            'card' => $row,
            'documents' => $this->documents->of(['record' => ['type' => $description->type(), 'id' => $row->id]]),
            'canDeleteDocuments' => $user->can('deleteAny', Document::class),
        ]);
    }

    /**
     * Saves the change and opens the card. With files, they wait for their
     * document and *Upload* opens with the card among the links of each.
     */
    public function update(SaveCardRequest $request, SaveCard $save, FileStore $store): RedirectResponse
    {
        $type = $request->cardType();
        $card = $request->card() ?? abort(404);
        $save->handle($type, $request->columns(), $card);

        return $this->openWithFiles($type, $card, $request, $store);
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

    /**
     * Keeps the files sent with the card waiting for their document and opens
     * *Upload* linked to the card; without files, opens the card.
     *
     * @param  CardType  $type  the type of the card
     * @param  Model  $card  the card just saved
     * @param  SaveCardRequest  $request  the request with the files
     * @param  FileStore  $store  where the files are kept
     */
    private function openWithFiles(CardType $type, Model $card, SaveCardRequest $request, FileStore $store): RedirectResponse
    {
        $files = $request->uploadedFiles();

        foreach ($files as $file) {
            $store->store($file, toSort: true);
        }

        return $files === []
            ? to_route('cards.show', ['type' => $type->type(), 'id' => $card->getKey()])
            : to_route('upload', PresetLink::of($type, $card));
    }
}
