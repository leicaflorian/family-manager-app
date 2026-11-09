<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LinkedDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone linked to an account: it enters with the secret of its cookie, without a password (`linked_devices`).
 *
 * @property int $id
 * @property int $family_id
 * @property int $user_id
 * @property string $name
 * @property string $token_hash sha256 of the secret in the cookie of the phone
 * @property string|null $session_id The session of the phone, deleted when the phone is forgotten
 * @property CarbonImmutable|null $last_used_at The last time the phone entered
 * @property CarbonImmutable|null $last_upload_at The last time the phone uploaded a document
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Family $family
 * @property-read User $user
 *
 * @method static LinkedDeviceFactory factory($count = null, $state = [])
 * @method static Builder<static>|LinkedDevice newModelQuery()
 * @method static Builder<static>|LinkedDevice newQuery()
 * @method static Builder<static>|LinkedDevice query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['user_id', 'name', 'token_hash', 'session_id', 'last_used_at', 'last_upload_at'])]
final class LinkedDevice extends FamilyModel
{
    /** @use HasFactory<LinkedDeviceFactory> */
    use HasFactory;

    use MassPrunable;

    /** Days without entries after which a phone is forgotten. */
    public const int EXPIRES_AFTER_DAYS = 180;

    /**
     * Finds the phone of a secret. It runs outside the scope of the family, because whoever opens the link
     * has not entered yet: only the sha256 of the secret is looked up, and an expired phone is not found.
     *
     * @param  string  $token  the secret from the cookie
     */
    public static function findByToken(string $token): ?self
    {
        return self::query()
            ->withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $token))
            ->whereNot(fn (Builder $query) => self::applyExpired($query))
            ->first();
    }

    /**
     * Whether a phone is still linked (not forgotten, not expired). It runs outside the scope of the family, like
     * `findByToken`: the middleware that asks is before any family is in use.
     *
     * @param  int  $id  the id kept in the session of the phone
     */
    public static function isActive(int $id): bool
    {
        return self::query()
            ->withoutGlobalScopes()
            ->whereKey($id)
            ->whereNot(fn (Builder $query) => self::applyExpired($query))
            ->exists();
    }

    /**
     * The expired phones, of every family: pruning runs without a signed-in family, so the scope is off.
     *
     * @return Builder<LinkedDevice>
     */
    public function prunable(): Builder
    {
        return self::query()->withoutGlobalScopes()->where(fn (Builder $query) => self::applyExpired($query));
    }

    /**
     * The account the phone is linked to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The dates of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'immutable_datetime',
            'last_upload_at' => 'immutable_datetime',
        ];
    }

    /**
     * Restricts the query to the phones with no entry for 180 days: the last one is `last_used_at`, or
     * `created_at` for a phone that never entered.
     *
     * @param  Builder<LinkedDevice>  $query
     */
    private static function applyExpired(Builder $query): void
    {
        $query->whereRaw('COALESCE(last_used_at, created_at) < ?', [now()->subDays(self::EXPIRES_AFTER_DAYS)]);
    }
}
