<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * What the owner did to a family, as written in the log of the panel.
 */
enum OwnerActionType: string
{
    use HasLabel;

    case Suspended = 'suspended';
    case Reactivated = 'reactivated';
    case DeletionScheduled = 'deletion_scheduled';
    case DeletionCancelled = 'deletion_cancelled';
    case VisitStarted = 'visit_started';
    case VisitEnded = 'visit_ended';
    case ReadingLimitChanged = 'reading_limit_changed';
}
