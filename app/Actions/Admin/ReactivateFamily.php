<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\OwnerActionType;
use App\Models\Family;

/**
 * Gives a suspended family everything back; a deletion that was waiting is cancelled too.
 */
final readonly class ReactivateFamily
{
    /**
     * @param  LogOwnerAction  $log  writes the line of the log
     */
    public function __construct(private LogOwnerAction $log) {}

    /**
     * Clears the suspension and the date of deletion.
     *
     * @param  Family  $family  the family to reactivate
     */
    public function handle(Family $family): void
    {
        $family->forceFill(['suspended_at' => null, 'delete_after' => null])->save();
        $this->log->handle(OwnerActionType::Reactivated, $family);
    }
}
