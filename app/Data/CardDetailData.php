<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The page of one card: its heading, dates, notices, data, linked cards,
 * documents and payments.
 */
#[TypeScript]
final class CardDetailData extends Data
{
    /**
     * @param  CardData  $card  the row of the card
     * @param  string  $heading  the line under the name, for example «Car · 40/123456 · policyholder Marco»
     * @param  list<CardDateData>  $dates  the dates of the tiles
     * @param  list<CardNoticeData>  $notices  the notices of the card: what its data and its reminders say
     * @param  list<CardValueData>  $values  the data, without the empty fields
     * @param  list<CardData>  $related  the linked cards, in both directions
     * @param  list<CardAddData>  $addable  the cards whoever looks can add already linked to this one
     * @param  list<DocumentData>  $documents  the first documents linked to the card
     * @param  string|null  $documents_more  the address of the list of all its documents, null when it has no more than those shown
     * @param  bool  $can_edit  whether whoever looks can edit the card
     * @param  bool  $can_delete  whether whoever looks can delete the card
     * @param  bool  $can_delete_documents  whether whoever looks can delete the documents of the card
     * @param  CardBudgetData|null  $budget  the payments of the card, null for the types without any
     * @param  CardNumberData|null  $status  the status of the card as a figure beside its dates, null when it has none
     * @param  array{quantity: float, unit: string, price: float|null, href: string}|null  $consumption  the last 12 months of consumption of a utility with a unit, null for any other card
     */
    public function __construct(
        public readonly CardData $card,
        public readonly string $heading,
        #[DataCollectionOf(CardDateData::class)]
        public readonly array $dates,
        #[DataCollectionOf(CardNoticeData::class)]
        public readonly array $notices,
        #[DataCollectionOf(CardValueData::class)]
        public readonly array $values,
        #[DataCollectionOf(CardData::class)]
        public readonly array $related,
        #[DataCollectionOf(CardAddData::class)]
        public readonly array $addable,
        #[DataCollectionOf(DocumentData::class)]
        public readonly array $documents,
        public readonly ?string $documents_more,
        public readonly bool $can_edit,
        public readonly bool $can_delete,
        public readonly bool $can_delete_documents,
        public readonly ?CardBudgetData $budget = null,
        public readonly ?CardNumberData $status = null,
        public readonly ?array $consumption = null,
    ) {}
}
