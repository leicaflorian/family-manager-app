<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * La famiglia di cui si stanno leggendo e scrivendo i dati.
 *
 * In una richiesta è la famiglia di chi è entrato. Fuori da una richiesta
 * (comandi, seeder, test) si imposta a mano con `set()` e si toglie con
 * `clear()`. Lo stato vive nel container di Laravel come istanza *scoped*:
 * riparte vuoto a ogni richiesta e a ogni test, così una famiglia impostata a
 * mano non resta attaccata a quella dopo.
 */
final class CurrentFamily
{
    /** La famiglia impostata a mano, che vale più di quella di chi è entrato. */
    private ?int $impostata = null;

    /**
     * L'id della famiglia corrente, o null se non c'è.
     */
    public static function id(): ?int
    {
        $corrente = self::istanza();

        if ($corrente->impostata !== null) {
            return $corrente->impostata;
        }

        $utente = Auth::user();

        return $utente instanceof User ? $utente->family_id : null;
    }

    /**
     * Imposta a mano la famiglia, per lavorare fuori da una richiesta.
     *
     * @param  int  $id  l'id della famiglia
     */
    public static function set(int $id): void
    {
        self::istanza()->impostata = $id;
    }

    /**
     * Esegue un lavoro con la famiglia impostata a mano, poi rimette quella di prima.
     *
     * Serve a chi scrive i dati di una famiglia senza esserci entrato: l'iscrizione,
     * il seeder, la copia da Directus. Quella di prima torna anche se il lavoro si
     * ferma con un'eccezione.
     *
     * @template T
     *
     * @param  int  $id  l'id della famiglia
     * @param  callable(): T  $lavoro  il lavoro da fare
     * @return T quello che restituisce il lavoro
     */
    public static function per(int $id, callable $lavoro): mixed
    {
        $corrente = self::istanza();
        $prima = $corrente->impostata;
        $corrente->impostata = $id;

        try {
            return $lavoro();
        } finally {
            $corrente->impostata = $prima;
        }
    }

    /**
     * Toglie la famiglia impostata a mano.
     */
    public static function clear(): void
    {
        self::istanza()->impostata = null;
    }

    /**
     * L'istanza del container che tiene la famiglia impostata.
     */
    private static function istanza(): self
    {
        return resolve(self::class);
    }
}
