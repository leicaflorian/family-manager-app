<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\OpenToMembers;

/**
 * The permissions on expenses: a `member` records payments and edits them too.
 * Deleting them stays with an `admin`.
 */
final class ExpensePolicy extends FamilyPolicy
{
    use OpenToMembers;
}
