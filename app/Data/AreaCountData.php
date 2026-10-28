<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An area with the number of its active cards, for the sidebar.
 */
#[TypeScript]
final class AreaCountData extends Data
{
    /**
     * @param  string  $area  the value of the area, for example `home`
     * @param  string  $label  the name of the area
     * @param  int  $count  the cards that are not closed
     */
    public function __construct(
        public readonly string $area,
        public readonly string $label,
        public readonly int $count,
    ) {}
}
