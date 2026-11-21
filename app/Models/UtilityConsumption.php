<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsumptionSource;
use App\Enums\ConsumptionUnit;
use Carbon\CarbonImmutable;
use Database\Factories\UtilityConsumptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The consumption of a utility over a period, read from a bill or written by hand (`utility_consumptions`).
 *
 * @property int $id
 * @property int $family_id
 * @property int $utility_id
 * @property int|null $document_id
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property numeric $quantity In the unit of the utility: kWh, Smc or m3
 * @property ConsumptionUnit $unit
 * @property numeric|null $amount The total of the bill
 * @property numeric|null $energy_amount The energy part of the bill
 * @property numeric|null $quantity_f1
 * @property numeric|null $quantity_f2
 * @property numeric|null $quantity_f3
 * @property bool $is_estimated
 * @property bool $has_adjustments
 * @property ConsumptionSource $source reading or manual
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Document|null $document
 * @property-read Family $family
 * @property-read Utility $utility
 *
 * @method static UtilityConsumptionFactory factory($count = null, $state = [])
 * @method static Builder<static>|UtilityConsumption newModelQuery()
 * @method static Builder<static>|UtilityConsumption newQuery()
 * @method static Builder<static>|UtilityConsumption query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['utility_id', 'document_id', 'period_start', 'period_end', 'quantity', 'unit', 'amount', 'energy_amount', 'quantity_f1', 'quantity_f2', 'quantity_f3', 'is_estimated', 'has_adjustments', 'source'])]
final class UtilityConsumption extends FamilyModel
{
    /** @use HasFactory<UtilityConsumptionFactory> */
    use HasFactory;

    /**
     * The utility it measures.
     *
     * @return BelongsTo<Utility, $this>
     */
    public function utility(): BelongsTo
    {
        return $this->belongsTo(Utility::class);
    }

    /**
     * The bill it comes from, if it still exists.
     *
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'quantity' => 'decimal:3',
            'unit' => ConsumptionUnit::class,
            'amount' => 'decimal:2',
            'energy_amount' => 'decimal:2',
            'quantity_f1' => 'decimal:3',
            'quantity_f2' => 'decimal:3',
            'quantity_f3' => 'decimal:3',
            'is_estimated' => 'boolean',
            'has_adjustments' => 'boolean',
            'source' => ConsumptionSource::class,
        ];
    }
}
