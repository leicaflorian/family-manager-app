<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FinancialAccountStatus;
use App\Models\FinancialAccount;

/**
 * The account the family pays from when nothing says otherwise: the active one marked `is_default`.
 */
final class DefaultAccount
{
    /**
     * The id of the default account of the current family.
     *
     * @return ?int Null when the family has none.
     */
    public static function id(): ?int
    {
        $id = FinancialAccount::query()
            ->where('is_default', true)
            ->where('status', FinancialAccountStatus::Active)
            ->orderBy('id')
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
}
