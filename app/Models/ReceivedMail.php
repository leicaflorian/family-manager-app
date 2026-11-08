<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MailOutcome;
use Carbon\CarbonImmutable;
use Database\Factories\ReceivedMailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A mail that reached the address for the bills of a family (`received_mails`), one row per mail however many times Mailgun delivered it.
 *
 * @property int $id
 * @property int $family_id
 * @property string $event_id
 * @property string $sender
 * @property string $sender_address
 * @property string $recipient
 * @property array<array-key, mixed> $recipients
 * @property string|null $subject
 * @property CarbonImmutable $received_at
 * @property int $deliveries
 * @property MailOutcome $outcome
 * @property array<array-key, mixed> $notes
 * @property array<array-key, mixed> $skipped
 * @property array<array-key, mixed>|null $body
 * @property string|null $gmail_code
 * @property int|null $organization_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Family $family
 * @property-read Collection<int, File> $files
 * @property-read int|null $files_count
 * @property-read Organization|null $organization
 *
 * @method static ReceivedMailFactory factory($count = null, $state = [])
 * @method static Builder<static>|ReceivedMail newModelQuery()
 * @method static Builder<static>|ReceivedMail newQuery()
 * @method static Builder<static>|ReceivedMail query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['event_id', 'sender', 'sender_address', 'recipient', 'recipients', 'subject', 'received_at', 'deliveries', 'outcome', 'notes', 'skipped', 'body', 'gmail_code', 'organization_id'])]
final class ReceivedMail extends FamilyModel
{
    /** @use HasFactory<ReceivedMailFactory> */
    use HasFactory;

    /**
     * The files saved from the mail.
     *
     * @return HasMany<File, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * The organization the sender was recognised as, if it still exists.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Who wrote, as the family reads it: the organization of a known sender, otherwise the name in the `From`
     * header, otherwise the address.
     */
    public function senderName(): string
    {
        if ($this->organization instanceof Organization) {
            return $this->organization->name;
        }

        if (preg_match('/^\s*"?([^"<]*?)"?\s*<[^>]*>\s*$/u', $this->sender, $match) === 1 && $match[1] !== '') {
            return $match[1];
        }

        return $this->sender_address;
    }

    /**
     * The types of the columns.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'received_at' => 'immutable_datetime',
            'deliveries' => 'integer',
            'outcome' => MailOutcome::class,
            'notes' => 'array',
            'skipped' => 'array',
            'body' => 'array',
        ];
    }
}
