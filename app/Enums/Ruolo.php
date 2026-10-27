<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HaEtichetta;

/**
 * Il livello di un account: cosa può fare nella sua famiglia.
 */
enum Ruolo: string
{
    use HaEtichetta;

    /** Può tutto, come chi ha iscritto la famiglia. */
    case Admin = 'admin';

    /** Vede tutto, carica e modifica i documenti, non elimina niente. */
    case Member = 'member';

    /**
     * Il livello come lo leggono le pagine (tavola *Invita un familiare*).
     */
    public function etichetta(): string
    {
        return match ($this) {
            self::Admin => 'Tutto, come te',
            self::Member => 'Vede tutto e carica i documenti',
        };
    }

    /**
     * Il nome del livello accanto al nome di una persona («Marco Bianchi · Amministratore»).
     */
    public function nome(): string
    {
        return match ($this) {
            self::Admin => 'Amministratore',
            self::Member => 'Familiare',
        };
    }

    /**
     * Cosa vuol dire il livello, la frase sotto la scelta in *Invita un familiare*.
     */
    public function spiegazione(): string
    {
        return match ($this) {
            self::Admin => 'Anche le impostazioni, gli inviti e l\'abbonamento',
            self::Member => 'Paga, registra e carica, ma non cambia le impostazioni',
        };
    }

    /**
     * Le opzioni per la scelta del livello, ognuna con la sua spiegazione.
     *
     * @return list<array{value: string, label: string, help: string}>
     */
    public static function opzioni(): array
    {
        return array_map(
            static fn (self $caso): array => ['value' => $caso->value, 'label' => $caso->etichetta(), 'help' => $caso->spiegazione()],
            self::cases(),
        );
    }
}
