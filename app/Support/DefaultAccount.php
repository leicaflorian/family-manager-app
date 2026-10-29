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
        return self::model()?->id;
    }

    /**
     * The default account of the current family.
     *
     * @return ?FinancialAccount Null when the family has none.
     */
    public static function model(): ?FinancialAccount
    {
        return FinancialAccount::query()
            ->where('is_default', true)
            ->where('status', FinancialAccountStatus::Active)
            ->orderBy('id')
            ->first();
    }
}
