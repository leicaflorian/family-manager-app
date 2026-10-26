<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DatoDellaFamiglia;
use App\Models\User;

/**
 * I permessi sui dati di una famiglia, uguali per tutti i modelli: tutti vedono
 * tutto, solo un `admin` crea, modifica ed elimina.
 *
 * Che il dato sia della famiglia lo garantisce già lo scope (un dato di un'altra
 * famiglia non si trova, e risponde 404); il controllo qui sotto è una seconda
 * difesa, per i dati letti senza scope.
 */
class PolicyDellaFamiglia
{
    /**
     * Vedere l'elenco: tutti.
     *
     * @param  User  $utente  chi è entrato
     */
    public function viewAny(User $utente): bool
    {
        return true;
    }

    /**
     * Vedere un dato: tutti quelli della sua famiglia.
     *
     * @param  User  $utente  chi è entrato
     * @param  DatoDellaFamiglia  $dato  il dato
     */
    public function view(User $utente, DatoDellaFamiglia $dato): bool
    {
        return $this->dellaFamiglia($utente, $dato);
    }

    /**
     * Creare: solo un `admin`.
     *
     * @param  User  $utente  chi è entrato
     */
    public function create(User $utente): bool
    {
        return $utente->isAdmin();
    }

    /**
     * Modificare: solo un `admin` della famiglia.
     *
     * @param  User  $utente  chi è entrato
     * @param  DatoDellaFamiglia  $dato  il dato
     */
    public function update(User $utente, DatoDellaFamiglia $dato): bool
    {
        return $utente->isAdmin() && $this->dellaFamiglia($utente, $dato);
    }

    /**
     * Eliminare: solo un `admin` della famiglia.
     *
     * @param  User  $utente  chi è entrato
     * @param  DatoDellaFamiglia  $dato  il dato
     */
    public function delete(User $utente, DatoDellaFamiglia $dato): bool
    {
        return $utente->isAdmin() && $this->dellaFamiglia($utente, $dato);
    }

    /**
     * Il dato è della famiglia di chi è entrato.
     *
     * @param  User  $utente  chi è entrato
     * @param  DatoDellaFamiglia  $dato  il dato
     */
    protected function dellaFamiglia(User $utente, DatoDellaFamiglia $dato): bool
    {
        return $dato->family_id === $utente->family_id;
    }
}
