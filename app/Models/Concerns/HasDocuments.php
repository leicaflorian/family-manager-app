<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * For the records that have linked documents (the 15 junction tables of
 * Directus): the documents go through `documentables`.
 *
 * @property-read Collection<int, Document> $documents
 */
trait HasDocuments
{
    /**
     * The documents linked to the record.
     *
     * @return MorphToMany<Document, $this>
     */
    public function documents(): MorphToMany
    {
        return $this->morphToMany(Document::class, 'documentable');
    }
}
