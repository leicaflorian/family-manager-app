<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MaintenanceType;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\MaintenanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A maintenance record of a home, a belonging or a vehicle (`maintenance_records`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property CarbonImmutable $date
 * @property MaintenanceType|null $type
 * @property int|null $organization_id
 * @property int|null $home_id
 * @property int|null $belonging_id
 * @property int|null $vehicle_id
 * @property int|null $expense_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Belonging|null $belonging
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Expense|null $expense
 * @property-read Family $family
 * @property-read Home|null $home
 * @property-read Organization|null $organization
 * @property-read Vehicle|null $vehicle
 *
 * @method static MaintenanceRecordFactory factory($count = null, $state = [])
 * @method static Builder<static>|MaintenanceRecord newModelQuery()
 * @method static Builder<static>|MaintenanceRecord newQuery()
 * @method static Builder<static>|MaintenanceRecord query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['title', 'date', 'type', 'organization_id', 'home_id', 'belonging_id', 'vehicle_id', 'expense_id', 'notes'])]
final class MaintenanceRecord extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<MaintenanceRecordFactory> */
    use HasFactory;

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
     * The linked home.
     *
     * @return BelongsTo<Home, $this>
     */
    public function home(): BelongsTo
    {
        return $this->belongsTo(Home::class);
    }

    /**
     * The linked belonging.
     *
     * @return BelongsTo<Belonging, $this>
     */
    public function belonging(): BelongsTo
    {
        return $this->belongsTo(Belonging::class);
    }

    /**
     * The linked vehicle.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
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
            'type' => MaintenanceType::class,
            'date' => 'date',
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
