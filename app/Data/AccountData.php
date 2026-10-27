<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Il mio account, come lo mostrano *Impostazioni* e *Il mio account*.
 */
#[TypeScript]
final class AccountData extends Data
{
    /**
     * @param  string  $nome  il nome, dalla persona dell'account
     * @param  string  $cognome  il cognome
     * @param  string  $email  l'email per entrare
     * @param  string  $ruolo  il livello, «Amministratore» o «Familiare»
     * @param  bool  $admin  se può cambiare le impostazioni della famiglia
     * @param  string  $iniziali  le iniziali del cerchio, quando manca la foto
     * @param  string|null  $foto  l'indirizzo della foto
     * @param  bool  $ultimoAdmin  se è l'unico admin, che non può eliminarsi
     * @param  string  $famiglia  il nome della famiglia
     */
    public function __construct(
        public readonly string $nome,
        public readonly string $cognome,
        public readonly string $email,
        public readonly string $ruolo,
        public readonly bool $admin,
        public readonly string $iniziali,
        public readonly ?string $foto,
        public readonly bool $ultimoAdmin,
        public readonly string $famiglia,
    ) {}

    /**
     * I dati di un account. Senza persona, il nome si prende da `users.name`.
     *
     * @param  User  $utente  l'account
     */
    public static function daUtente(User $utente): self
    {
        $persona = $utente->person;

        return new self(
            nome: $persona->first_name ?? $utente->name,
            cognome: $persona->last_name ?? '',
            email: $utente->email,
            ruolo: $utente->role->nome(),
            admin: $utente->isAdmin(),
            iniziali: $persona->initials ?? mb_strtoupper(mb_substr($utente->name, 0, 1)),
            foto: $persona?->photo_id !== null ? route('file.show', $persona->photo_id) : null,
            ultimoAdmin: $utente->ultimoAdmin(),
            famiglia: $utente->family->name,
        );
    }
}
