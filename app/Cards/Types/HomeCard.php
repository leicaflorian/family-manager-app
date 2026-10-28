<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\HomeStatus;
use App\Models\Home;
use Illuminate\Database\Eloquent\Model;

/**
 * A home: the house where the family lives, or a second home.
 */
final class HomeCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::Home;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Residence');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('The home where you live, or a second home');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Home::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('name', __('Name'), FieldKind::Text, required: true),
            new CardField('address', __('Address'), FieldKind::Text),
            new CardField('city', __('City'), FieldKind::Text),
            new CardField('country', __('Country'), FieldKind::Text),
            new CardField('is_owned', __('Owned'), FieldKind::Toggle),
            $this->status(HomeStatus::class),
            $this->notes(),
        ];
    }

    /**
     * The address and the city, for example «Via Roma 14, Bologna».
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        $home = $this->card($card, Home::class);

        return $this->join(', ', $home->address, $home->city);
    }

    /** {@inheritDoc} */
    public function related(): array
    {
        return ['utilities', 'belongings', 'maintenanceRecords', 'insurancePolicies'];
    }
}
