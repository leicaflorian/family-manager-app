<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReminderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
 * @property-read Remindable|null $link
 * @property-read Person|null $person
 * @property-read ReminderGroup|null $reminderGroup
 *
 * @method static ReminderFactory factory($count = null, $state = [])
 * @method static Builder<static>|Reminder automaticFor(string $field, string $morphType, array<int> $ids)
 * @method static Builder<static>|Reminder open()
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
     * The row of `remindables`: the one thing the reminder refers to.
     *
     * @return HasOne<Remindable, $this>
     */
    public function link(): HasOne
    {
        return $this->hasOne(Remindable::class);
    }

    /**
     * The reminders still to do.
     *
     * @param  Builder<Reminder>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('reminders.status', ReminderStatus::Open->value);
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
     * The automatic reminders of a kind of deadline linked to some cards of a
     * type. Each reminder comes once per card it is linked to, with that card's
     * id as `card_id`, so a caller matches reminders and cards without a query
     * for each card.
     *
     * @param  Builder<Reminder>  $query
     * @param  string  $field  the `source_field`, like `documents.expiry_date`
     * @param  string  $morphType  the morph alias of the cards, like `document`
     * @param  list<int>  $ids  the ids of the cards
     */
    #[Scope]
    protected function automaticFor(Builder $query, string $field, string $morphType, array $ids): void
    {
        $query->select('reminders.*', 'remindables.remindable_id as card_id')
            ->join('remindables', 'remindables.reminder_id', '=', 'reminders.id')
            ->where('reminders.is_automatic', true)
            ->where('reminders.source_field', $field)
            ->where('remindables.remindable_type', $morphType)
            ->whereIn('remindables.remindable_id', $ids);
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
