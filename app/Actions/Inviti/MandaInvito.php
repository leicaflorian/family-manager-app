<?php

declare(strict_types=1);

namespace App\Actions\Inviti;

use App\Enums\Ruolo;
use App\Enums\RuoloPersona;
use App\Mail\Invito;
use App\Models\Invitation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Crea l'invito a entrare nella famiglia corrente e lo manda per email.
 */
final class MandaInvito
{
    /**
     * Crea l'invito, che vale 7 giorni, e manda l'email con il link.
     *
     * @param  array{email: string, role: string, person_id?: int|null, first_name?: string|null, last_name?: string|null, person_role?: string|null}  $dati  i dati del modulo, già controllati
     * @return Invitation l'invito creato
     */
    public function handle(array $dati): Invitation
    {
        $perPersona = isset($dati['person_id']);

        $invito = Invitation::query()->create([
            'email' => Str::lower($dati['email']),
            'role' => Ruolo::from($dati['role']),
            'person_id' => $dati['person_id'] ?? null,
            'first_name' => $perPersona ? null : ($dati['first_name'] ?? null),
            'last_name' => $perPersona ? null : ($dati['last_name'] ?? null),
            'person_role' => $perPersona ? null : RuoloPersona::tryFrom($dati['person_role'] ?? ''),
            'token' => Str::random(64),
            'expires_at' => now()->addDays(Invitation::GIORNI),
        ]);

        Mail::to($invito->email)->queue(new Invito($invito));

        return $invito;
    }
}
