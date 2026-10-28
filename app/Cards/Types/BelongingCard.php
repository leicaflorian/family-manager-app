<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardDate;
use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\BelongingStatus;
use App\Models\Belonging;
use Illuminate\Database\Eloquent\Model;

/**
 * A belonging under warranty: an appliance, a phone, a computer.
 */
final class BelongingCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::Home;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Item under warranty');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('An appliance, a phone, a computer');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Belonging::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('name', __('Name'), FieldKind::Text, required: true),
            new CardField('brand', __('Brand'), FieldKind::Text),
            new CardField('model', __('Model'), FieldKind::Text),
            new CardField('purchase_date', __('Purchase date'), FieldKind::Date),
            new CardField('purchase_price', __('Price'), FieldKind::Currency),
            new CardField('warranty_expiry_date', __('Warranty until'), FieldKind::Date),
            new CardField('organization_id', __('Shop'), FieldKind::Organization),
            new CardField('home_id', __('Residence'), FieldKind::Card, target: 'home'),
            $this->status(BelongingStatus::class),
            $this->notes(),
        ];
    }

    /** {@inheritDoc} */
    public function dates(): array
    {
        return [
            // An ended warranty is shown in red but there is nothing to do about it.
            new CardDate('warranty_expiry_date', __('End of warranty'), expires: true, overdueWhile: static fn (): bool => false),
            new CardDate('purchase_date', __('Purchase date'), expires: false),
        ];
    }

    /**
     * The brand and the model, for example «Samsung QE55Q60».
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        $belonging = $this->card($card, Belonging::class);

        return $this->join(' ', $belonging->brand, $belonging->model);
    }

    /** {@inheritDoc} */
    public function related(): array
    {
        return ['home', 'maintenanceRecords'];
    }
}
