<?php

declare(strict_types=1);

use App\Enums\HealthEventType;
use App\Enums\HomeStatus;
use App\Enums\MaintenanceType;
use App\Enums\UtilityStatus;
use App\Models\Belonging;
use App\Models\Family;
use App\Models\HealthEvent;
use App\Models\Home;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Reading\RecordSearch;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->bianchi = Family::factory()->create(['name' => 'Bianchi']);
    $this->rossi = Family::factory()->create(['name' => 'Rossi']);
    CurrentFamily::set($this->bianchi->id);
    $this->search = new RecordSearch;
    $this->day = CarbonImmutable::parse('2026-03-15');
    $this->person = Person::factory()->for($this->bianchi)->create();
    $this->clinic = Organization::factory()->for($this->bianchi)->create();
    $this->otherClinic = Organization::factory()->for($this->bianchi)->create();
});

afterEach(fn () => CurrentFamily::clear());

/**
 * A health event of the Bianchi person.
 *
 * @param  array<string, mixed>  $attributes
 */
function eventOf(array $attributes = []): HealthEvent
{
    return HealthEvent::factory()->for(test()->bianchi)->create($attributes + [
        'person_id' => test()->person->id,
        'organization_id' => test()->clinic->id,
        'type' => HealthEventType::Visit,
        'date' => '2026-03-15',
    ]);
}

describe('findEvent', function (): void {
    test('findEvent: 7 days around the date', function (): void {
        $event = eventOf(['date' => '2026-03-22']);

        expect($this->search->event($this->person->id, $this->day, $this->clinic->id, 'visit')?->id)->toBe($event->id);

        $event->update(['date' => '2026-03-23']);
        expect($this->search->event($this->person->id, $this->day, $this->clinic->id, 'visit'))->toBeNull();

        $event->update(['date' => '2026-03-08']);
        expect($this->search->event($this->person->id, $this->day, $this->clinic->id, 'visit')?->id)->toBe($event->id);

        $event->update(['date' => '2026-03-07']);
        expect($this->search->event($this->person->id, $this->day, $this->clinic->id, 'visit'))->toBeNull();
    });

    test('an event of the same day wins over a closer-looking one of another day', function (): void {
        eventOf(['date' => '2026-03-14']);
        $same = eventOf(['type' => HealthEventType::Exam]);

        expect($this->search->event($this->person->id, $this->day, $this->clinic->id, 'visit')?->id)->toBe($same->id);
    });

    test('same day with the place missing on the event stays; with another place it does not', function (): void {
        $missing = eventOf(['organization_id' => null]);
        expect($this->search->event($this->person->id, $this->day, $this->clinic->id, 'visit')?->id)->toBe($missing->id);

        $missing->delete();
        eventOf(['organization_id' => $this->otherClinic->id]);
        expect($this->search->event($this->person->id, $this->day, $this->clinic->id, 'visit'))->toBeNull();
    });

    test('without the place on the paper the same day matches whatever the place, but another day never does', function (): void {
        eventOf(['date' => '2026-03-14']);
        $same = eventOf(['organization_id' => $this->otherClinic->id]);
        expect($this->search->event($this->person->id, $this->day, null, null)?->id)->toBe($same->id);

        $same->delete();
        expect($this->search->event($this->person->id, $this->day, null, 'visit'))->toBeNull();
    });

    test('no event of the same day and no type: null', function (): void {
        eventOf(['date' => '2026-03-14']);

        expect($this->search->event($this->person->id, $this->day, $this->clinic->id, null))->toBeNull();
    });

    test('another day: the closest of the same place and type, not the first one, not another type or place', function (): void {
        eventOf(['date' => '2026-03-20']);
        eventOf(['date' => '2026-03-14', 'type' => HealthEventType::Exam]);
        eventOf(['date' => '2026-03-14', 'organization_id' => $this->otherClinic->id]);
        $closest = eventOf(['date' => '2026-03-12']);

        expect($this->search->event($this->person->id, $this->day, $this->clinic->id, 'visit')?->id)->toBe($closest->id);
    });

    test('another day, same distance before and after: the older event', function (): void {
        $older = eventOf(['date' => '2026-03-13']);
        eventOf(['date' => '2026-03-17']);

        expect($this->search->event($this->person->id, $this->day, $this->clinic->id, 'visit')?->id)->toBe($older->id);
    });

    test('another day and no event of that place and type: null', function (): void {
        eventOf(['date' => '2026-03-14', 'organization_id' => $this->otherClinic->id]);

        expect($this->search->event($this->person->id, $this->day, $this->clinic->id, 'visit'))->toBeNull();
    });

    test('an event of another person is not found', function (): void {
        $other = Person::factory()->for($this->bianchi)->create();
        eventOf(['person_id' => $other->id]);

        expect($this->search->event($this->person->id, $this->day, $this->clinic->id, 'visit'))->toBeNull();
    });

    test('an event of another family is not found', function (): void {
        CurrentFamily::using($this->rossi->id, function (): void {
            $person = Person::factory()->for($this->rossi)->create();
            HealthEvent::factory()->for($this->rossi)->create(['person_id' => $person->id, 'date' => '2026-03-15']);
        });

        expect($this->search->event($this->person->id, $this->day, null, null))->toBeNull();
        expect(HealthEvent::query()->count())->toBe(0);
    });
});

