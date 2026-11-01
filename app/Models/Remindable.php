<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The row of `remindables`: the thing a reminder refers to. It is a bridge
 * table without `family_id` (like `documentables`): it is reached only from
 * a reminder, which belongs to the family.
 *
 * @property int $id
 * @property int $reminder_id
 * @property string $remindable_type
 * @property int $remindable_id
 * @property-read Reminder $reminder
 * @property-read Model $subject
 */
#[Unguarded]
#[Table(name: 'remindables')]
#[WithoutTimestamps]
final class Remindable extends Model
{
    /**
     * The reminder of the row.
     *
     * @return BelongsTo<Reminder, $this>
     */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    /**
     * The card, document or other record the reminder refers to.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'remindable_type', 'remindable_id');
    }
}
