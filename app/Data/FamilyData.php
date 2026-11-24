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
     * @param  bool  $mail_available  whether this server can offer the mail for the bills (it has the mail domain): false hides every way to turn it on
     */
    public function __construct(
        public readonly string $name,
        #[LiteralTypeScriptType('Record<App.Enums.Feature, boolean>')]
        public readonly array $features,
        public readonly bool $mail_available,
    ) {}

    /**
     * The data of a family.
     *
     * @param  Family  $family  the family
     */
    public static function fromFamily(Family $family): self
    {
        $features = resolve(FamilyFeatures::class);

        return new self($family->name, $features->all($family), $features->mailAvailable());
    }
}
