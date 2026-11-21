<?php

declare(strict_types=1);

use App\Actions\Mail\RecordMailFilterCall;
use App\Enums\ConsumptionOutcome;
use App\Enums\ReadingPurpose;
use App\Models\AiReading;
use App\Models\Family;
use App\Models\File;
use App\Support\CurrentFamily;
use App\Support\Reading\ReadingCost;

test('the call is written with its file and returned as the row', function (): void {
    $family = Family::factory()->create();
    $file = File::factory()->for($family)->create();

    $row = CurrentFamily::using($family->id, fn (): AiReading => (new RecordMailFilterCall)->handle('gemini', 'gemini-3.8-flash', 300, 40, 0, ReadingPurpose::Consumption, null, $file->id));

    expect($row->exists)->toBeTrue()
        ->and($row->file_id)->toBe($file->id)
        ->and($row->purpose)->toBe(ReadingPurpose::Consumption)
        ->and($row->family_id)->toBe($family->id);
});

test('a call without a file has no file', function (): void {
    $family = Family::factory()->create();

    $row = CurrentFamily::using($family->id, fn (): AiReading => (new RecordMailFilterCall)->handle('gemini', 'gemini-3.8-flash', 300, 40, 0));

    expect($row->file_id)->toBeNull()
        ->and($row->purpose)->toBe(ReadingPurpose::MailFilter);
});

test('the average cost of a purpose without readings is the default', function (): void {
    expect(ReadingCost::averageFor(ReadingPurpose::Consumption))->toBe(0.008);
});

test('the average cost of a purpose is that of the current family and of that purpose only', function (): void {
    $family = Family::factory()->create();
    AiReading::factory()->for($family)->create(['purpose' => ReadingPurpose::Consumption, 'cost_usd' => '0.004000']);
    AiReading::factory()->for($family)->create(['purpose' => ReadingPurpose::Consumption, 'cost_usd' => '0.006000']);
    AiReading::factory()->for($family)->create(['purpose' => ReadingPurpose::Document, 'cost_usd' => '0.100000']);
    AiReading::factory()->for(Family::factory()->create())->create(['purpose' => ReadingPurpose::Consumption, 'cost_usd' => '0.900000']);

    expect(CurrentFamily::using($family->id, fn (): float => ReadingCost::averageFor(ReadingPurpose::Consumption)))->toEqualWithDelta(0.005, 1e-9);
});

test('the average cost without a current family is the default and does not throw', function (): void {
    AiReading::factory()->for(Family::factory()->create())->create(['purpose' => ReadingPurpose::Consumption, 'cost_usd' => '0.900000']);

    expect(ReadingCost::averageFor(ReadingPurpose::Consumption))->toBe(0.008);
});

test('the outcome of a reading is set once, from null', function (): void {
    $family = Family::factory()->create();
    $reading = AiReading::factory()->for($family)->create();

    CurrentFamily::using($family->id, function () use ($reading): void {
        $reading->update(['outcome' => ConsumptionOutcome::Saved]);
        expect($reading->fresh()?->outcome)->toBe(ConsumptionOutcome::Saved);

        expect(fn () => $reading->update(['outcome' => ConsumptionOutcome::NotABill]))->toThrow(LogicException::class);
    });
});

test('cost and tokens of a reading still cannot change', function (): void {
    $family = Family::factory()->create();
    $reading = AiReading::factory()->for($family)->create();

    expect(fn () => $reading->update(['cost_usd' => '1']))->toThrow(LogicException::class)
        ->and(fn () => $reading->update(['input_tokens' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $reading->update(['outcome' => ConsumptionOutcome::Saved, 'input_tokens' => 1]))->toThrow(LogicException::class);
});
