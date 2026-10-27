<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The status of an enrollment (`enrollments.status`).
 * The values are those of Directus.
 */
enum EnrollmentStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Ended = 'ended';
}
