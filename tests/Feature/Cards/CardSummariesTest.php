<?php

declare(strict_types=1);

use App\Data\AreaData;
use App\Data\CardData;
use App\Enums\Area;
use App\Enums\BelongingStatus;
use App\Enums\InsurancePolicyStatus;
use App\Models\Belonging;
use App\Models\InsurancePolicy;
use App\Models\Vehicle;
use App\Services\CardSummaries;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Date::setTestNow(Date::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
});

afterEach(fn () => Date::setTestNow());

/**
 * The summaries of the Bianchi, read inside their family.
 */
function summaries(): CardSummaries
{
    return resolve(CardSummaries::class);
}

/**
 * The row of a card of an area, found by its label.
 */
function rowOf(AreaData $area, string $label): CardData
{
    return collect($area->cards)->firstWhere('label', $label) ?? throw new RuntimeException($label.' is not in '.$area->area);
}

test('it counts the cards of each area', function (): void {
    insideBianchi(function (): void {
        $areas = summaries()->areas();

        expect(array_map(fn (AreaData $area): string => $area->area, $areas))->toBe(array_map(fn (Area $area): string => $area->value, Area::cases()))
            ->and(collect($areas)->firstWhere('area', 'home')?->count)->toBe(7)
            ->and(collect($areas)->firstWhere('area', 'vehicles')?->count)->toBe(1)
            ->and(collect($areas)->firstWhere('area', 'school')?->count)->toBe(1)
            ->and(collect($areas)->firstWhere('area', 'home')?->label)->toBe(Area::Home->label())
            ->and(summaries()->area(Area::Vehicles)->cards)->toHaveCount(1);
    });
});

test('it builds the row of a card', function (): void {
    insideBianchi(function (): void {
        $row = rowOf(summaries()->area(Area::Vehicles), 'Fiat Panda');

        expect($row->type)->toBe('vehicle')
            ->and($row->type_label)->toBe('Vehicle')
            ->and($row->area)->toBe('vehicles')
            ->and($row->subtitle)->toBe('GH 482 TK')
            ->and($row->closed)->toBeFalse()
            ->and($row->next_label)->toBeNull()
            ->and($row->next_date)->toBeNull()
            ->and(summaries()->row(Vehicle::query()->sole())->id)->toBe($row->id);
    });
});

test('it marks overdue an active card with an expiring date in the past', function (): void {
    insideBianchi(function (): void {
        $tv = Belonging::query()->where('name', 'TV Samsung')->sole();
        $tv->update(['warranty_expiry_date' => '2026-10-25']);

        expect(summaries()->row($tv)->overdue)->toBeTrue()
            ->and(summaries()->area(Area::Home)->overdue)->toBe(1);

        $tv->update(['status' => BelongingStatus::Disposed]);

        expect(summaries()->row($tv)->overdue)->toBeFalse()
            ->and(summaries()->area(Area::Home)->overdue)->toBe(0);
    });
});

test('it ignores dates that do not expire', function (): void {
    insideBianchi(function (): void {
        $tv = Belonging::query()->where('name', 'TV Samsung')->sole();
        $tv->update(['warranty_expiry_date' => null, 'purchase_date' => '2026-09-15']);
        $row = summaries()->row($tv);

        expect($row->overdue)->toBeFalse()
            ->and($row->next_date)->toBe('2026-09-15')
            ->and($row->next_label)->toBe('Purchase date');
    });
});

test('it picks the nearest coming date, then the latest past one', function (): void {
    insideBianchi(function (): void {
        $panda = Vehicle::query()->sole();
        $panda->update(['road_tax_due_date' => '2027-01-31', 'inspection_due_date' => '2027-01-09']);
        $row = summaries()->row($panda);

        expect($row->next_label)->toBe('Inspection')
            ->and($row->next_date)->toBe('2027-01-09');

        $panda->update(['road_tax_due_date' => '2026-09-01', 'inspection_due_date' => '2026-10-01']);
        $row = summaries()->row($panda);

        expect($row->next_label)->toBe('Inspection')
            ->and($row->next_date)->toBe('2026-10-01')
            ->and($row->overdue)->toBeTrue();
    });
});

test('it counts a date of today as not yet overdue', function (): void {
    Date::setTestNow(Date::parse('2026-10-28 23:30', 'Europe/Rome'));

    insideBianchi(function (): void {
        $panda = Vehicle::query()->sole();
        $panda->update(['inspection_due_date' => '2026-10-28']);
        $row = summaries()->row($panda);

        expect($row->overdue)->toBeFalse()
            ->and($row->next_date)->toBe('2026-10-28');

        Date::setTestNow(Date::parse('2026-10-29 00:30', 'Europe/Rome'));

        expect(summaries()->row($panda->fresh() ?? $panda)->overdue)->toBeTrue();
    });
});

test('it lists closed cards last', function (): void {
    insideBianchi(function (): void {
        $policy = InsurancePolicy::query()->create(['title' => 'Aaa old policy', 'type' => 'car', 'status' => InsurancePolicyStatus::Cancelled]);
        $area = summaries()->area(Area::Insurance);

        expect(array_map(fn (CardData $card): string => $card->label, $area->cards))->toBe(['RC auto Panda', 'Aaa old policy'])
            ->and($area->cards[1]->closed)->toBeTrue()
            ->and($area->count)->toBe(1)
            ->and($policy->exists)->toBeTrue();
    });
});

test('it reads the rows of an area with one query per type', function (): void {
    insideBianchi(function (): void {
        DB::enableQueryLog();
        summaries()->areas();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 13 types, plus the lazy-load free relations: far below one query per card.
        expect($queries)->toBeLessThanOrEqual(13 * 3);
    });
});
