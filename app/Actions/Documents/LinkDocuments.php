<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use RuntimeException;

/**
 * Links documents to a card and unlinks them; the documents stay. Linking a
 * document twice changes nothing.
 */
final class LinkCardDocuments
{
    /**
     * Links and unlinks the documents of the card.
     *
     * @param  Model  $card  the card
     * @param  Collection<int, Document>  $link  the documents to link
     * @param  Collection<int, Document>  $unlink  the documents to unlink
     *
     * @throws RuntimeException if the card has no documents
     */
    public function handle(Model $card, Collection $link, Collection $unlink): void
    {
        $documents = $this->documents($card);

        if ($link->isNotEmpty()) {
            $documents->syncWithoutDetaching($link->modelKeys());
        }

        if ($unlink->isNotEmpty()) {
            $documents->detach($unlink->modelKeys());
        }
    }

    /**
     * The documents relation of a card.
     *
     * @param  Model  $card  the card
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
