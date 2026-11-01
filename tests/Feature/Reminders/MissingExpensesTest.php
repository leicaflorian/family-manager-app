<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\Reminder;
use App\Models\Vehicle;
use App\Services\Reminders\MissingExpenses;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->user = registerFamily();
    $this->today = CarbonImmutable::today('Europe/Rome');
});

afterEach(fn () => Date::setTestNow());

/**
 * An automatic reminder linked to a card.
 *
 * @param  Model  $card  the card
 * @param  string  $field  the `source_field`
 */
function reminderOf(Model $card, string $field): Reminder
{
    $reminder = Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'is_automatic' => true, 'source_field' => $field, 'due_date' => '2026-11-14']);
    DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => $card->getMorphClass(), 'remindable_id' => $card->id]);

    return $reminder->load('link');
}

test('a road tax without a planned expense is missing the expense', function (): void {
    insideFamilyOf($this->user, function (): void {
        $reminder = reminderOf(Vehicle::factory()->create(['family_id' => CurrentFamily::id()]), 'vehicles.road_tax_due_date');

        expect(resolve(MissingExpenses::class)->of(collect([$reminder]), $this->today))->toBe([$reminder->id => true]);
    });
});

test('a planned expense in progress for the same card is enough', function (): void {
    insideFamilyOf($this->user, function (): void {
        $panda = Vehicle::factory()->create(['family_id' => CurrentFamily::id()]);
        $reminder = reminderOf($panda, 'vehicles.road_tax_due_date');
        agendaPlanned($panda, 'Bollo', 180, '2025-01-31', ['end_date' => null]);

        expect(resolve(MissingExpenses::class)->of(collect([$reminder]), $this->today))->toBe([]);
    });
});

test('a planned expense that ends today still counts, the one that ended yesterday does not', function (): void {
    insideFamilyOf($this->user, function (): void {
        $panda = Vehicle::factory()->create(['family_id' => CurrentFamily::id()]);
        $golf = Vehicle::factory()->create(['family_id' => CurrentFamily::id()]);
        $pandaReminder = reminderOf($panda, 'vehicles.road_tax_due_date');
        $golfReminder = reminderOf($golf, 'vehicles.inspection_due_date');
        agendaPlanned($panda, 'Bollo', 180, '2025-01-31', ['end_date' => '2026-10-26']);
        agendaPlanned($golf, 'Revisione', 80, '2025-01-31', ['end_date' => '2026-10-25']);

        expect(resolve(MissingExpenses::class)->of(collect([$pandaReminder, $golfReminder]), $this->today))->toBe([$golfReminder->id => true]);
    });
});

test('a planned expense of another card, or of another kind of card, does not count', function (): void {
    insideFamilyOf($this->user, function (): void {
        $panda = Vehicle::factory()->create(['family_id' => CurrentFamily::id()]);
        $golf = Vehicle::factory()->create(['family_id' => CurrentFamily::id()]);
        $reminder = reminderOf($panda, 'vehicles.road_tax_due_date');
        agendaPlanned($golf, 'Bollo', 180, '2025-01-31');
        agendaPlanned(null, 'Senza scheda', 10, '2025-01-31');

        expect(resolve(MissingExpenses::class)->of(collect([$reminder]), $this->today))->toBe([$reminder->id => true]);
    });
});

test('a document never misses an expense', function (): void {
    insideFamilyOf($this->user, function (): void {
        $reminder = reminderOf(Document::factory()->create(['family_id' => CurrentFamily::id()]), 'documents.expiry_date');

        expect(resolve(MissingExpenses::class)->of(collect([$reminder]), $this->today))->toBe([]);
    });
});

test('a reminder written by hand, without a link or a kind, misses nothing', function (): void {
    insideFamilyOf($this->user, function (): void {
        $manual = Reminder::factory()->create(['family_id' => CurrentFamily::id()])->load('link');

        expect(resolve(MissingExpenses::class)->of(collect([$manual]), $this->today))->toBe([]);
    });
});

test('ten reminders cost one query', function (): void {
    insideFamilyOf($this->user, function (): void {
        $reminders = collect();

        foreach (range(1, 10) as $number) {
            $vehicle = Vehicle::factory()->create(['family_id' => CurrentFamily::id()]);
            $reminders->push(reminderOf($vehicle, 'vehicles.road_tax_due_date'));

            if ($number % 2 === 0) {
                agendaPlanned($vehicle, 'Bollo', 180, '2025-01-31');
            }
        }

        DB::enableQueryLog();
        $missing = resolve(MissingExpenses::class)->of($reminders, $this->today);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1)
            ->and($missing)->toHaveCount(5);
    });
});
