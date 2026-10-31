<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One step of *To begin*, the checklist of the Home of a new family.
 */
#[TypeScript]
final class ChecklistStepData extends Data
{
    /**
     * @param  string  $key  `utilities`, `first_bill`, `card` or `invite`
     * @param  bool  $done  whether the data of the family say it is done
     * @param  string|null  $name  the first name of the person to invite; only for `invite`
     */
    public function __construct(
        public readonly string $key,
        public readonly bool $done,
        public readonly ?string $name,
    ) {}
}
