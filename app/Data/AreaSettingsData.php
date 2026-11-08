<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything *Settings → Areas* needs.
 */
#[TypeScript]
final class AreaSettingsData extends Data
{
    /**
     * @param  list<AreaSettingData>  $areas  every area, in the order of the family
     * @param  bool  $can_change  whether who looks can change the areas (only an administrator)
     */
    public function __construct(
        public readonly array $areas,
        public readonly bool $can_change,
    ) {}
}
