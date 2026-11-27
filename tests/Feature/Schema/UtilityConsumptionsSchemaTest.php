<?php

declare(strict_types=1);

use App\Enums\ConsumptionUnit;
use App\Enums\UtilityType;
use App\Models\Document;
use App\Models\Family;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use App\Support\CurrentFamily;
use Illuminate\Database\UniqueConstraintViolationException;

test('two utilities can have a consumption from the same bill', function (): void {
    $familyId = Family::factory()->create()->id;

    CurrentFamily::using($familyId, function () use ($familyId): void {
        $document = Document::factory()->create(['family_id' => $familyId]);
        $electricity = Utility::factory()->create(['family_id' => $familyId, 'type' => UtilityType::Electricity]);
        $gas = Utility::factory()->create(['family_id' => $familyId, 'type' => UtilityType::Gas]);

        UtilityConsumption::factory()->create(['family_id' => $familyId, 'utility_id' => $electricity->id, 'document_id' => $document->id, 'quantity' => 201, 'amount' => 96.25]);
        UtilityConsumption::factory()->create(['family_id' => $familyId, 'utility_id' => $gas->id, 'document_id' => $document->id, 'quantity' => 48, 'unit' => ConsumptionUnit::Smc, 'amount' => 69.40]);

        expect($document->consumptions)->toHaveCount(2);
    });
});

test('the same utility cannot have two consumptions from the same bill', function (): void {
    $familyId = Family::factory()->create()->id;

    CurrentFamily::using($familyId, function () use ($familyId): void {
        $document = Document::factory()->create(['family_id' => $familyId]);
        $utility = Utility::factory()->create(['family_id' => $familyId]);

        UtilityConsumption::factory()->create(['family_id' => $familyId, 'utility_id' => $utility->id, 'document_id' => $document->id]);

        expect(fn () => UtilityConsumption::factory()->create(['family_id' => $familyId, 'utility_id' => $utility->id, 'document_id' => $document->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31']))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});
