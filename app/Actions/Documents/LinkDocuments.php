<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use RuntimeException;

/**
 * Links documents to a record (a card, an expense, an income) and unlinks them; the documents stay. Linking a
 * document twice changes nothing.
 */
final class LinkDocuments
{
    /**
     * Links and unlinks the documents of the record.
     *
     * @param  Model  $record  the record, a model with `documents()`
     * @param  Collection<int, Document>  $link  the documents to link
     * @param  Collection<int, Document>  $unlink  the documents to unlink
     *
     * @throws RuntimeException if the record has no documents
     */
    public function handle(Model $record, Collection $link, Collection $unlink): void
    {
        $documents = $this->documents($record);

        if ($link->isNotEmpty()) {
            $documents->syncWithoutDetaching($link->modelKeys());
        }

        if ($unlink->isNotEmpty()) {
            $documents->detach($unlink->modelKeys());
        }
    }

    /**
     * The documents relation of a record.
     *
     * @param  Model  $record  the record
     * @return MorphToMany<Document, Model>
     *
     * @throws RuntimeException if the record has no documents
     */
    private function documents(Model $record): MorphToMany
    {
        $relation = method_exists($record, 'documents') ? $record->documents() : null;

        return $relation instanceof MorphToMany ? $relation : throw new RuntimeException($record::class.' has no documents.');
    }
}
