<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CallOutcome;
use Carbon\CarbonImmutable;
use Database\Factories\MailInboxCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One call of Mailgun to the app and how it was answered (`mail_inbox_calls`). It is not a family model: the family is
 * empty when the call did not reach one, and only the command of the owner reads the rows.
 *
 * @property int $id
 * @property CallOutcome $outcome
 * @property int $http_status
 * @property string|null $event_id
 * @property int $attachments
 * @property int $saved
 * @property string|null $error
 * @property array<array-key, mixed> $payload
 * @property int|null $family_id
 * @property int|null $received_mail_id
 * @property CarbonImmutable|null $created_at
 *
 * @method static MailInboxCallFactory factory($count = null, $state = [])
 * @method static Builder<static>|MailInboxCall newModelQuery()
 * @method static Builder<static>|MailInboxCall newQuery()
 * @method static Builder<static>|MailInboxCall query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['outcome', 'http_status', 'event_id', 'attachments', 'saved', 'error', 'payload', 'family_id', 'received_mail_id'])]
final class MailInboxCall extends Model
{
    /** @use HasFactory<MailInboxCallFactory> */
    use HasFactory;

    use MassPrunable;

    /** How many days a call stays in the log. */
    public const int KEPT_DAYS = 90;

    public const ?string UPDATED_AT = null;

    /**
     * The calls older than 90 days.
     *
     * @return Builder<MailInboxCall>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(self::KEPT_DAYS));
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => CallOutcome::class,
            'http_status' => 'integer',
            'attachments' => 'integer',
            'saved' => 'integer',
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