describe('findTerminatedUtility', function (): void {
    test('looks among the terminated utilities, by POD', function (): void {
        $old = Utility::factory()->for($this->bianchi)->create(['status' => UtilityStatus::Terminated, 'supply_point_code' => 'IT001E12345678']);
        Utility::factory()->for($this->bianchi)->create(['status' => UtilityStatus::Active, 'supply_point_code' => 'IT001E99999999']);

        expect($this->search->terminatedUtility('it 001e-12345678', null)?->id)->toBe($old->id);
        expect($this->search->terminatedUtility('IT001E99999999', null))->toBeNull();
    });

    test('then by customer code', function (): void {
        $old = Utility::factory()->for($this->bianchi)->create(['status' => UtilityStatus::Terminated, 'customer_code' => '123-456']);

        expect($this->search->terminatedUtility(null, '123456')?->id)->toBe($old->id);
        expect($this->search->terminatedUtility(null, null))->toBeNull();
    });
});

describe('findActiveHomes', function (): void {
    test('reads the active homes', function (): void {
        $home = Home::factory()->for($this->bianchi)->create(['name' => 'Casa Schio', 'status' => HomeStatus::Active]);
        Home::factory()->for($this->bianchi)->create(['status' => HomeStatus::Archived]);
        CurrentFamily::using($this->rossi->id, fn () => Home::factory()->for($this->rossi)->create(['status' => HomeStatus::Active]));

        expect($this->search->activeHomes()->pluck('id')->all())->toBe([$home->id]);
    });
});

describe('findBelonging', function (): void {
    beforeEach(function (): void {
        $this->shop = Organization::factory()->for($this->bianchi)->create();
        $this->washer = Belonging::factory()->for($this->bianchi)->create([
            'name' => 'Lavatrice', 'brand' => 'Bosch', 'model' => 'WGG 244', 'organization_id' => $this->shop->id,
            'purchase_date' => '2026-10-03', 'purchase_price' => '549.00',
        ]);
        $this->blender = Belonging::factory()->for($this->bianchi)->create([
            'name' => 'Frullatore', 'brand' => null, 'model' => null, 'organization_id' => $this->shop->id,
            'purchase_date' => '2026-09-01', 'purchase_price' => '39.90',
        ]);
    });

    test('same brand and model, written another way', function (): void {
        expect($this->search->belonging('BOSCH', 'WGG244', null, null, null)?->id)->toBe($this->washer->id);
    });

    test('same shop, day and price to the cent', function (): void {
        $found = $this->search->belonging(null, null, $this->shop->id, CarbonImmutable::parse('2026-09-01'), 39.9);

        expect($found?->id)->toBe($this->blender->id);
    });

    test('none when nothing matches', function (): void {
        expect($this->search->belonging('Samsung', 'X1', $this->shop->id, CarbonImmutable::parse('2026-09-01'), 40.0))->toBeNull();
        expect($this->search->belonging(null, null, null, null, null))->toBeNull();
    });
});

describe('findMaintenance', function (): void {
    beforeEach(function (): void {
        $this->home = Home::factory()->for($this->bianchi)->create();
        $this->vehicle = Vehicle::factory()->for($this->bianchi)->create();
    });

    /**
     * A maintenance record of the Bianchi.
     *
     * @param  array<string, mixed>  $attributes
     */
    function recordOf(array $attributes): MaintenanceRecord
    {
        return MaintenanceRecord::factory()->for(test()->bianchi)->create($attributes + ['type' => MaintenanceType::BoilerService]);
    }

    test('same home and type within 7 days: 20/09 finds 25/09, not 28/09', function (): void {
        recordOf(['home_id' => $this->home->id, 'date' => '2026-09-28']);
        $near = recordOf(['home_id' => $this->home->id, 'date' => '2026-09-25']);

        $found = $this->search->maintenance(null, $this->home->id, 'boiler_service', CarbonImmutable::parse('2026-09-20'));

        expect($found?->id)->toBe($near->id);
    });

    test('nothing beyond 7 days', function (): void {
        recordOf(['home_id' => $this->home->id, 'date' => '2026-09-28']);

        expect($this->search->maintenance(null, $this->home->id, 'boiler_service', CarbonImmutable::parse('2026-09-20')))->toBeNull();
    });

    test('the vehicle wins over the home', function (): void {
        recordOf(['home_id' => $this->home->id, 'date' => '2026-09-21', 'type' => MaintenanceType::Service]);
        $car = recordOf(['vehicle_id' => $this->vehicle->id, 'date' => '2026-09-20', 'type' => MaintenanceType::Service]);

        $found = $this->search->maintenance($this->vehicle->id, $this->home->id, 'service', CarbonImmutable::parse('2026-09-21'));

        expect($found?->id)->toBe($car->id);
    });

    test('without vehicle, home, type or date it does not look', function (): void {
        recordOf(['home_id' => $this->home->id, 'date' => '2026-09-20']);
        $date = CarbonImmutable::parse('2026-09-20');

        expect($this->search->maintenance(null, null, 'boiler_service', $date))->toBeNull();
        expect($this->search->maintenance(null, $this->home->id, null, $date))->toBeNull();
        expect($this->search->maintenance(null, $this->home->id, 'boiler_service', null))->toBeNull();
    });
});
