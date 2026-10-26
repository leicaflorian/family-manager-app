<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatoLavoro;
use App\Models\Concerns\HaDocumenti;
use Carbon\CarbonImmutable;
use Database\Factories\EmploymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un lavoro di una persona (`employments`).
 *
 * @property int $id
 * @property int $family_id
 * @property int $person_id
 * @property int|null $organization_id
 * @property string|null $job_title
 * @property StatoLavoro $status
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $end_date
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
 * @method static EmploymentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Employment newModelQuery()
 * @method static Builder<static>|Employment newQuery()
 * @method static Builder<static>|Employment query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['person_id', 'organization_id', 'job_title', 'status', 'start_date', 'end_date', 'notes'])]
final class Employment extends DatoDellaFamiglia
{
    use HaDocumenti;

    /** @use HasFactory<EmploymentFactory> */
    use HasFactory;

    /**
     * La persona a cui si riferisce.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * L'ente collegato.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatoLavoro::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
