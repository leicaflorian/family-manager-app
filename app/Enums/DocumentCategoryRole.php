<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The fixed role of a document category (`document_categories.role`): what the app means by it, whatever the
 * family calls or moves it. A family has at most one category for each role.
 */
enum DocumentCategoryRole: string
{
    use HasLabel;

    /** The registration document of a vehicle: a vehicle expects a document in it (or under it). */
    case VehicleRegistration = 'vehicle_registration';
}
