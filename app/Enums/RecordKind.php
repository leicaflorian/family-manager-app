<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The cards a file can create when they are missing, in the order they are created: the organization first
 * (the others take it), the vehicle before the policy, the intervention last (it takes the expense).
 */
enum RecordKind: string
{
    use HasLabel;

    case Organization = 'organization';
    case Vehicle = 'vehicle';
    case Utility = 'utility';
    case Policy = 'policy';
    case Belonging = 'belonging';
    case Maintenance = 'maintenance';

    /**
     * The type of the morph map of the card this kind creates, or null for an organization (not a card).
     */
    public function cardType(): ?string
    {
        return match ($this) {
            self::Organization => null,
            self::Vehicle => 'vehicle',
            self::Utility => 'utility',
            self::Policy => 'insurance_policy',
            self::Belonging => 'belonging',
            self::Maintenance => 'maintenance_record',
        };
    }
}
