<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Feature;
use App\Services\FamilyFeatures;
use Carbon\CarbonImmutable;
use Database\Factories\ReminderNoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A notice of the bell: one reminder (or one received mail, or one payment of the bank), for one account, on the day the morning round sent it (`reminder_notices`).
 *
 * @property int $id
 * @property int $family_id
 * @property int|null $reminder_id
 * @property int $user_id
 * @property CarbonImmutable|null $due_date
 * @property int|null $offset
 * @property CarbonImmutable $sent_at
 * @property CarbonImmutable|null $read_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int|null $received_mail_id
 * @property array<array-key, mixed>|null $lines
 * @property int|null $bank_transaction_id
 * @property int|null $bank_connection_id
 * @property-read BankConnection|null $bankConnection
 * @property-read BankTransaction|null $bankTransaction
 * @property-read Family $family
 * @property-read ReceivedMail|null $mail
 * @property-read Reminder|null $reminder
 * @property-read User $user
 *
 * @method static ReminderNoticeFactory factory($count = null, $state = [])
 * @method static Builder<static>|ReminderNotice newModelQuery()
 * @method static Builder<static>|ReminderNotice newQuery()
 * @method static Builder<static>|ReminderNotice query()
 * @method static Builder<static>|ReminderNotice shown()
 * @method static Builder<static>|ReminderNotice unread()
 *
 * @mixin \Eloquent
 */
#[Fillable(['reminder_id', 'user_id', 'due_date', 'offset', 'sent_at', 'read_at', 'received_mail_id', 'lines', 'bank_transaction_id', 'bank_connection_id'])]
final class ReminderNotice extends FamilyModel
{
    /** @use HasFactory<ReminderNoticeFactory> */
    use HasFactory;

    use MassPrunable;

    /** How many days a notice stays, before it is pruned. */
    public const int KEPT_DAYS = 90;

    /**
     * The notices older than 90 days, of every family: pruning runs without a signed-in family, so the scope is off.
     *
     * @return Builder<ReminderNotice>
     */
    public function prunable(): Builder
    {
        return self::query()->withoutGlobalScopes()->where('sent_at', '<', now()->subDays(self::KEPT_DAYS));
    }

    /**
     * The notices not read yet.
     *
     * @param  Builder<ReminderNotice>  $query
     */
    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * The notices the bell shows: the ones about the bank only while the Budget, where the bank lives, is on, and the ones about the mail only while the Mail is on.
     *
     * @param  Builder<ReminderNotice>  $query
     */
    #[Scope]
    protected function shown(Builder $query): void
    {
        if (! resolve(FamilyFeatures::class)->on(Feature::Budget)) {
            $query->whereNull('bank_transaction_id')->whereNull('bank_connection_id');
        }

        if (! resolve(FamilyFeatures::class)->on(Feature::Mail)) {
            // a mail notice is the one with no reminder and nothing of the bank (the mail itself, or the mails limit)
            $query->where(static fn (Builder $notices): Builder => $notices
                ->whereNotNull('reminder_id')
                ->orWhereNotNull('bank_transaction_id')
                ->orWhereNotNull('bank_connection_id'));
        }
    }

    /**
     * The reminder the notice is about.
     *
     * @return BelongsTo<Reminder, $this>
     */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    /**
     * The mail the notice is about, for a notice that is not about a reminder.
     *
     * @return BelongsTo<ReceivedMail, $this>
     */
    public function mail(): BelongsTo
    {
        return $this->belongsTo(ReceivedMail::class, 'received_mail_id');
    }

    /**
     * The bank connection the notice is about, for a consent that ends.
     *
     * @return BelongsTo<BankConnection, $this>
     */
    public function bankConnection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class);
    }

    /**
     * The bank transaction the notice is about, for a payment the bank settled.
     *
     * @return BelongsTo<BankTransaction, $this>
     */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    /**
     * The account the notice is for.
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
            'due_date' => 'immutable_date',
            'offset' => 'integer',
            'lines' => 'array',
            'sent_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
        ];
    }
}
