<?php

declare(strict_types=1);

namespace App\Actions\Consumption;

use App\Enums\ConsumptionSource;
use App\Enums\ConsumptionUnit;
use App\Enums\UtilityType;
use App\Models\Document;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use InvalidArgumentException;

/**
 * The one place that writes a row of `utility_consumptions`. It works in the current family, which must be the
 * utility's. The same bill read twice (by mail and by upload, or by the archive reading and then by *Importa*)
 * ends in one row.
 */
final class SaveConsumption
{
    /**
     * Saves the consumption of a utility.
     *
     * With a row it updates that one. Without, it looks for the row of the document, then for the row of the same
     * utility with the same period, and creates one only when neither exists. A reading never touches the figures of a
     * row written by hand: it gets that row back, only linked to the bill when the row has none, so that the bill is
     * no longer offered for reading. A bill carrying electricity and gas has one row per utility, each linked to it.
     *
     * @param  Utility  $utility  the utility the bill belongs to
     * @param  ConsumptionValues  $values  the figures to save
     * @param  ConsumptionSource  $source  where they come from
     * @param  Document|null  $document  the bill they were read from
     * @param  UtilityConsumption|null  $row  the row being corrected, if any
     * @return UtilityConsumption the saved row
     *
     * @throws InvalidArgumentException when the type of the utility has no unit (internet, phone…)
     */
    public function handle(Utility $utility, ConsumptionValues $values, ConsumptionSource $source, ?Document $document = null, ?UtilityConsumption $row = null): UtilityConsumption
    {
        $unit = ConsumptionUnit::forUtility($utility->type)
            ?? throw new InvalidArgumentException(sprintf('A %s utility has no consumption unit.', $utility->type->value));

        $row ??= $this->existing($utility, $values, $document);

        if ($row instanceof UtilityConsumption && $source === ConsumptionSource::Reading && $row->source === ConsumptionSource::Manual) {
            if ($row->document_id === null && $this->isFree($document, $utility, $row)) {
                $row->document_id = $document->id;
                $row->save();
            }

            return $row;
        }

        $row ??= new UtilityConsumption;
        $row->utility_id = $utility->id;
        $row->document_id = $this->isFree($document, $utility, $row) ? $document->id : $row->document_id;
        $row->period_start = $values->periodStart;
        $row->period_end = $values->periodEnd;
        $row->quantity = $values->quantity;
        $row->unit = $unit;
        $row->amount = $values->amount;
        // Water bills rebill and refund estimated cubic metres, so no line is the consumption alone.
        $row->energy_amount = $utility->type === UtilityType::Water ? null : $values->energyAmount;
        $row->quantity_f1 = $values->f1;
        $row->quantity_f2 = $values->f2;
        $row->quantity_f3 = $values->f3;
        $row->is_estimated = $values->isEstimated;
        $row->has_adjustments = $values->hasAdjustments;
        $row->source = $source;
        $row->save();

        return $row;
    }

    /**
     * The row this bill already has: the one of the document for this utility, otherwise the one of the utility with the same period.
     *
     * @param  Utility  $utility  the utility of the bill
     * @param  ConsumptionValues  $values  the figures, for the period
     * @param  Document|null  $document  the bill
     */
    private function existing(Utility $utility, ConsumptionValues $values, ?Document $document): ?UtilityConsumption
    {
        if ($document instanceof Document) {
            $byDocument = UtilityConsumption::query()->where('utility_id', $utility->id)->where('document_id', $document->id)->first();

            if ($byDocument instanceof UtilityConsumption) {
                return $byDocument;
            }
        }

        return UtilityConsumption::query()
            ->where('utility_id', $utility->id)
            ->whereDate('period_start', $values->periodStart->toDateString())
            ->whereDate('period_end', $values->periodEnd->toDateString())
            ->first();
    }

    /**
     * Whether the bill can be linked to the row: there is a bill, and no other row of the same utility holds it
     * (`document_id` and `utility_id` are unique together: a bill of electricity and gas has a row for each).
     *
     * @param  Document|null  $document  the bill
     * @param  Utility  $utility  the utility of the row
     * @param  UtilityConsumption  $row  the row to link it to
     *
     * @phpstan-assert-if-true Document $document
     */
    private function isFree(?Document $document, Utility $utility, UtilityConsumption $row): bool
    {
        return $document instanceof Document
            && ! UtilityConsumption::query()
                ->where('document_id', $document->id)
                ->where('utility_id', $utility->id)
                ->when($row->exists, fn ($query) => $query->whereKeyNot($row->id))
                ->exists();
    }
}
