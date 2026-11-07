<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a read file would do when confirmed, with the choices of the person: the document, the expense or income,
 * the health event, the missing cards and the policy it takes from another file, or, when it names no card, the
 * box to create one. Nothing is written.
 */
#[TypeScript]
final class ProposalData extends Data
{
    /**
     * @param  int  $fileId  the file
     * @param  DocumentProposalData  $document  the fields of the document as the reading proposes them
     * @param  MovementProposalData|null  $movement  the expense or income
     * @param  EventProposalData|null  $event  the health event of a medical paper; null for any other
     * @param  list<RecordDraftData>  $records  the missing cards, or the ones found to link, each with the cards of its kind to pick by hand
     * @param  array{need: bool, giver: ?PolicyGiverData, many: bool}|null  $policy  for an insurance paper: whether it needs the policy of another file or of one registered, the one it takes, and whether more than one fits
     * @param  list<string>  $warnings  the codes on the paper that no card has and no box creates
     * @param  bool  $createCard  the paper names no card (no card to create or link, no health event): the box *Create the card* opens *New card* with the document linked after the import
     */
    public function __construct(
        public readonly int $fileId,
        public readonly DocumentProposalData $document,
        public readonly ?MovementProposalData $movement,
        public readonly ?EventProposalData $event,
        public readonly array $records,
        public readonly ?array $policy,
        public readonly array $warnings,
        public readonly bool $createCard = false,
    ) {}

    /**
     * The same proposal without *Create the card*: for whoever may not create cards, or an import that starts from a
     * card.
     */
    public function withoutCreateCard(): self
    {
        return new self($this->fileId, $this->document, $this->movement, $this->event, $this->records, $this->policy, $this->warnings);
    }
}
