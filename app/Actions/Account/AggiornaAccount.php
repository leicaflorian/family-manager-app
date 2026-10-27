<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use App\Support\Iniziali;
use Illuminate\Support\Facades\DB;

/**
 * Cambia nome, cognome ed email di un account, e il nome anche nella sua persona.
 */
final class AggiornaAccount
{
    /**
     * Salva i dati. Un'email nuova va confermata di nuovo.
     *
     * @param  User  $utente  l'account
     * @param  string  $nome  il nome
     * @param  string  $cognome  il cognome
     * @param  string  $email  l'email per entrare
     */
    public function handle(User $utente, string $nome, string $cognome, string $email): void
    {
        DB::transaction(function () use ($utente, $nome, $cognome, $email): void {
            $utente->fill(['name' => "{$nome} {$cognome}", 'email' => $email]);

            if ($utente->isDirty('email')) {
                $utente->email_verified_at = null;
            }

            $utente->save();
            $utente->person?->update(['first_name' => $nome, 'last_name' => $cognome, 'initials' => Iniziali::di($nome, $cognome)]);
        });

        if ($utente->email_verified_at === null) {
            $utente->sendEmailVerificationNotification();
        }
    }
}
