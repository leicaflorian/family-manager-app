<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DatoDellaFamiglia;
use App\Models\User;

/**
 * I permessi sui documenti: anche un `member` li carica e li modifica.
 * Eliminarli resta di un `admin`.
 */
final class DocumentPolicy extends PolicyDellaFamiglia
{
    /**
     * Caricare un documento: tutti.
     *
     * @param  User  $utente  chi è entrato
     */
    public function create(User $utente): bool
    {
        return true;
    }

    /**
     * Modificare un documento: tutti quelli della sua famiglia.
     *
     * @param  User  $utente  chi è entrato
     * @param  DatoDellaFamiglia  $dato  il documento
     */
    public function update(User $utente, DatoDellaFamiglia $dato): bool
    {
        return $this->dellaFamiglia($utente, $dato);
    }
}
