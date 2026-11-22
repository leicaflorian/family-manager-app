<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\Document;
use App\Queries\FilterDocuments;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Marks a drawing of the gallery as a favourite, or removes the mark.
 */
final readonly class FavoriteDocument
{
    public function __construct(private FilterDocuments $filter) {}

    /**
     * Marks or unmarks the document.
     *
     * @param  Document  $document  the drawing
     * @param  bool  $favorite  true to mark it, false to remove the mark
     *
     * @throws ValidationException when marking a document that is not in the gallery
     */
    public function handle(Document $document, bool $favorite): void
    {
        Gate::authorize('update', $document);

        if ($favorite && ! $this->filter->apply(Document::query()->whereKey($document->id), ['gallery' => true])->exists()) {
            throw ValidationException::withMessages(['document' => __('Only a drawing of the gallery can be a favourite.')]);
        }

        $document->forceFill(['is_favorite' => $favorite])->save();
    }
}
