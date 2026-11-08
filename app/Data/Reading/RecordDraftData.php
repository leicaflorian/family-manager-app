<?php

declare(strict_types=1);

namespace App\Data\Reading;

use App\Data\CardFieldData;
use App\Enums\RecordKind;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A card a file names that is missing, proposed as a box the person can tick (`Create the utility Luce · Edison`).
 */
#[TypeScript]
final class RecordDraftData extends Data
{
    /**
     * @param  RecordKind  $kind  which card
     * @param  string  $title  the text of the box without the verb, in the language of the person (`the utility Electricity · Edison`)
     * @param  array<string, mixed>  $values  the fields the card is created with, by column (`type`, `home_id`, `activation_date`)
     * @param  bool  $suggested  whether the box starts ticked
     * @param  list<string>  $missing  the required fields still empty, as words (`the type`)
     * @param  array{id: int, title: string}|null  $existing  the organization, belonging or intervention already there, to link
     * @param  list<array{id: int, label: string}>  $candidates  the cards of this kind, to pick one by hand
     * @param  string|null  $reminder  the reminder the new card makes (`I'll remind you before the warranty ends`)
     * @param  list<CardFieldData>  $fields  the required fields still empty that the person can write in the box, as the card form draws them
     * @param  list<string>  $complete  the (`date`, `person_id`, `organization_id`) required fields still empty that come from the document, written in its form
     */
    public function __construct(
        public readonly RecordKind $kind,
        public readonly string $title,
        public readonly array $values,
        public readonly bool $suggested,
        public readonly array $missing,
        public readonly ?array $existing = null,
        public readonly array $candidates = [],
        public readonly ?string $reminder = null,
        public readonly array $fields = [],
        public readonly array $complete = [],
    ) {}
}
