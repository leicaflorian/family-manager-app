<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardDate;
use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * A vehicle: a car, a motorbike, a scooter.
 */
final class VehicleCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::Vehicles;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Vehicle');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('Car, motorbike, scooter');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Vehicle::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('name', __('Name'), FieldKind::Text, required: true),
            new CardField('brand', __('Brand'), FieldKind::Text),
            new CardField('model', __('Model'), FieldKind::Text),
            new CardField('plate_number', __('Plate'), FieldKind::Text),
            new CardField('registration_year', __('Registration year'), FieldKind::Number),
            new CardField('person_id', __('Account holder'), FieldKind::Person),
            new CardField('is_owned', __('Owned'), FieldKind::Toggle),
            new CardField('road_tax_due_date', __('Road tax'), FieldKind::Date),
            new CardField('inspection_due_date', __('Inspection'), FieldKind::Date),
            $this->status(VehicleStatus::class),
            $this->notes(),
        ];
    }

    /** {@inheritDoc} */
    public function dates(): array
    {
        return [
            new CardDate('road_tax_due_date', __('Road tax'), expires: true),
            new CardDate('inspection_due_date', __('Inspection'), expires: true),
        ];
    }

    /**
     * The plate, for example «GH 482 TK».
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        return $this->card($card, Vehicle::class)->plate_number ?? '';
    }

    /** {@inheritDoc} */
    public function related(): array
    {
        return ['insurancePolicies', 'maintenanceRecords'];
    }
}
