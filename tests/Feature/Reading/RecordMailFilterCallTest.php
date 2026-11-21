<?php

declare(strict_types=1);

use App\Actions\Mail\RecordMailFilterCall;
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

test('the average cost of a purpose reads the readings of every family and of that purpose only', function (): void {
    AiReading::factory()->for(Family::factory()->create())->create(['purpose' => ReadingPurpose::Consumption, 'cost_usd' => '0.004000']);
    AiReading::factory()->for(Family::factory()->create())->create(['purpose' => ReadingPurpose::Consumption, 'cost_usd' => '0.006000']);
    AiReading::factory()->for(Family::factory()->create())->create(['purpose' => ReadingPurpose::Document, 'cost_usd' => '0.100000']);

    expect(ReadingCost::averageFor(ReadingPurpose::Consumption))->toEqualWithDelta(0.005, 1e-9);
});
