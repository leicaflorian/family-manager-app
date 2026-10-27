<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReminderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reminder, with the records it refers to (`reminders`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property CarbonImmutable $due_date
 * @property ReminderStatus $status
 * @property int|null $person_id
 * @property bool $is_automatic
 * @property string|null $source_field
 * @property int|null $reminder_group_id
 * @property array<array-key, mixed>|null $notifications_sent
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Family $family
 * @property-read Person|null $person
 * @property-read ReminderGroup|null $reminderGroup
 *
 * @method static ReminderFactory factory($count = null, $state = [])
 * @method static Builder<static>|Reminder newModelQuery()
 * @method static Builder<static>|Reminder newQuery()
 * @method static Builder<static>|Reminder query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['title', 'due_date', 'status', 'person_id', 'is_automatic', 'source_field', 'reminder_group_id', 'notifications_sent', 'notes'])]
final class Reminder extends FamilyModel
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    /**
     * The person it refers to.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The alert group of the reminder.
     *
     * @return BelongsTo<ReminderGroup, $this>
     */
    public function reminderGroup(): BelongsTo
    {
        return $this->belongsTo(ReminderGroup::class);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'status' => ReminderStatus::class,
            'is_automatic' => 'boolean',
            'notifications_sent' => 'array',
        ];
    }
}
