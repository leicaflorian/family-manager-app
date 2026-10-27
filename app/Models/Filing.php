<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FilingStatus;
use App\Enums\FilingType;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\FilingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A filing: tax return, ISEE, bonuses, leave, municipal paperwork (`filings`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property FilingType $type
 * @property FilingStatus $status
 * @property int|null $reference_year
 * @property int|null $person_id
 * @property int|null $organization_id
 * @property CarbonImmutable|null $submission_date
 * @property CarbonImmutable|null $due_date
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Family $family
 * @property-read Organization|null $organization
 * @property-read Person|null $person
 *
 * @method static FilingFactory factory($count = null, $state = [])
 * @method static Builder<static>|Filing newModelQuery()
 * @method static Builder<static>|Filing newQuery()
 * @method static Builder<static>|Filing query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['title', 'type', 'status', 'reference_year', 'person_id', 'organization_id', 'submission_date', 'due_date', 'notes'])]
final class Filing extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<FilingFactory> */
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
            'type' => FilingType::class,
            'status' => FilingStatus::class,
            'reference_year' => 'integer',
            'submission_date' => 'date',
            'due_date' => 'date',
        ];
    }

    /**
     * The name among a document's links: the title.
     */
    public function label(): string
    {
        return $this->title;
    }
}
