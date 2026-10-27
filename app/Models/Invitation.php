<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Ruolo;
use App\Enums\RuoloPersona;
use App\Models\Scopes\FamilyScope;
use Carbon\CarbonImmutable;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un invito a entrare nella famiglia (`invitations`): vale 7 giorni e una volta sola.
 *
 * @property int $id
 * @property int $family_id
 * @property string $email
 * @property Ruolo $role
 * @property int|null $person_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property RuoloPersona|null $person_role
 * @property string $token
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Family $family
 * @property-read \App\Models\Person|null $person
 * @method static \Database\Factories\InvitationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Invitation newModelQuery()
 * @method static Builder<static>|Invitation newQuery()
 * @method static Builder<static>|Invitation query()
 * @mixin \Eloquent
 */
#[Fillable(['email', 'role', 'person_id', 'first_name', 'last_name', 'person_role', 'token', 'expires_at', 'accepted_at'])]
final class Invitation extends DatoDellaFamiglia
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    /** Per quanti giorni vale un invito. */
    public const int GIORNI = 7;

    /**
     * L'invito con questo codice, se si può ancora accettare: non usato, non
     * scaduto, e con un'email che non ha già un account.
     *
     * È l'unica lettura degli inviti fuori dalla famiglia corrente: chi apre il
     * link non è ancora entrato, e il codice di 64 caratteri fa da chiave.
     *
     * @param  string  $token  il codice del link
     */
    public static function valido(string $token): ?self
    {
        return self::query()
            ->withoutGlobalScope(FamilyScope::class)
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->whereNotExists(fn ($q) => $q->from('users')->whereColumn('users.email', 'invitations.email'))
            ->first();
    }

    /**
     * La persona che c'è già, se l'invito è per lei.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * L'account che ha mandato l'invito.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Ruolo::class,
            'person_role' => RuoloPersona::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
