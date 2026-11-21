<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\OpenToMembers;

/**
 * The permissions on the transactions of the bank: a `member` sorts them too (links, creates, ignores).
 * Deleting stays with an `admin`.
 */
final class BankTransactionPolicy extends FamilyPolicy
{
    use OpenToMembers;
}
