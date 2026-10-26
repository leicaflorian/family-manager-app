<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatoBene;
use App\Models\Concerns\HaDocumenti;
use Carbon\CarbonImmutable;
use Database\Factories\BelongingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un bene comprato: elettrodomestico, mobile, elettronica (`belongings`).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property StatoBene $status
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
final class Belonging extends DatoDellaFamiglia
{
    use HaDocumenti;

    /** @use HasFactory<BelongingFactory> */
    use HasFactory;

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
            'status' => StatoBene::class,
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
            'warranty_expiry_date' => 'date',
        ];
    }
}
