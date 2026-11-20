<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Models\Family;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\Config;

/**
 * Whether the link to the bank is on: a provider is set up and the owner turned it on for the current family.
 */
final class BankFeedAvailability
{
    /**
     * Whether the pages, the routes and the sync of the bank are available for the current family.
     */
    public static function on(): bool
    {
        if (Config::string('bank_feed.provider', '') === '') {
            return false;
        }

        $id = CurrentFamily::id();

        return $id !== null && (bool) Family::query()->whereKey($id)->value('bank_feed_enabled');
    }
}
