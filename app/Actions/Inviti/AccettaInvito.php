<?php

declare(strict_types=1);

namespace App\Actions\Inviti;

use App\Enums\RuoloPersona;
use App\Models\Invitation;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\Iniziali;
use Illuminate\Support\Facades\DB;

/**
 * Accetta un invito: nasce l'account, legato alla persona dell'invito o a una nuova.
 */
final class AccettaInvito
{
    /**
     * Crea l'account con il livello dell'invito e lo lega alla persona. L'email è
     * già confermata: il link è arrivato proprio lì.
     *
     * @param  Invitation  $invito  un invito valido (vedi `Invitation::valido()`)
     * @param  string  $password  la password scelta
     * @return User l'account creato
     */
    public function handle(Invitation $invito, string $password): User
    {
        return DB::transaction(fn (): User => CurrentFamily::per($invito->family_id, function () use ($invito, $password): User {
            $persona = $invito->person ?? Person::query()->create([
                'first_name' => (string) $invito->first_name,
                'last_name' => (string) $invito->last_name,
                'initials' => Iniziali::di((string) $invito->first_name, (string) $invito->last_name),
                'role' => $invito->person_role ?? RuoloPersona::Other,
            ]);

            $account = User::query()->create([
                'family_id' => $invito->family_id,
                'name' => trim($persona->first_name.' '.$persona->last_name),
                'email' => $invito->email,
                'password' => $password,
                'role' => $invito->role,
            ]);
            $account->forceFill(['email_verified_at' => now()])->save();

            $persona->update(['user_id' => $account->id]);
            $invito->update(['accepted_at' => now(), 'person_id' => $persona->id]);

            return $account;
        }));
    }
}
