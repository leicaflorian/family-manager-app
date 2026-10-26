<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Family;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * La famiglia di chi è entrato, come la vedono tutte le pagine.
 */
#[TypeScript]
final class FamigliaData extends Data
{
    /**
     * @param  string  $name  il nome della famiglia, per esempio «Bianchi»
     */
    public function __construct(
        public readonly string $name,
    ) {}

    /**
     * I dati di una famiglia.
     *
     * @param  Family  $famiglia  la famiglia
     */
    public static function daFamiglia(Family $famiglia): self
    {
        return new self($famiglia->name);
    }
}
