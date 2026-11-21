<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\OpenToMembers;

/**
 * The permissions on consumptions: a `member` adds and corrects them too.
 * Deleting stays with an `admin`.
 */
final class UtilityConsumptionPolicy extends FamilyPolicy
{
    use OpenToMembers;
}
