<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Models\Remindable;
use Illuminate\Database\Eloquent\Model;

/**
 * Takes a thing out of the reminders that refer to it, when the thing is deleted: the reminders stay, without the
 * link, so a manual reminder about a sold car or a closed organization is still there to read.
 */
final class ForgetRemindable
{
    /**
     * Deletes the rows of `remindables` of the thing. The reminders themselves are not touched.
     *
     * @param  Model  $subject  the card, document, person or organization that goes away
     */
    public function handle(Model $subject): void
    {
        Remindable::query()
            ->where('remindable_type', $subject->getMorphClass())
            ->where('remindable_id', $subject->getKey())
            ->delete();
    }
}
