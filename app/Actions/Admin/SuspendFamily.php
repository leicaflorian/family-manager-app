<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\OwnerActionType;
use App\Models\Family;
use Illuminate\Support\Facades\DB;

/**
 * Suspends a family: its accounts cannot sign in and the ones inside are sent out at the next request.
 */
final readonly class SuspendFamily
{
    /**
     * @param  LogOwnerAction  $log  writes the line of the log
     */
    public function __construct(private LogOwnerAction $log) {}

    /**
     * Suspends the family, keeping the moment it was first suspended.
     *
     * @param  Family  $family  the family to suspend
     */
    public function handle(Family $family): void
    {
        DB::transaction(function () use ($family): void {
            $family->forceFill(['suspended_at' => $family->suspended_at ?? now()])->save();
            $this->log->handle(OwnerActionType::Suspended, $family);
        });
    }
}
