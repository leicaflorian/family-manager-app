<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\OpenToMembers;

/**
 * The permissions on the reports sent to the owner: everyone of the family sends one, a `member` too.
 */
final class FeedbackReportPolicy extends FamilyPolicy
{
    use OpenToMembers;
}
