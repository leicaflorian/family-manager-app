<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Person;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Una persona della famiglia, quanto basta per un elenco o per il suo cerchio.
 */
#[TypeScript]
final class PersonaBreveData extends Data
{
    /**
     * @param  int  $id  l'id della persona
     * @param  string  $first_name  il nome
     * @param  string  $last_name  il cognome
     * @param  string  $initials  le iniziali del cerchio
     */
    public function __construct(
        public readonly int $id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $initials,
    ) {}

    /**
     * I dati di una persona.
     *
     * @param  Person  $persona  la persona
     */
    public static function daPersona(Person $persona): self
    {
        return new self($persona->id, $persona->first_name, $persona->last_name, $persona->initials);
    }
}
