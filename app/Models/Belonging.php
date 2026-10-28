<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BelongingStatus;
use App\Models\Concerns\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\BelongingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchased belonging: appliance, furniture, electronics (`belongings`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property BelongingStatus $status
 * @property string|null $brand
 * @property string|null $model
 * @property int|null $home_id
 * @property int|null $organization_id
 * @property CarbonImmutable|null $purchase_date
 * @property numeric|null $purchase_price
 * @property CarbonImmutable|null $warranty_expiry_date
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Family $family
 * @property-read Home|null $home
 * @property-read Collection<int, MaintenanceRecord> $maintenanceRecords
 * @property-read int|null $maintenance_records_count
 * @property-read Organization|null $organization
 *
 * @method static BelongingFactory factory($count = null, $state = [])
 * @method static Builder<static>|Belonging newModelQuery()
 * @method static Builder<static>|Belonging newQuery()
 * @method static Builder<static>|Belonging query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'status', 'brand', 'model', 'home_id', 'organization_id', 'purchase_date', 'purchase_price', 'warranty_expiry_date', 'notes'])]
final class Belonging extends FamilyModel
{
    use HasDocuments;

    /** @use HasFactory<BelongingFactory> */
    use HasFactory;

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
     * The linked organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The maintenance jobs on the belonging.
     *
     * @return HasMany<MaintenanceRecord, $this>
     */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BelongingStatus::class,
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
            'warranty_expiry_date' => 'date',
        ];
    }

    /**
     * The name among a document's links: the name.
     */
    public function label(): string
    {
        return $this->name;
    }
}
