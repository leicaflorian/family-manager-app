<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\OwnerActionType;
use App\Models\Family;
use Illuminate\Support\Facades\DB;

/**
 * Cancels the deletion of a family: it goes back to how it was before, active, or still suspended when the owner
 * had suspended it before scheduling the deletion.
 */
final readonly class CancelFamilyDeletion
{
    /**
     * @param  LogOwnerAction  $log  writes the line of the log
     */
    public function __construct(private LogOwnerAction $log) {}

    /**
     * Clears the day of deletion, and the suspension when the deletion made it.
     *
     * @param  Family  $family  the family that stays
     */
    public function handle(Family $family): void
    {
        DB::transaction(function () use ($family): void {
            $family->forceFill([
                'delete_after' => null,
                'suspended_at' => $this->suspendedByDeletion($family) ? null : $family->suspended_at,
            ])->save();
            $this->log->handle(OwnerActionType::DeletionCancelled, $family);
        });
    }

    /**
     * Whether the suspension came with the deletion: {@see ScheduleFamilyDeletion} writes both at the same moment,
     * the day of deletion {@see ScheduleFamilyDeletion::DAYS} days later. An earlier suspension is older than that.
     *
     * @param  Family  $family  the family
     */
    private function suspendedByDeletion(Family $family): bool
    {
        return $family->suspended_at === null || $family->delete_after === null
            || $family->suspended_at->greaterThanOrEqualTo($family->delete_after->subDays(ScheduleFamilyDeletion::DAYS));
    }
}
