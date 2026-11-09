<?php

declare(strict_types=1);

use App\Admin\FamilyFigures;
use App\Enums\ReadingPurpose;
use App\Models\AiReading;
use App\Models\Document;
use App\Models\Family;
use App\Models\File;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Two families with different amounts of everything.
 *
 * @return array{0: Family, 1: Family}
 */
function twoFamilies(): array
{
    $a = Family::factory()->create();
    $b = Family::factory()->create();

    CurrentFamily::using($a->id, function () use ($a): void {
        Document::factory()->count(12)->create(['family_id' => $a->id]);
        Person::factory()->count(4)->create(['family_id' => $a->id]);
    });
    CurrentFamily::using($b->id, function () use ($b): void {
        Document::factory()->count(3)->create(['family_id' => $b->id]);
        Person::factory()->count(2)->create(['family_id' => $b->id]);
    });
    File::factory()->count(2)->create(['family_id' => $a->id, 'size' => 5000000]);
    File::factory()->create(['family_id' => $b->id, 'size' => 5]);

    return [$a, $b];
}

it('counts the figures of each family on its own', function (): void {
    [$a, $b] = twoFamilies();

    $figures = resolve(FamilyFigures::class)->forFamilies([$a->id, $b->id], CarbonImmutable::now());

    expect($figures[$a->id]->documents)->toBe(12)
        ->and($figures[$b->id]->documents)->toBe(3)
        ->and($figures[$a->id]->people)->toBe(4)
        ->and($figures[$b->id]->people)->toBe(2)
        ->and($figures[$a->id]->fileBytes)->toBe((int) DB::table('files')->where('family_id', $a->id)->sum('size'))
        ->and($figures[$b->id]->fileBytes)->toBe((int) DB::table('files')->where('family_id', $b->id)->sum('size'))
        ->and($figures[$a->id]->fileBytes)->toBeGreaterThan($figures[$b->id]->fileBytes);
});

it('counts the readings of the month except the crops', function (): void {
    [$a, $b] = twoFamilies();
    AiReading::factory()->count(2)->create(['family_id' => $a->id, 'cost_usd' => '0.010000']);
    AiReading::factory()->create(['family_id' => $a->id, 'purpose' => ReadingPurpose::Crop, 'cost_usd' => '0.5']);
    AiReading::factory()->create(['family_id' => $b->id, 'cost_usd' => '0.004200']);

    $figures = resolve(FamilyFigures::class)->forFamilies([$a->id, $b->id], CarbonImmutable::now());

    expect($figures[$a->id]->readings)->toBe(2)
        ->and($figures[$b->id]->readings)->toBe(1)
        ->and($figures[$b->id]->readingCost)->toBe(0.0042);
});

it('gives zeros to a family with nothing', function (): void {
    $family = Family::factory()->create();

    $figure = resolve(FamilyFigures::class)->forFamilies([$family->id], CarbonImmutable::now())[$family->id];

    expect($figure->documents)->toBe(0)
        ->and($figure->fileBytes)->toBe(0)
        ->and($figure->readings)->toBe(0)
        ->and($figure->lastActivity)->toBeNull();
});

it('takes the last activity from the sessions of the accounts', function (): void {
    $family = Family::factory()->create();
    $one = User::factory()->create(['family_id' => $family->id]);
    $two = User::factory()->create(['family_id' => $family->id]);
    $stamp = CarbonImmutable::parse('2026-11-05 10:00:00')->getTimestamp();
    foreach ([[$one, 's1', $stamp - 500], [$two, 's2', $stamp], [$two, 's3', $stamp - 900]] as [$user, $id, $time]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => $time]);
    }

    $figures = resolve(FamilyFigures::class);

    expect($figures->forFamilies([$family->id], CarbonImmutable::now())[$family->id]->lastActivity?->getTimestamp())->toBe($stamp)
        ->and($figures->lastActivityOf($one->id)?->getTimestamp())->toBe($stamp - 500)
        ->and($figures->lastActivityOf(999999))->toBeNull();
});

it('lists the readings month by month', function (): void {
    $family = Family::factory()->create();
    AiReading::factory()->create(['family_id' => $family->id, 'cost_usd' => '0.010000', 'created_at' => CarbonImmutable::parse('2026-10-10 12:00')]);
    AiReading::factory()->create(['family_id' => $family->id, 'cost_usd' => '0.020000', 'created_at' => CarbonImmutable::parse('2026-10-20 12:00')]);
    AiReading::factory()->create(['family_id' => $family->id, 'purpose' => ReadingPurpose::Crop, 'created_at' => CarbonImmutable::parse('2026-10-20 12:00')]);

    $months = resolve(FamilyFigures::class)->monthlyReadings($family->id, 12, CarbonImmutable::parse('2026-11-09 12:00'));

    expect($months)->toHaveCount(12)
        ->and($months['2026-10'])->toBe(['readings' => 2, 'cost' => 0.03])
        ->and($months['2026-11'])->toBe(['readings' => 0, 'cost' => 0.0])
        ->and(array_key_first($months))->toBe('2025-12');
});
