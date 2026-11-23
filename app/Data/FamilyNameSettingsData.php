<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * *Settings → Family name*: the name of the family and whether who looks can change it.
 */
#[TypeScript]
final class FamilyNameSettingsData extends Data
{
    /**
     * @param  string  $name  the name of the family, for example "Bianchi"
     * @param  bool  $can_edit  whether who looks may change it (an `admin`)
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $can_edit,
    ) {}
}
