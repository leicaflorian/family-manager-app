<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\OwnerActionType;
use App\Models\Family;

/**
 * Cancels the deletion of a family: it is active again, not only out of the queue.
 */
final readonly class CancelFamilyDeletion
{
    /**
     * @param  LogOwnerAction  $log  writes the line of the log
     */
    public function __construct(private LogOwnerAction $log) {}

    /**
     * Clears the day of deletion and the suspension.
     *
     * @param  Family  $family  the family that stays
     */
    public function handle(Family $family): void
    {
        $family->forceFill(['delete_after' => null, 'suspended_at' => null])->save();
        $this->log->handle(OwnerActionType::DeletionCancelled, $family);
    }
}
