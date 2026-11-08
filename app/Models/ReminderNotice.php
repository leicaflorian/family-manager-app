<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReminderNoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A notice of the bell: one reminder (or one received mail), for one account, on the day the morning round sent it (`reminder_notices`).
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
 * @property-read Family $family
 * @property-read ReceivedMail|null $mail
 * @property-read Reminder|null $reminder
 * @property-read User $user
 *
 * @method static ReminderNoticeFactory factory($count = null, $state = [])
 * @method static Builder<static>|ReminderNotice newModelQuery()
 * @method static Builder<static>|ReminderNotice newQuery()
 * @method static Builder<static>|ReminderNotice query()
 * @method static Builder<static>|ReminderNotice unread()
 *
 * @mixin \Eloquent
 */
#[Fillable(['reminder_id', 'user_id', 'due_date', 'offset', 'sent_at', 'read_at', 'received_mail_id', 'lines'])]
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
