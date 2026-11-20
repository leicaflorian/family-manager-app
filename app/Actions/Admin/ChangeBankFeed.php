<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\OwnerActionType;
use App\Models\Family;
use Illuminate\Support\Facades\DB;

/**
 * Turns the link to the bank on or off for one family; the log gets a line only when the value changes.
 */
final readonly class ChangeBankFeed
{
    /**
     * @param  LogOwnerAction  $log  writes the line of the log
     */
    public function __construct(private LogOwnerAction $log) {}

    /**
     * Saves the switch and writes the log in one transaction.
     *
     * @param  Family  $family  the family
     * @param  bool  $enabled  whether the family can link its banks
     */
    public function handle(Family $family, bool $enabled): void
    {
        if ((bool) $family->bank_feed_enabled === $enabled) {
            return;
        }

        DB::transaction(function () use ($family, $enabled): void {
            $family->forceFill(['bank_feed_enabled' => $enabled])->save();
            $this->log->handle(OwnerActionType::BankFeedChanged, $family);
        });
    }
}
