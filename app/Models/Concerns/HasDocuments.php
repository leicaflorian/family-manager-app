<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * For the records that have linked documents (the 15 junction tables of
 * Directus): the documents go through `documentables`, and each record has the
 * name it is chosen by among a document's links.
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

    /**
     * The name of the record among a document's links, for example
     * «Laura - Comune di Bologna» for an employment.
     */
    abstract public function label(): string;

    /**
     * The relations `label()` reads, to load them together for a list.
     *
     * @return list<string>
     */
    public static function labelRelations(): array
    {
        return [];
    }

    /**
     * The parts of a name that are there, joined by a dash.
     *
     * @param  string|null  ...$parts  the parts, in order; the empty ones are left out
     */
    protected static function joinLabel(?string ...$parts): string
    {
        return implode(' - ', array_filter($parts, static fn (?string $part): bool => $part !== null && $part !== ''));
    }
}
