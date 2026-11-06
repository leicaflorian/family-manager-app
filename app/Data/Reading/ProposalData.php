<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a read file would do when confirmed, with the choices of the person: the document, the expense or income,
 * the health event, the missing cards and the policy it takes from another file. Nothing is written.
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
     */
    public function __construct(
        public readonly int $fileId,
        public readonly DocumentProposalData $document,
        public readonly ?MovementProposalData $movement,
        public readonly ?EventProposalData $event,
        public readonly array $records,
        public readonly ?array $policy,
        public readonly array $warnings,
    ) {}
}
