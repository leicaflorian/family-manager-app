<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardDate;
use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\HealthEventType;
use App\Models\HealthEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * A health event of a person: a visit, a test, a vaccine. It has no status,
 * so it is never closed.
 */
final class HealthEventCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::Health;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Health event');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('A visit, a test, a vaccine');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return HealthEvent::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('title', __('Title'), FieldKind::Text, required: true),
            new CardField('person_id', __('Person'), FieldKind::Person, required: true),
            new CardField('type', __('Type'), FieldKind::Dropdown, required: true, enum: HealthEventType::class),
            new CardField('organization_id', __('Doctor or facility'), FieldKind::Organization),
            new CardField('date', __('Date'), FieldKind::Date, required: true),
            new CardField('outcome', __('Outcome'), FieldKind::Text),
            new CardField('next_checkup_date', __('Next checkup'), FieldKind::Date),
            $this->notes(),
        ];
    }

    /** {@inheritDoc} */
    public function dates(): array
    {
        return [
            new CardDate('date', __('Date'), expires: false),
            new CardDate('next_checkup_date', __('Next checkup'), expires: true),
        ];
    }

    /**
     * The doctor or the facility, for example «Studio Ferri».
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        return $this->card($card, HealthEvent::class)->organization->name ?? '';
    }
}
