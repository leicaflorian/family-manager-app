<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The person a reminder is for.
 */
#[TypeScript]
final class ReminderPersonData extends Data
{
    /**
     * @param  int  $id  the id of the person
     * @param  string  $name  the first name
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}
}
