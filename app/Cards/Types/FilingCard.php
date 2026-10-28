<?php

declare(strict_types=1);

namespace App\Cards\Types;

use App\Cards\CardDate;
use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\FilingStatus;
use App\Enums\FilingType;
use App\Models\Filing;
use Illuminate\Database\Eloquent\Model;

/**
 * A filing with an office: tax return, ISEE, child benefit, residence.
 */
final class FilingCard extends CardType
{
    /** {@inheritDoc} */
    public function area(): Area
    {
        return Area::Paperwork;
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('Filing');
    }

    /** {@inheritDoc} */
    public function description(): string
    {
        return __('Tax return, ISEE, child benefit, residence');
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Filing::class;
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            new CardField('title', __('Title'), FieldKind::Text, required: true),
            new CardField('type', __('Type'), FieldKind::Dropdown, required: true, enum: FilingType::class),
            new CardField('reference_year', __('Year'), FieldKind::Number),
            new CardField('person_id', __('Applicant'), FieldKind::Person),
            new CardField('organization_id', __('Organization'), FieldKind::Organization),
            new CardField('submission_date', __('Submission date'), FieldKind::Date),
            new CardField('due_date', __('Due date'), FieldKind::Date),
            $this->status(FilingStatus::class),
            $this->notes(),
        ];
    }

    /** {@inheritDoc} */
    public function dates(): array
    {
        return [
            // A filing already submitted or approved has nothing left to do, whatever its due date.
            new CardDate('due_date', __('Due date'), expires: true, overdueWhile: fn (Model $card): bool => ! in_array($this->card($card, Filing::class)->status, [FilingStatus::Submitted, FilingStatus::Approved], true)),
            new CardDate('submission_date', __('Submission date'), expires: false),
        ];
    }

    /**
     * The office that handles it, for example «CAF CGIL Bologna».
     *
     * {@inheritDoc}
     */
    public function subtitle(Model $card): string
    {
        return $this->card($card, Filing::class)->organization->name ?? '';
    }

    /** {@inheritDoc} */
    public function subtitleRelations(): array
    {
        return ['organization'];
    }
}
