<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Compila `created_by` e `updated_by` con l'account di chi è entrato, come
 * `user_created` e `user_updated` di Directus. Fuori da una richiesta (comandi,
 * seeder) restano come sono.
 *
 * @property int|null $created_by
 * @property int|null $updated_by
 */
trait RegistraAutori
{
    /**
     * Collega la compilazione alla nascita e alla modifica del modello.
     */
    public static function bootRegistraAutori(): void
    {
        static::creating(static function (Model $model): void {
            $autore = Auth::id();

            if ($autore !== null) {
                $model->setAttribute('created_by', $model->getAttribute('created_by') ?? $autore);
                $model->setAttribute('updated_by', $autore);
            }
        });

        static::updating(static function (Model $model): void {
            $autore = Auth::id();

            if ($autore !== null) {
                $model->setAttribute('updated_by', $autore);
            }
        });
    }
}
