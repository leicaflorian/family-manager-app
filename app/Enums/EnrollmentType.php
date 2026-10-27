<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The type of an enrollment (`enrollments.type`).
 * The values are those of Directus.
 */
enum EnrollmentType: string
{
    use HasLabel;

    case School = 'school';
    case Sport = 'sport';
    case Course = 'course';
    case Other = 'other';
}
