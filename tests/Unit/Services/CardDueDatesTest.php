<?php

declare(strict_types=1);

use App\Cards\CardTypes;
use App\Models\Employment;
use App\Models\Utility;
use App\Services\CardDueDates;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
});

afterEach(fn () => Date::setTestNow());

it('gives the oldest open due date of a card that is late', function (): void {
    $due = insideBianchi(function (): array {
        $luce = Utility::query()->whereRelation('organization', 'name', 'Edison')->sole();

        return [$luce->id, resolve(CardDueDates::class)->of(CardTypes::findOrFail('utility'), [$luce], CarbonImmutable::today('Europe/Rome'))];
    });

    expect($due[1])->toBe([$due[0] => ['amount' => 71.3, 'late' => true]]);
});

it('gives nothing for no cards or for a type without payments', function (): void {
    insideBianchi(function (): void {
        $today = CarbonImmutable::today('Europe/Rome');

        expect(resolve(CardDueDates::class)->of(CardTypes::findOrFail('utility'), [], $today))->toBe([])
            ->and(resolve(CardDueDates::class)->of(CardTypes::findOrFail('belonging'), [], $today))->toBe([]);
    });
});

it('reads the planned incomes of a job', function (): void {
    insideBianchi(function (): void {
        $jobs = Employment::query()->get();
        $due = resolve(CardDueDates::class)->of(CardTypes::findOrFail('employment'), $jobs, CarbonImmutable::today('Europe/Rome'));

        expect(array_diff(array_keys($due), $jobs->modelKeys()))->toBe([]);
    });
});
