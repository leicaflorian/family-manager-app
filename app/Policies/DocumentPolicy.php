<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\OpenToMembers;

/**
 * The permissions on documents: a `member` uploads and edits them too.
 * Deleting them stays with an `admin`.
 */
final class DocumentPolicy extends FamilyPolicy
{
    use OpenToMembers;
}
