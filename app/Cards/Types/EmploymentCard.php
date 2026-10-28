<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardDate;
use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\EmploymentStatus;
use App\Models\Employment;
use Illuminate\Database\Eloquent\Model;

/**
 * An employment of a person, with its employer.
 */
final class EmploymentCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::Work;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Job');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('An employment and its salary');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Employment::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('person_id', __('Person'), FieldKind::Person, required: true),
            new CardField('organization_id', __('Employer'), FieldKind::Organization),
            new CardField('job_title', __('Role'), FieldKind::Text),
            new CardField('start_date', __('Start'), FieldKind::Date),
            new CardField('end_date', __('End'), FieldKind::Date),
            $this->status(EmploymentStatus::class),
            $this->notes(),
        ];
    }

    /** {@inheritDoc} */
    public function dates(): array
    {
        return [
            new CardDate('start_date', __('Start'), expires: false),
            new CardDate('end_date', __('End'), expires: false),
        ];
    }

    /**
     * The role, for example «Clerk».
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        return $this->card($card, Employment::class)->job_title ?? '';
    }

    /** {@inheritDoc} */
    public function related(): array
    {
        return ['insurancePolicies'];
    }
}
