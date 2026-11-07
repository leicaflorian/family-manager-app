<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a medical file does with its health event: the values with the corrections of the person, the event already
 * there it is linked to instead, what is missing to create one, and the reminder that will follow.
 */
#[TypeScript]
final class EventProposalData extends Data
{
    /**
     * @param  EventValuesData  $values  the values: person, place and date are the document's
     * @param  bool  $suggested  whether the box starts ticked: not for a prescription, which usually comes from a visit already there
     * @param  array{id: int, title: string, date: string}|null  $existing  the event already there (or picked by hand) the document is linked to
     * @param  string|null  $blocked  what is missing to create the event; null when nothing is
     * @param  list<array{id: int, title: string, date: string}>  $candidates  the events of the person within a month of the date, the closest first, to pick another by hand
     * @param  string|null  $reminder  the reminder the event makes when the import gives it its next checkup (`I'll remind you to book the checkup`)
     */
    public function __construct(
        public readonly EventValuesData $values,
        public readonly bool $suggested,
        public readonly ?array $existing,
        public readonly ?string $blocked,
        public readonly array $candidates,
        public readonly ?string $reminder,
    ) {}
}
