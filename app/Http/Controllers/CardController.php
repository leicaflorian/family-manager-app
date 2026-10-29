<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Cards\DeleteCard;
use App\Actions\Cards\LinkCardDocuments;
use App\Actions\Cards\SaveCard;
use App\Actions\Loans\RefreshLoan;
use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Data\LoanFiguresData;
use App\Enums\Area;
use App\Http\Requests\Cards\SaveCardRequest;
use App\Models\Document;
use App\Models\Loan;
use App\Models\User;
use App\Queries\DocumentRows;
use App\Services\CardBudgets;
use App\Services\CardDetails;
use App\Services\CardSummaries;
use App\Services\FileStore;
use App\Support\LoanTerms;
use App\Support\PresetLink;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\MergeProp;
use Inertia\OptionalProp;
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
        private readonly CardBudgets $budgets,
        private readonly RefreshLoan $loans,
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

            return Inertia::render('cards/EditCard', [
                ...$this->details->form($card, null, $request->query()),
                ...$this->loanFigures($request, $card, null),
                ...$this->linkable($request, []),
            ]);
        }

        Gate::authorize('create', CardTypes::all()[0]->model());
        $area = $request->query('area');
        $types = is_string($area) ? CardTypes::of(Area::tryFrom($area) ?? abort(404)) : CardTypes::all();

        return Inertia::render('cards/NewCard', ['types' => AreaController::typeData($types)]);
    }

    /**
     * Adds the card with the documents chosen in the form and opens it. With
     * files, they wait for their document and *Upload* opens with the card
     * among the links of each.
     */
    public function store(SaveCardRequest $request, SaveCard $save, LinkCardDocuments $links, FileStore $store): RedirectResponse
    {
        $type = $request->cardType();
        // Looked up first: a document of another family answers 404 before anything is saved
        $link = $request->documentsToLink();
        $card = $save->handle($type, $request->columns());
        $links->handle($card, $link, new Collection);

        return $this->openWithFiles($type, $card, $request, $store);
    }

    /**
     * *A card*: its detail and, for *Link a document*, a page of the documents
     * of the family not yet linked to it, found with `link_search`.
     */
    public function show(Request $request, string $type, string $id, #[CurrentUser] User $user): Response
    {
        $card = CardTypes::findOrFail($type)->findOrFail($id);
        $detail = $this->details->of($card, $user);

        return Inertia::render('cards/Card', [
            'card' => $detail,
            ...$this->linkable($request, ['not_record' => ['type' => $detail->card->type, 'id' => $detail->card->id]]),
        ]);
    }

    /**
     * *Edit card*: the form with the data of the card, its documents, whether
     * they can be deleted, the documents that can be linked and, for the column
     * beside the form on the computer, its payments.
     */
    public function edit(Request $request, string $type, string $id, #[CurrentUser] User $user): Response
    {
        $description = CardTypes::findOrFail($type);
        $card = $description->findOrFail($id);
        Gate::authorize('update', $card);

        $row = $this->summaries->row($card);

        return Inertia::render('cards/EditCard', [
            ...$this->details->form($description, $card, []),
            ...$this->loanFigures($request, $description, $card),
            'card' => $row,
            'documents' => $this->documents->of(['record' => ['type' => $description->type(), 'id' => $row->id]]),
            'canDeleteDocuments' => $user->can('deleteAny', Document::class),
            'budget' => $this->budgets->of($card, $user, CarbonImmutable::today('Europe/Rome')),
            ...$this->linkable($request, ['not_record' => ['type' => $description->type(), 'id' => $row->id]]),
        ]);
    }

    /**
     * Saves the change, links and unlinks the documents chosen in the form and
     * opens the card. With files, they wait for their document and *Upload*
     * opens with the card among the links of each.
     */
    public function update(SaveCardRequest $request, SaveCard $save, LinkCardDocuments $links, FileStore $store): RedirectResponse
    {
        $type = $request->cardType();
        $card = $request->card() ?? abort(404);
        // Looked up first: a document of another family answers 404 before anything is saved
        $link = $request->documentsToLink();
        $unlink = $request->documentsToUnlink();
        $save->handle($type, $request->columns(), $card);
        $links->handle($card, $link, $unlink);

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
     * For the form of a loan: the total to pay and the cost of the values written so far (`figures`), worked out as
     * saving them would, with the saved exceptions of the loan's planned expenses. Only asked for by the form while
     * the person types; partial or wrong values give null figures.
     *
     * @param  Request  $request  the request with the values of the form in `figures`
     * @param  CardType  $type  the type of the card
     * @param  Model|null  $card  the loan to change, or null for a new one
     * @return array{loanFigures?: OptionalProp|Closure}
     */
    private function loanFigures(Request $request, CardType $type, ?Model $card): array
    {
        if ($type->model() !== Loan::class) {
            return [];
        }

        if (! $card instanceof Loan) {
            return ['loanFigures' => Inertia::optional(fn (): LoanFiguresData => $this->previewLoan($request, null))];
        }

        // Plain value, so the page opens with the saved figures; a reload asking for them gets the live ones
        return ['loanFigures' => fn (): LoanFiguresData => $this->isAskedFor($request)
            ? $this->previewLoan($request, $card)
            : new LoanFiguresData($this->amount($card->total_to_pay), $this->amount($card->cost))];
    }

    /**
     * The live figures of the values written in the form of a loan. For a saved loan, what the form does not
     * write (the status, the last date) is the saved one, as saving leaves it.
     *
     * @param  Request  $request  the request with the values in `figures`
     * @param  Loan|null  $saved  the loan being changed, or null for a new one
     */
    private function previewLoan(Request $request, ?Loan $saved): LoanFiguresData
    {
        $kept = $saved instanceof Loan ? ['status' => $saved->status, 'end_date' => $saved->end_date] : [];
        $figures = $this->loans->preview($saved, LoanTerms::fromForm([...$kept, ...$request->array('figures')]), CarbonImmutable::today('Europe/Rome'));

        return new LoanFiguresData($figures->totalToPay, $figures->cost);
    }

    /**
     * Whether the request is a partial reload that asks for the loan figures.
     *
     * @param  Request  $request  the request
     */
    private function isAskedFor(Request $request): bool
    {
        return in_array('loanFigures', explode(',', (string) $request->header('X-Inertia-Partial-Data')), true);
    }

    /**
     * A saved amount as a number.
     *
     * @param  mixed  $value  the column of the model
     */
    private function amount(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * For *Link a document*: a page of the documents of the family that match
     * `link_search` (the page in `link_page`), the next pages added below
     * those already shown, and whether there is any document to link.
     *
     * @param  Request  $request  the request with the search and the page
     * @param  array<string, mixed>  $filters  the filters that leave out the documents already linked
     * @return array{linkable: MergeProp, linkableNext: ?int, linkableSearch: string, hasLinkable: bool}
     */
    private function linkable(Request $request, array $filters): array
    {
        $search = $request->string('link_search')->trim()->value();
        $page = $this->documents->page([...$filters, 'search' => $search], max(1, $request->integer('link_page', 1)), self::LINKABLE_PER_PAGE);

        return [
            'linkable' => Inertia::merge($page['documents']),
            'linkableNext' => $page['next'],
            'linkableSearch' => $search,
            'hasLinkable' => $this->documents->exist($filters),
        ];
    }

    /**
     * Keeps the files sent with the card waiting for their document, each linked
     * to the card, and opens *Upload*; without files, opens the card.
     *
     * @param  CardType  $type  the type of the card
     * @param  Model  $card  the card just saved
     * @param  SaveCardRequest  $request  the request with the files
     * @param  FileStore  $store  where the files are kept
     */
    private function openWithFiles(CardType $type, Model $card, SaveCardRequest $request, FileStore $store): RedirectResponse
    {
        $files = $request->uploadedFiles();

        $link = PresetLink::card($type, $card);

        foreach ($files as $file) {
            $store->store($file, toSort: true, link: $link);
        }

        return $files === []
            ? to_route('cards.show', ['type' => $type->type(), 'id' => $card->getKey()])
            : to_route('upload', PresetLink::of($type, $card));
    }
}
