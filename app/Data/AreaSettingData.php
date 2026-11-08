<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of *Settings → Areas*: an area with its active cards and whether the family hid it.
 */
#[TypeScript]
final class AreaSettingData extends Data
{
    /**
     * @param  string  $area  the value of the area, for example `home`
     * @param  string  $label  the name of the area
     * @param  int  $count  the cards that are not closed
     * @param  bool  $hidden  whether the family hid the area
     */
    public function __construct(
        public readonly string $area,
        public readonly string $label,
        public readonly int $count,
        public readonly bool $hidden,
    ) {}
}
