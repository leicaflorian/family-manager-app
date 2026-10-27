<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatoIscrizione;
use App\Enums\TipoIscrizione;
use App\Models\Concerns\HaDocumenti;
use Carbon\CarbonImmutable;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un'iscrizione a scuola, sport o corsi (`enrollments`).
 *
 * @property int $id
 * @property int $family_id
 * @property int $person_id
 * @property int|null $organization_id
 * @property TipoIscrizione $type
 * @property StatoIscrizione $status
 * @property string|null $school_year
 * @property string|null $class_name
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read \App\Models\Family $family
 * @property-read \App\Models\Organization|null $organization
 * @property-read \App\Models\Person $person
 * @method static \Database\Factories\EnrollmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Enrollment newModelQuery()
 * @method static Builder<static>|Enrollment newQuery()
 * @method static Builder<static>|Enrollment query()
 * @mixin \Eloquent
 */
#[Fillable(['person_id', 'organization_id', 'type', 'status', 'school_year', 'class_name', 'notes'])]
final class Enrollment extends DatoDellaFamiglia
{
    use HaDocumenti;

    /** @use HasFactory<EnrollmentFactory> */
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
            'type' => TipoIscrizione::class,
            'status' => StatoIscrizione::class,
        ];
    }
}
