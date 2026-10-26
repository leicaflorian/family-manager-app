<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * Per gli enum delle scelte fisse: l'etichetta in italiano di ogni valore e
 * l'elenco delle opzioni per i menu delle pagine.
 */
trait HaEtichetta
{
    /**
     * Il nome del valore come lo legge la persona.
     */
    abstract public function etichetta(): string;

    /**
     * Le opzioni per un menu, nell'ordine in cui sono dichiarati i valori.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function opzioni(): array
    {
        return array_map(
            static fn (self $caso): array => ['value' => $caso->value, 'label' => $caso->etichetta()],
            self::cases(),
        );
    }
}
