<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;

/**
 * An enrollment of a person: school, sport, a course.
 */
final class EnrollmentCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::School;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Enrollment');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('School, sport, a course');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Enrollment::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('person_id', __('Person'), FieldKind::Person, required: true),
            new CardField('organization_id', __('Organization'), FieldKind::Organization),
            new CardField('type', __('Type'), FieldKind::Choice, required: true, enum: EnrollmentType::class),
            new CardField('school_year', __('School year'), FieldKind::Text),
            new CardField('class_name', __('Class'), FieldKind::Text),
            $this->status(EnrollmentStatus::class),
            $this->notes(),
        ];
    }

    /**
     * The school year, for example «2026/2027».
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        return $this->card($card, Enrollment::class)->school_year ?? '';
    }
}
