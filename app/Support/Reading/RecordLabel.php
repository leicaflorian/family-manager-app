<?php

declare(strict_types=1);

namespace App\Support\Reading;

use App\Enums\RecordKind;
use App\Models\Belonging;
use App\Models\InsurancePolicy;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Utility;
use App\Models\Vehicle;

/**
 * How a card the reading found, or the person picked, is named in *What I also do*: `Luce (IT001E…)`,
 * `Panda (GH482TK)`, the number of a policy (its title without one), `Tagliando (12/10/2026)`.
 */
final class RecordLabel
{
    /**
     * The model class of the cards of a kind.
     *
     * @param  RecordKind  $kind  which card
     * @return class-string<Organization|Vehicle|Utility|InsurancePolicy|Belonging|MaintenanceRecord>
     */
    public static function model(RecordKind $kind): string
    {
        return match ($kind) {
            RecordKind::Organization => Organization::class,
            RecordKind::Vehicle => Vehicle::class,
            RecordKind::Utility => Utility::class,
            RecordKind::Policy => InsurancePolicy::class,
            RecordKind::Belonging => Belonging::class,
            RecordKind::Maintenance => MaintenanceRecord::class,
        };
    }

    /**
     * The name of a card.
     *
     * @param  Organization|Vehicle|Utility|InsurancePolicy|Belonging|MaintenanceRecord  $card  the card
     */
    public static function of(Organization|Vehicle|Utility|InsurancePolicy|Belonging|MaintenanceRecord $card): string
    {
        return match (true) {
            $card instanceof Utility => ucfirst($card->type->label()).($card->supply_point_code === null ? '' : ' ('.$card->supply_point_code.')'),
            $card instanceof Vehicle => $card->name.($card->plate_number === null ? '' : ' ('.$card->plate_number.')'),
            $card instanceof InsurancePolicy => $card->policy_number ?? $card->title ?? (string) $card->id,
            $card instanceof MaintenanceRecord => $card->title.' ('.$card->date->format('d/m/Y').')',
            $card instanceof Organization, $card instanceof Belonging => $card->name,
        };
    }
}
