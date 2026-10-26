<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoManutenzione;
use App\Models\Concerns\HaDocumenti;
use Carbon\CarbonImmutable;
use Database\Factories\MaintenanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una manutenzione di una casa, un bene o un veicolo (`maintenance_records`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property CarbonImmutable $date
 * @property TipoManutenzione|null $type
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
final class MaintenanceRecord extends DatoDellaFamiglia
{
    use HaDocumenti;

    /** @use HasFactory<MaintenanceRecordFactory> */
    use HasFactory;

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
     * La casa collegata.
     *
     * @return BelongsTo<Home, $this>
     */
    public function home(): BelongsTo
    {
        return $this->belongsTo(Home::class);
    }

    /**
     * Il bene collegato.
     *
     * @return BelongsTo<Belonging, $this>
     */
    public function belonging(): BelongsTo
    {
        return $this->belongsTo(Belonging::class);
    }

    /**
     * Il veicolo collegato.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * La spesa pagata collegata.
     *
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * I tipi delle colonne.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TipoManutenzione::class,
            'date' => 'date',
        ];
    }
}
