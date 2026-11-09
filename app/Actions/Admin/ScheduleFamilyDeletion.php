<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\OwnerActionType;
use App\Models\Family;
use Illuminate\Support\Facades\DB;

/**
 * Puts a family on the way out: suspended now, deleted by `model:prune` after 30 days unless the owner cancels.
 */
final readonly class ScheduleFamilyDeletion
{
    /** How many days the family waits before it goes. */
    public const int DAYS = 30;

    /**
     * @param  LogOwnerAction  $log  writes the line of the log
     */
    public function __construct(private LogOwnerAction $log) {}

    /**
     * Suspends the family if it is not yet and sets the day of deletion.
     *
     * @param  Family  $family  the family to delete
     */
    public function handle(Family $family): void
    {
        DB::transaction(function () use ($family): void {
            $family->forceFill([
                'suspended_at' => $family->suspended_at ?? now(),
                'delete_after' => now()->addDays(self::DAYS),
            ])->save();
            $this->log->handle(OwnerActionType::DeletionScheduled, $family);
        });
    }
}
