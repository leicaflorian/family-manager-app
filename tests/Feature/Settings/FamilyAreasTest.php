<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Models\FamilySetting;
use App\Services\FamilyAreas;

beforeEach(function (): void {
    $this->marco = registerFamily();
});

/**
 * Runs some work inside Marco's family with a fresh service.
 *
 * @template T
 *
 * @param  callable(FamilyAreas): T  $work
 * @return T
 */
function withAreas(callable $work): mixed
{
    return insideFamilyOf(test()->marco, fn (): mixed => $work(resolve(FamilyAreas::class)));
}

/**
 * Writes the raw `areas` column of Marco's family.
 */
function writeAreas(?string $json): void
{
    insideFamilyOf(test()->marco, function () use ($json): void {
        $setting = FamilySetting::query()->firstOrCreate([]);
        $setting->forceFill(['areas' => $json === null ? null : json_decode($json, true)])->save();
    });
}

test('without choices every area is shown in the default order and none is hidden', function (): void {
    withAreas(function (FamilyAreas $areas): void {
        expect($areas->ordered())->toBe(Area::cases())
            ->and($areas->visible())->toBe(Area::cases())
            ->and($areas->hidden())->toBe([]);
    });
});

test('visible leaves out the hidden areas and keeps the saved order', function (): void {
    withAreas(function (FamilyAreas $areas): void {
        $areas->save(
            [Area::Home, Area::Health, Area::Vehicles, Area::Insurance, Area::Banks, Area::School, Area::Work, Area::Subscriptions, Area::Paperwork],
            [Area::Work],
        );

        expect($areas->visible())->toBe([Area::Home, Area::Health, Area::Vehicles, Area::Insurance, Area::Banks, Area::School, Area::Subscriptions, Area::Paperwork])
            ->and($areas->hidden())->toBe([Area::Work])
            ->and($areas->isHidden(Area::Work))->toBeTrue()
            ->and($areas->isHidden(Area::Home))->toBeFalse();
    });
});

test('an area missing from the order goes after the last area that precedes it', function (): void {
    writeAreas('{"order": ["health", "home"], "hidden": []}');

    withAreas(function (FamilyAreas $areas): void {
        expect($areas->ordered())->toBe([
            Area::Health, Area::Home, Area::Vehicles, Area::Insurance, Area::Banks,
            Area::School, Area::Work, Area::Subscriptions, Area::Paperwork,
        ]);
    });
});

test('a value that is not an area is ignored in the order and in the hidden list', function (): void {
    writeAreas('{"order": ["garden", "vehicles", "home"], "hidden": ["garden", "banks"]}');

    withAreas(function (FamilyAreas $areas): void {
        expect($areas->ordered()[0])->toBe(Area::Vehicles)
            ->and($areas->ordered())->toHaveCount(count(Area::cases()))
            ->and($areas->hidden())->toBe([Area::Banks]);
    });
});

test('reveal takes an area out of the hidden ones and tells if it was hidden', function (): void {
    withAreas(function (FamilyAreas $areas): void {
        $areas->save(Area::cases(), [Area::Work]);

        expect($areas->reveal(Area::Work))->toBeTrue()
            ->and($areas->isHidden(Area::Work))->toBeFalse()
            ->and($areas->reveal(Area::Work))->toBeFalse();
    });
});

test('reset puts the column back to null', function (): void {
    withAreas(function (FamilyAreas $areas): void {
        $areas->save([Area::Health], [Area::Work]);
        $areas->reset();

        expect(FamilySetting::query()->firstOrFail()->areas)->toBeNull()
            ->and($areas->ordered())->toBe(Area::cases());
    });
});

test('the choice of a family is not seen from another family', function (): void {
    $other = registerFamily('Rossi', 'anna@example.com');

    withAreas(fn (FamilyAreas $areas) => $areas->save([Area::Health], [Area::Work]));

    insideFamilyOf($other, function (): void {
        $areas = resolve(FamilyAreas::class);
        expect($areas->hidden())->toBe([])
            ->and($areas->ordered())->toBe(Area::cases());
    });
});
