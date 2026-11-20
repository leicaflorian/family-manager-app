<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Enums\BankConnectionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BankConnectionFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * The link of a family to one bank (`bank_connections`): the provider, the bank and the session the consent gave.
 *
 * @property int $id
 * @property int $family_id
 * @property string $provider
 * @property string $institution_name
 * @property string $institution_country
 * @property string|null $session The session of the bank, encrypted; empty until the consent is given
 * @property CarbonImmutable|null $valid_until
 * @property BankConnectionStatus $status
 * @property CarbonImmutable|null $last_synced_at
 * @property string|null $last_error
 * @property CarbonImmutable|null $expiry_notified_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, FinancialAccount> $accounts
 * @property-read int|null $accounts_count
 * @property-read Family $family
 * @property-read Collection<int, BankTransaction> $transactions
 * @property-read int|null $transactions_count
 *
 * @method static BankConnectionFactory factory($count = null, $state = [])
 * @method static Builder<static>|BankConnection newModelQuery()
 * @method static Builder<static>|BankConnection newQuery()
 * @method static Builder<static>|BankConnection query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['provider', 'institution_name', 'institution_country', 'valid_until', 'status', 'last_synced_at', 'last_error', 'expiry_notified_at'])]
#[Hidden(['session'])]
final class BankConnection extends FamilyModel
{
    /** @use HasFactory<BankConnectionFactory> */
    use HasFactory;

    /**
     * The accounts read through the connection.
     *
     * @return HasMany<FinancialAccount, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    /**
     * The transactions read through the connection.
     *
     * @return HasMany<BankTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    /**
     * The session of the bank, decrypted.
     *
     * @return string|null the session; null when there is none yet, or when it was encrypted with another `APP_KEY`
     *                     and cannot be read any more (the person has to connect the bank again)
     */
    public function sessionId(): ?string
    {
        $encrypted = $this->getAttributes()['session'] ?? null;

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Saves the session the bank gave and the day its consent ends.
     *
     * @param  string  $session  the session, kept encrypted
     * @param  CarbonImmutable  $validUntil  when the consent ends
     */
    public function writeSession(string $session, CarbonImmutable $validUntil): void
    {
        // Written by the query, not by `save()`: saving compares the new value with the old one, decrypting it, and an
        // old session that cannot be decrypted any more (the `APP_KEY` changed) is exactly when it has to be replaced.
        // The row is already in hand, so the family scope is lifted and the family of the row is checked instead: the sync
        // runs without a signed-in family.
        self::query()->withoutGlobalScopes()->whereKey($this->getKey())->where('family_id', $this->family_id)->update([
            'session' => Crypt::encryptString($session),
            'valid_until' => $validUntil,
            'updated_at' => now(),
        ]);
        $this->refresh();
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_until' => 'immutable_datetime',
            'status' => BankConnectionStatus::class,
            'last_synced_at' => 'immutable_datetime',
            'expiry_notified_at' => 'immutable_datetime',
        ];
    }
}
