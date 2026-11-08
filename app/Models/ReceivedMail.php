<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MailOutcome;
use App\Support\Mail\AttachmentRules;
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
 * @property array<array-key, mixed>|null $saved_files
 * @property array<array-key, mixed>|null $body
 * @property string|null $gmail_code
 * @property int|null $organization_id
 * @property array<array-key, mixed>|null $screening
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
#[Fillable(['event_id', 'sender', 'sender_address', 'recipient', 'recipients', 'subject', 'received_at', 'deliveries', 'outcome', 'notes', 'skipped', 'saved_files', 'body', 'gmail_code', 'organization_id', 'screening'])]
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
     * The files the mail saved, whether they are still there or not: the key against a second delivery and the
     * name of each, in the order they were saved.
     *
     * @return list<array{key: string, name: string}>
     */
    public function savedFiles(): array
    {
        $saved = [];

        foreach ($this->saved_files ?? [] as $entry) {
            if (is_array($entry) && is_string($entry['key'] ?? null) && is_string($entry['name'] ?? null)) {
                $saved[] = ['key' => $entry['key'], 'name' => $entry['name']];
            }
        }

        return $saved;
    }

    /**
     * Writes on the mail that it saved some files, and saves it: once deleted, a file is still known as saved
     * («File eliminato»). A file already written (by a delivery before) is not written twice.
     *
     * @param  File  ...$files  the files just saved, with their `mail_key`
     */
    public function recordSaved(File ...$files): void
    {
        $saved = $this->savedFiles();
        $keys = array_column($saved, 'key');

        foreach ($files as $file) {
            if ($file->mail_key !== null && ! in_array($file->mail_key, $keys, true)) {
                $saved[] = ['key' => $file->mail_key, 'name' => $file->original_name];
                $keys[] = $file->mail_key;
            }
        }

        $this->forceFill(['saved_files' => $saved])->save();
    }

    /**
     * Each file the mail saved with the file itself, null once it was deleted: those written on the mail, then
     * those linked to it and not written (saved before the mail kept the list).
     *
     * @return list<array{key: string, name: string, file: File|null}>
     */
    public function savedEntries(): array
    {
        $files = $this->files->keyBy(static fn (File $file): string => $file->mail_key ?? 'file-'.$file->id);
        $entries = array_map(
            static fn (array $saved): array => [...$saved, 'file' => $files->get($saved['key'])],
            $this->savedFiles(),
        );
        $written = array_column($entries, 'key');

        foreach ($files as $key => $file) {
            if (! in_array($key, $written, true)) {
                $entries[] = ['key' => (string) $key, 'name' => $file->original_name, 'file' => $file];
            }
        }

        return $entries;
    }

    /**
     * The key of the notice of the mail, the text saved as a file: the position -1, which no attachment has.
     */
    public function noticeKey(): string
    {
        return AttachmentRules::mailKey($this->event_id, -1);
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
            'saved_files' => 'array',
            'body' => 'array',
            'screening' => 'array',
        ];
    }
}
