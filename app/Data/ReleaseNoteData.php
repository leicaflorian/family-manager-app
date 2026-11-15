<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A version of the app in the panel *What's new*: its number, its day and what changed, in one language.
 */
#[TypeScript]
final class ReleaseNoteData extends Data
{
    /**
     * @param  string  $version  the version, for example `1.2.0`
     * @param  string  $date  the day it was released, `yyyy-mm-dd`
     * @param  list<string>  $notes  what changed, in simple sentences
     */
    public function __construct(
        public readonly string $version,
        public readonly string $date,
        public readonly array $notes,
    ) {}
}
