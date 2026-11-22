<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\FavoriteDocument;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;

/**
 * Marks a drawing of the gallery as a favourite and removes the mark.
 */
final class DocumentFavoriteController extends Controller
{
    /**
     * Marks the drawing as a favourite.
     *
     * @param  Document  $document  the drawing
     * @param  FavoriteDocument  $favorite  the action
     * @return RedirectResponse back to the page
     */
    public function store(Document $document, FavoriteDocument $favorite): RedirectResponse
    {
        $favorite->handle($document, true);

        return back();
    }

    /**
     * Removes the mark.
     *
     * @param  Document  $document  the drawing
     * @param  FavoriteDocument  $favorite  the action
     * @return RedirectResponse back to the page
     */
    public function destroy(Document $document, FavoriteDocument $favorite): RedirectResponse
    {
        $favorite->handle($document, false);

        return back();
    }
}
