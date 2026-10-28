<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Cards\CardTypes;
use App\Http\Requests\Cards\LinkDocumentRequest;
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Links a document to a card and unlinks it. Whoever may change the document
 * does it (a `member` too), as the permission is on the document.
 */
final class CardDocumentController extends Controller
{
    /**
     * Links the document to the card; linking it twice changes nothing.
     */
    public function store(LinkDocumentRequest $request, string $type, string $id): RedirectResponse
    {
        $description = CardTypes::findOrFail($type);
        $card = $description->findOrFail($id);
        $document = Document::query()->findOrFail($request->documentId());
        Gate::authorize('update', $document);

        $this->documents($card)->syncWithoutDetaching([$document->getKey()]);

        return to_route('cards.show', ['type' => $type, 'id' => $id]);
    }

    /**
     * Unlinks the document from the card and goes back to the page it came
     * from (the card, or its form); the document stays.
     */
    public function destroy(string $type, string $id, Document $document): RedirectResponse
    {
        $card = CardTypes::findOrFail($type)->findOrFail($id);
        Gate::authorize('update', $document);

        $this->documents($card)->detach($document->getKey());

        // From the form of the card the person stays there, otherwise the card opens
        return back(fallback: route('cards.show', ['type' => $type, 'id' => $id]));
    }

    /**
     * The documents relation of a card.
     *
     * @return MorphToMany<Document, Model>
     *
     * @throws RuntimeException if the card has no documents
     */
    private function documents(Model $card): MorphToMany
    {
        $relation = method_exists($card, 'documents') ? $card->documents() : null;

        return $relation instanceof MorphToMany ? $relation : throw new RuntimeException($card::class.' has no documents.');
    }
}
