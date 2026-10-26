<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Per le schede che hanno documenti collegati (le 15 tabelle ponte di
 * Directus): i documenti passano da `documentables`.
 *
 * @property-read Collection<int, Document> $documents
 */
trait HaDocumenti
{
    /**
     * I documenti collegati alla scheda.
     *
     * @return MorphToMany<Document, $this>
     */
    public function documents(): MorphToMany
    {
        return $this->morphToMany(Document::class, 'documentable');
    }
}
