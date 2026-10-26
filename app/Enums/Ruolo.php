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
}
