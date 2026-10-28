<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The page of one card: its heading, dates, notices, data, linked cards and
 * documents.
 */
#[TypeScript]
final class CardDetailData extends Data
{
    /**
     * @param  CardData  $card  the row of the card
     * @param  string  $heading  the line under the name, for example «Car · 40/123456 · policyholder Marco»
     * @param  list<CardDateData>  $dates  the dates of the tiles
     * @param  list<string>  $notices  the notices the saved data gives
     * @param  list<CardValueData>  $values  the data, without the empty fields
     * @param  list<CardData>  $related  the linked cards, in both directions
     * @param  list<CardAddData>  $addable  the cards whoever looks can add already linked to this one
     * @param  list<DocumentData>  $documents  the documents linked to the card
     * @param  bool  $can_edit  whether whoever looks can edit the card
     * @param  bool  $can_delete  whether whoever looks can delete the card
     * @param  bool  $can_delete_documents  whether whoever looks can delete the documents of the card
     */
    public function __construct(
        public readonly CardData $card,
        public readonly string $heading,
        #[DataCollectionOf(CardDateData::class)]
        public readonly array $dates,
        public readonly array $notices,
        #[DataCollectionOf(CardValueData::class)]
        public readonly array $values,
        #[DataCollectionOf(CardData::class)]
        public readonly array $related,
        #[DataCollectionOf(CardAddData::class)]
        public readonly array $addable,
        #[DataCollectionOf(DocumentData::class)]
        public readonly array $documents,
        public readonly bool $can_edit,
        public readonly bool $can_delete,
        public readonly bool $can_delete_documents,
    ) {}
}
