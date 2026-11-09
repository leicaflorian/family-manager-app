<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\OwnerActionType;
use App\Models\Family;
use Illuminate\Support\Facades\DB;

/**
 * Sets the readings a month of one family; the log gets a line only when the value changes.
 */
final readonly class ChangeReadingLimit
{
    /**
     * @param  LogOwnerAction  $log  writes the line of the log
     */
    public function __construct(private LogOwnerAction $log) {}

    /**
     * Saves the limit and writes the log in one transaction.
     *
     * @param  Family  $family  the family
     * @param  int|null  $limit  readings a month; null means the limit of everyone
     */
    public function handle(Family $family, ?int $limit): void
    {
        if ($family->reading_limit === $limit) {
            return;
        }

        DB::transaction(function () use ($family, $limit): void {
            $family->forceFill(['reading_limit' => $limit])->save();
            $this->log->handle(OwnerActionType::ReadingLimitChanged, $family);
        });
    }
}
