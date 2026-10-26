<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Una voce di una scelta: il valore e il testo, con una frase di spiegazione facoltativa.
 */
#[TypeScript]
final class OpzioneData extends Data
{
    /**
     * @param  string  $value  il valore che si manda
     * @param  string  $label  il testo che si legge
     * @param  string|null  $help  la frase sotto, per esempio cosa può fare un livello
     */
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly ?string $help = null,
    ) {}

    /**
     * Le opzioni di un enum con le etichette (vedi `HaEtichetta::opzioni()`).
     *
     * @param  list<array{value: string, label: string, help?: string}>  $opzioni  le opzioni come le dà l'enum
     * @return list<self>
     */
    public static function elenco(array $opzioni): array
    {
        return array_map(static fn (array $opzione): self => new self($opzione['value'], $opzione['label'], $opzione['help'] ?? null), $opzioni);
    }
}
