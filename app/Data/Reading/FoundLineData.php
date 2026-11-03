<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One thing the reading found on the paper, already written for the person (`Due on`, `06/11/2026`).
 */
#[TypeScript]
final class FoundLineData extends Data
{
    /**
     * @param  string  $label  what it is, in the language of the person (`How much`)
     * @param  string  $value  its value, formatted (`84,20 €`, `06/11/2026`)
     */
    public function __construct(
        public readonly string $label,
        public readonly string $value,
    ) {}
}
