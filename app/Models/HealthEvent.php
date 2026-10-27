<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HealthEventType;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\HealthEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visit, a test, a vaccine, a prescription (`health_events`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property HealthEventType $type
 * @property int $person_id
 * @property int|null $organization_id
 * @property CarbonImmutable $date
 * @property CarbonImmutable|null $next_checkup_date
 * @property int|null $expense_id
 * @property string|null $outcome
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Expense|null $expense
 * @property-read Family $family
 * @property-read Organization|null $organization
 * @property-read Person $person
 *
 * @method static HealthEventFactory factory($count = null, $state = [])
 * @method static Builder<static>|HealthEvent newModelQuery()
 * @method static Builder<static>|HealthEvent newQuery()
 * @method static Builder<static>|HealthEvent query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['title', 'type', 'person_id', 'organization_id', 'date', 'next_checkup_date', 'expense_id', 'outcome', 'notes'])]
final class HealthEvent extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<HealthEventFactory> */
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
     * L'ente collegato.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The linked paid expense.
     *
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HealthEventType::class,
            'date' => 'date',
            'next_checkup_date' => 'date',
        ];
    }
}
