<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\OpenToMembers;

/**
 * The permissions on incomes: a `member` records incomes and edits them too.
 * Deleting them stays with an `admin`.
 */
final class IncomePolicy extends FamilyPolicy
{
    use OpenToMembers;
}
