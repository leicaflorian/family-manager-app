<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Family;
use App\Services\FamilyFeatures;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The family of whoever signed in, as every page sees it.
 */
#[TypeScript]
final class FamilyData extends Data
{
    /**
     * @param  string  $name  the name of the family, for example "Bianchi"
     * @param  array<string, bool>  $features  which parts of the app the family has, by `Feature` value
     */
    public function __construct(
        public readonly string $name,
        #[LiteralTypeScriptType('Record<App.Enums.Feature, boolean>')]
        public readonly array $features,
    ) {}

    /**
     * The data of a family.
     *
     * @param  Family  $family  the family
     */
    public static function fromFamily(Family $family): self
    {
        return new self($family->name, resolve(FamilyFeatures::class)->all($family));
    }
}
