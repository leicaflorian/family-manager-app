<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The cards a file can create when they are missing, in the order they are created: the organization first
 * (the others take it), the vehicle before the policy, the intervention after the others (it takes the expense).
 * The five card kinds the reading fills have their own case; any other card of `CardTypes`, proposed by the card
 * of the document category, is a `Card` whose type is in `RecordDraftData::$cardType`.
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
    case Card = 'card';

    /**
     * The kind of the box of a card type: its own case for the five the reading fills, otherwise `Card`.
     *
     * @param  string  $type  the type of the morph map, for example `home`
     */
    public static function forCardType(string $type): self
    {
        return match ($type) {
            'vehicle' => self::Vehicle,
            'utility' => self::Utility,
            'insurance_policy' => self::Policy,
            'belonging' => self::Belonging,
            'maintenance_record' => self::Maintenance,
            default => self::Card,
        };
    }

    /**
     * The type of the morph map of the card this kind creates, or null for an organization (not a card) and for a
     * `Card`, whose type is the box's.
     */
    public function cardType(): ?string
    {
        return match ($this) {
            self::Organization, self::Card => null,
            self::Vehicle => 'vehicle',
            self::Utility => 'utility',
            self::Policy => 'insurance_policy',
            self::Belonging => 'belonging',
            self::Maintenance => 'maintenance_record',
        };
    }
}
