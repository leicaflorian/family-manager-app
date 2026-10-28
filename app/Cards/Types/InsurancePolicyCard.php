<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardDate;
use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\InsurancePolicyStatus;
use App\Enums\InsurancePolicyType;
use App\Models\InsurancePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * An insurance policy: car, home, life, health. Its type decides the card it
 * covers: the vehicle, the home or the employment.
 */
final class InsurancePolicyCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::Insurance;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Policy');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('Car, home, life, health');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return InsurancePolicy::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('title', __('Title'), FieldKind::Text),
            new CardField('type', __('Type'), FieldKind::Dropdown, required: true, enum: InsurancePolicyType::class),
            new CardField('policy_number', __('Policy number'), FieldKind::Text),
            new CardField('vehicle_id', __('Vehicle'), FieldKind::Card, target: 'vehicle', showWhen: ['type' => ['car']]),
            new CardField('home_id', __('Residence'), FieldKind::Card, target: 'home', showWhen: ['type' => ['home']]),
            new CardField('employment_id', __('Employment'), FieldKind::Card, target: 'employment', showWhen: ['type' => ['health', 'accident', 'fund']]),
            new CardField('organization_id', __('Insurer'), FieldKind::Organization),
            new CardField('person_id', __('Policyholder'), FieldKind::Person),
            new CardField('start_date', __('Start'), FieldKind::Date),
            new CardField('expiry_date', __('Expiry'), FieldKind::Date),
            $this->status(InsurancePolicyStatus::class),
            $this->notes(),
        ];
    }

    /** {@inheritDoc} */
    public function dates(): array
    {
        return [
            new CardDate('expiry_date', __('Expiry'), expires: true),
            new CardDate('start_date', __('Start'), expires: false),
        ];
    }

    /**
     * The policy number, for example «40/123456».
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        return $this->card($card, InsurancePolicy::class)->policy_number ?? '';
    }

    /** {@inheritDoc} */
    public function related(): array
    {
        return ['vehicle', 'home', 'employment'];
    }
}
