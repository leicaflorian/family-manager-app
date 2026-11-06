<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The values of the health event a read file proposes.
 */
#[TypeScript]
final class EventValuesData extends Data
{
    /**
     * @param  string|null  $title  what it is called: the one read, otherwise the name of the type
     * @param  string|null  $type  a value of `HealthEventType`
     * @param  int|null  $person_id  the patient
     * @param  int|null  $organization_id  the clinic or pharmacy
     * @param  string|null  $date  `YYYY-MM-DD`
     * @param  string|null  $next_checkup_date  `YYYY-MM-DD`
     * @param  string|null  $outcome  the diagnosis or result
     * @param  string|null  $notes  the summary of the paper
     */
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $type,
        public readonly ?int $person_id,
        public readonly ?int $organization_id,
        public readonly ?string $date,
        public readonly ?string $next_checkup_date,
        public readonly ?string $outcome,
        public readonly ?string $notes,
    ) {}
}
