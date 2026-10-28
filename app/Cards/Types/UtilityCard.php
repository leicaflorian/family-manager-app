<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\UtilityStatus;
use App\Enums\UtilityType;
use App\Models\Utility;
use Illuminate\Database\Eloquent\Model;

/**
 * A utility of a home: electricity, gas, water, internet, waste tax.
 */
final class UtilityCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::Home;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Utility');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('Electricity, gas, water, internet, waste tax');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Utility::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('type', __('Type'), FieldKind::Dropdown, required: true, enum: UtilityType::class),
            new CardField('organization_id', __('Supplier'), FieldKind::Organization),
            new CardField('home_id', __('Residence'), FieldKind::Card, target: 'home'),
            new CardField('person_id', __('Account holder'), FieldKind::Person),
            new CardField('customer_code', __('Customer code'), FieldKind::Text),
            new CardField(
                'supply_point_code',
                __('POD code'),
                FieldKind::Text,
                showWhen: ['type' => ['electricity', 'gas']],
                labelWhen: ['type' => ['electricity' => __('POD code'), 'gas' => __('PDR code')]],
            ),
            new CardField('activation_date', __('Activation'), FieldKind::Date),
            $this->status(UtilityStatus::class),
            $this->notes(),
        ];
    }

    /**
     * The supplier, for example «Edison».
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        return $this->card($card, Utility::class)->organization->name ?? '';
    }

    /** {@inheritDoc} */
    public function related(): array
    {
        return ['home'];
    }

    /** {@inheritDoc} */
    public function subtitleRelations(): array
    {
        return ['organization'];
    }
}
