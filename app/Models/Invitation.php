<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PersonRole;
use App\Enums\Role;
use App\Models\Scopes\FamilyScope;
use Carbon\CarbonImmutable;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invitation to join the family (`invitations`): valid for 7 days and only once.
 *
 * @property int $id
 * @property int $family_id
 * @property string $email
 * @property Role $role
 * @property int|null $person_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property PersonRole|null $person_role
 * @property string $token
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $creator
 * @property-read Family $family
 * @property-read Person|null $person
 *
 * @method static InvitationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Invitation newModelQuery()
 * @method static Builder<static>|Invitation newQuery()
 * @method static Builder<static>|Invitation query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['email', 'role', 'person_id', 'first_name', 'last_name', 'person_role', 'token', 'expires_at', 'accepted_at'])]
final class Invitation extends FamilyModel
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    /** For how many days an invitation is valid. */
    public const int DAYS = 7;

    /**
     * The invitation with this token, if it can still be accepted: unused, not
     * expired, for an email that has no account yet, and for a person who is
     * still there without an account (or for a new person, with a name).
     *
     * It is the only read of invitations outside the current family: whoever
     * opens the link has not signed in yet, and the 64-character token is the key.
     *
     * @param  string  $token  the token of the link
     */
    public static function findValid(string $token): ?self
    {
        return self::query()
            ->withoutGlobalScope(FamilyScope::class)
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->whereNotExists(fn ($q) => $q->from('users')->whereColumn('users.email', 'invitations.email'))
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNull('person_id')->whereNotNull('first_name'))
                ->orWhereExists(fn ($q) => $q->from('people')->whereColumn('people.id', 'invitations.person_id')->whereNull('people.user_id')))
            ->first();
    }

    /**
     * The existing person, if the invitation is for them.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The account that sent the invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'person_role' => PersonRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
