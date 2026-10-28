<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardDate;
use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\MaintenanceTarget;
use App\Enums\MaintenanceType;
use App\Models\MaintenanceRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * A maintenance: a job on one thing only, a home, a belonging or a vehicle.
 * The form asks first what it refers to, then that card.
 */
final class MaintenanceRecordCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::Home;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Maintenance job');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('A job: boiler, repair, car service');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return MaintenanceRecord::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('title', __('Title'), FieldKind::Text, required: true),
            new CardField('date', __('Date'), FieldKind::Date, required: true),
            new CardField('type', __('Type'), FieldKind::Dropdown, enum: MaintenanceType::class),
            new CardField('organization_id', __('Done by'), FieldKind::Organization),
            new CardField('target', __('Refers to'), FieldKind::Choice, required: true, enum: MaintenanceTarget::class, virtual: true),
            new CardField('home_id', __('Residence'), FieldKind::Card, required: true, target: 'home', showWhen: ['target' => ['home']]),
            new CardField('belonging_id', __('Item'), FieldKind::Card, required: true, target: 'belonging', showWhen: ['target' => ['belonging']]),
            new CardField('vehicle_id', __('Vehicle'), FieldKind::Card, required: true, target: 'vehicle', showWhen: ['target' => ['vehicle']]),
            $this->notes(__('Description')),
        ];
    }

    /** {@inheritDoc} */
    public function dates(): array
    {
        return [new CardDate('date', __('Date'), expires: false)];
    }

    /**
     * Who did the job, for example «Termoidraulica Rossi».
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        return $this->card($card, MaintenanceRecord::class)->organization->name ?? '';
    }

    /** {@inheritDoc} */
    public function related(): array
    {
        return ['home', 'belonging', 'vehicle'];
    }

    /**
     * That a job goes with one thing only, and which one.
     *
     * {@inheritDoc}
     */
    public function notices(Model $card): array
    {
        $record = $this->card($card, MaintenanceRecord::class);
        $thing = $record->home ?? $record->belonging ?? $record->vehicle;

        return [$thing === null
            ? __('Applies to one thing only. A job is linked to a home, a belonging or a vehicle.')
            : __('Applies to one thing only. A job is linked to a home, a belonging or a vehicle: this one to :name.', ['name' => $thing->label()])];
    }
}
