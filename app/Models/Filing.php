<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatoPratica;
use App\Enums\TipoPratica;
use App\Models\Concerns\HaDocumenti;
use Carbon\CarbonImmutable;
use Database\Factories\FilingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una pratica: 730, ISEE, bonus, congedi, pratiche del comune (`filings`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property TipoPratica $type
 * @property StatoPratica $status
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
 * @property-read Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read \App\Models\Family $family
 * @property-read \App\Models\Organization|null $organization
 * @property-read \App\Models\Person|null $person
 * @method static \Database\Factories\FilingFactory factory($count = null, $state = [])
 * @method static Builder<static>|Filing newModelQuery()
 * @method static Builder<static>|Filing newQuery()
 * @method static Builder<static>|Filing query()
 * @mixin \Eloquent
 */
#[Fillable(['title', 'type', 'status', 'reference_year', 'person_id', 'organization_id', 'submission_date', 'due_date', 'notes'])]
final class Filing extends DatoDellaFamiglia
{
    use HaDocumenti;

    /** @use HasFactory<FilingFactory> */
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
            'type' => TipoPratica::class,
            'status' => StatoPratica::class,
            'reference_year' => 'integer',
            'submission_date' => 'date',
            'due_date' => 'date',
        ];
    }
}
