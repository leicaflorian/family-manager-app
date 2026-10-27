<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An enrollment at school, sport or courses (`enrollments`).
 *
 * @property int $id
 * @property int $family_id
 * @property int $person_id
 * @property int|null $organization_id
 * @property EnrollmentType $type
 * @property EnrollmentStatus $status
 * @property string|null $school_year
 * @property string|null $class_name
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Family $family
 * @property-read Organization|null $organization
 * @property-read Person $person
 *
 * @method static EnrollmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Enrollment newModelQuery()
 * @method static Builder<static>|Enrollment newQuery()
 * @method static Builder<static>|Enrollment query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['person_id', 'organization_id', 'type', 'status', 'school_year', 'class_name', 'notes'])]
final class Enrollment extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    /**
     * The person it refers to.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The linked organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EnrollmentType::class,
            'status' => EnrollmentStatus::class,
        ];
    }

    /**
     * The name among a document's links: the person, the type and the school, for example «Sofia - Dance - ASD Danza».
     */
    public function label(): string
    {
        return self::joinLabel($this->person->first_name, $this->type->label(), $this->organization?->name);
    }

    /**
     * The relations the name reads.
     *
     * @return list<string>
     */
    public static function labelRelations(): array
    {
        return ['person', 'organization'];
    }
}
