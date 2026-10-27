<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReminderGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An alert group of reminders (`reminder_groups`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property bool $is_default
 * @property array<array-key, mixed>|null $deadline_types
 * @property array<array-key, mixed>|null $notifications
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Family $family
 * @property-read Collection<int, Reminder> $reminders
 * @property-read int|null $reminders_count
 *
 * @method static ReminderGroupFactory factory($count = null, $state = [])
 * @method static Builder<static>|ReminderGroup newModelQuery()
 * @method static Builder<static>|ReminderGroup newQuery()
 * @method static Builder<static>|ReminderGroup query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'is_default', 'deadline_types', 'notifications'])]
final class ReminderGroup extends FamilyModel
{
    /** @use HasFactory<ReminderGroupFactory> */
    use HasFactory;

    /**
     * The reminders of the group.
     *
     * @return HasMany<Reminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'deadline_types' => 'array',
            'notifications' => 'array',
        ];
    }
}
