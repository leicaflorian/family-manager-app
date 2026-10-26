<?php

declare(strict_types=1);

namespace App\Support\Schema;

use Illuminate\Database\Schema\Blueprint;

/**
 * Le colonne che ogni tabella dei dati di una famiglia ripete, scritte una
 * volta sola per tutte le migrazioni.
 */
final class Colonne
{
    /**
     * `family_id` obbligatorio, con indice: eliminando una famiglia si
     * eliminano anche i suoi dati.
     */
    public static function famiglia(Blueprint $table): void
    {
        $table->foreignId('family_id')->constrained()->cascadeOnDelete();
    }

    /**
     * I campi di sistema: chi ha creato e chi ha modificato il dato (vuoti se
     * l'account non c'è più), e quando.
     */
    public static function sistema(Blueprint $table): void
    {
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
    }
}
