<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Devices\ForgetLinkedDevices;
use App\Enums\Role;
use App\Enums\Theme;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property int $family_id
 * @property Role $role
 * @property string|null $locale The language of the account, saved from the browser; null until the first request
 * @property array{months: int, person_id: int|null, hidden_areas: list<string>}|null $agenda_filters The filters of the Agenda: months, person_id and hidden_areas; null until the person changes them
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property Theme $theme system, light or dark
 * @property-read Family $family
 * @property-read Collection<int, LinkedDevice> $linkedDevices
 * @property-read int|null $linked_devices_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Person|null $person
 *
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'email', 'password', 'family_id', 'role', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Forgets the linked phones when the password changes, whichever way it changed
     * (the page *My account*, the reset of Fortify): a new password signs the phones out.
     */
    protected static function booted(): void
    {
        self::updated(static function (self $user): void {
            if ($user->wasChanged('password')) {
                resolve(ForgetLinkedDevices::class)->handle($user);
            }
        });
    }

    /**
     * The family of the account. Accounts have no family scope: signing in
     * must find all of them.
     *
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * The person of the family tied to the account.
     *
     * @return HasOne<Person, $this>
     */
    public function person(): HasOne
    {
        return $this->hasOne(Person::class);
    }

    /**
     * The phones linked to the account.
     *
     * @return HasMany<LinkedDevice, $this>
     */
    public function linkedDevices(): HasMany
    {
        return $this->hasMany(LinkedDevice::class);
    }

    /**
     * Whether the account can do everything in its family.
     */
    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * Whether it is the only `admin` of its family: then it cannot delete itself.
     */
    public function isLastAdmin(): bool
    {
        return $this->isAdmin()
            && ! self::query()->where('family_id', $this->family_id)->where('role', Role::Admin)->whereKeyNot($this->id)->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'theme' => Theme::class,
            'agenda_filters' => 'array',
        ];
    }
}
