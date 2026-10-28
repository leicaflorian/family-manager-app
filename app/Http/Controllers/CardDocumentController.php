<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Cards\LinkCardDocuments;
use App\Cards\CardTypes;
use App\Http\Requests\Cards\LinkDocumentRequest;
use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Links a document to a card and unlinks it. Whoever may change the document
 * does it (a `member` too), as the permission is on the document.
 */
final class CardDocumentController extends Controller
{
    /**
     * Links the document to the card; linking it twice changes nothing.
     */
    public function store(LinkDocumentRequest $request, string $type, string $id, LinkCardDocuments $links): RedirectResponse
    {
        $description = CardTypes::findOrFail($type);
        $card = $description->findOrFail($id);
        $document = Document::query()->findOrFail($request->documentId());
        Gate::authorize('update', $document);

        $links->handle($card, new Collection([$document]), new Collection);

        return to_route('cards.show', ['type' => $type, 'id' => $id]);
    }

    /**
     * Unlinks the document from the card and goes back to the page it came
     * from (the card, or its form); the document stays.
     */
    public function destroy(string $type, string $id, Document $document, LinkCardDocuments $links): RedirectResponse
    {
        $card = CardTypes::findOrFail($type)->findOrFail($id);
        Gate::authorize('update', $document);

        $links->handle($card, new Collection, new Collection([$document]));

        // From the form of the card the person stays there, otherwise the card opens
        return back(fallback: route('cards.show', ['type' => $type, 'id' => $id]));
    }
}
