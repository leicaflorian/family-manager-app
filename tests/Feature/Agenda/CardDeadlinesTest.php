<?php

declare(strict_types=1);

use App\Cards\DeadlineSources;
use App\Enums\BelongingStatus;
use App\Enums\DocumentStatus;
use App\Enums\IntervalUnit;
use App\Enums\LoanStatus;
use App\Enums\ReminderStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\VehicleStatus;
use App\Models\Belonging;
use App\Models\Document;
use App\Models\Family;
use App\Models\Loan;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\Vehicle;
use App\Queries\CardDeadlines;
use App\Support\CardDeadline;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
});

afterEach(fn () => Date::setTestNow());

/**
 * The deadlines of the current family between two days, today being 26/10/2026.
 *
 * @param  string  $from  first day
 * @param  string  $to  last day
 * @return list<CardDeadline>
 */
function deadlinesBetween(string $from = '2026-10-01', string $to = '2027-12-31'): array
{
    return (new CardDeadlines)->between(
        CarbonImmutable::parse($from, 'Europe/Rome'),
        CarbonImmutable::parse($to, 'Europe/Rome'),
        CarbonImmutable::today('Europe/Rome'),
    );
}

/**
 * The fields of the deadlines found.
 *
 * @param  list<CardDeadline>  $deadlines  the deadlines
 * @return list<string>
 */
function fieldsOf(array $deadlines): array
{
    return array_map(fn (CardDeadline $deadline): string => $deadline->source->field, $deadlines);
}

test('a sold vehicle has no deadlines', function (): void {
    CurrentFamily::using(Family::factory()->create()->id, function (): void {
        $panda = Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'status' => VehicleStatus::Active, 'inspection_due_date' => '2027-01-09', 'road_tax_due_date' => null]);
        Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'status' => VehicleStatus::Sold, 'inspection_due_date' => '2027-01-09', 'road_tax_due_date' => null]);

        $found = deadlinesBetween();

        expect($found)->toHaveCount(1)
            ->and($found[0]->card->is($panda))->toBeTrue()
            ->and($found[0]->area)->toBe('vehicles')
            ->and($found[0]->date->toDateString())->toBe('2027-01-09');
    });
});

test('a disposed belonging loses the end of warranty', function (): void {
    CurrentFamily::using(Family::factory()->create()->id, function (): void {
        Belonging::factory()->create(['family_id' => CurrentFamily::id(), 'status' => BelongingStatus::Disposed, 'warranty_expiry_date' => '2026-12-01']);

        expect(deadlinesBetween())->toBe([]);
    });
});

test('a subscription without a yearly planned expense has no renewal', function (): void {
    CurrentFamily::using(Family::factory()->create()->id, function (): void {
        $monthly = Subscription::factory()->create(['family_id' => CurrentFamily::id(), 'status' => SubscriptionStatus::Active, 'renewal_date' => '2026-12-01', 'trial_end_date' => null]);
        PlannedExpense::factory()->forCard($monthly)->create(['family_id' => CurrentFamily::id(), 'interval_unit' => IntervalUnit::Month]);
        $yearly = Subscription::factory()->create(['family_id' => CurrentFamily::id(), 'status' => SubscriptionStatus::Active, 'renewal_date' => '2026-12-02', 'trial_end_date' => null]);
        PlannedExpense::factory()->forCard($yearly)->create(['family_id' => CurrentFamily::id(), 'interval_unit' => IntervalUnit::Year]);

        $found = deadlinesBetween();

        expect($found)->toHaveCount(1)
            ->and($found[0]->card->is($yearly))->toBeTrue();
    });
});

test('a paid off loan keeps its last instalment only once past', function (): void {
    CurrentFamily::using(Family::factory()->create()->id, function (): void {
        $past = Loan::factory()->create(['family_id' => CurrentFamily::id(), 'status' => LoanStatus::PaidOff, 'start_date' => null, 'end_date' => '2026-10-10']);
        Loan::factory()->create(['family_id' => CurrentFamily::id(), 'status' => LoanStatus::PaidOff, 'start_date' => null, 'end_date' => '2026-11-10']);

        $found = deadlinesBetween();

        expect($found)->toHaveCount(1)
            ->and($found[0]->card->is($past))->toBeTrue()
            ->and(fieldsOf($found))->toBe(['loans.end_date']);
    });
});

test('the deadline of a document is its expiry', function (): void {
    CurrentFamily::using(Family::factory()->create()->id, function (): void {
        $marco = Person::factory()->create(['family_id' => CurrentFamily::id()]);
        $document = Document::factory()->create(['family_id' => CurrentFamily::id(), 'status' => DocumentStatus::Active, 'person_id' => $marco->id, 'expiry_date' => '2026-11-20']);

        $found = deadlinesBetween();

        expect($found)->toHaveCount(1)
            ->and($found[0]->card->is($document))->toBeTrue()
            ->and($found[0]->person?->is($marco))->toBeTrue()
            ->and($found[0]->area)->toBe('general')
            ->and($found[0]->source->label)->toBeNull();
    });
});

test('the reminder done is found from the fields of Directus', function (): void {
    CurrentFamily::using(Family::factory()->create()->id, function (): void {
        $done = Document::factory()->create(['family_id' => CurrentFamily::id(), 'status' => DocumentStatus::Active, 'expiry_date' => '2026-11-20']);
        $otherDate = Document::factory()->create(['family_id' => CurrentFamily::id(), 'status' => DocumentStatus::Active, 'expiry_date' => '2026-11-21']);
        $open = Document::factory()->create(['family_id' => CurrentFamily::id(), 'status' => DocumentStatus::Active, 'expiry_date' => '2026-11-22']);

        foreach ([[$done, '2026-11-20', ReminderStatus::Done], [$otherDate, '2026-11-01', ReminderStatus::Done], [$open, '2026-11-22', ReminderStatus::Open]] as [$card, $date, $status]) {
            $reminder = Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'is_automatic' => true, 'source_field' => 'documents.expiry_date', 'due_date' => $date, 'status' => $status]);
            DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => $card->getMorphClass(), 'remindable_id' => $card->id]);
        }

        $byId = collect(deadlinesBetween())->keyBy(fn (CardDeadline $deadline): int => (int) $deadline->card->getKey());

        expect($byId[$done->id]->reminderDone())->toBeTrue()
            ->and($byId[$otherDate->id]->reminderDone())->toBeFalse()
            ->and($byId[$open->id]->reminderDone())->toBeFalse()
            ->and($byId[$open->id]->reminder)->not->toBeNull();
    });
});

test('nothing of another family', function (): void {
    $rossi = Family::factory()->create();
    $bianchi = Family::factory()->create();
    CurrentFamily::using($rossi->id, fn () => Document::factory()->create(['family_id' => CurrentFamily::id(), 'status' => DocumentStatus::Active, 'expiry_date' => '2026-11-20']));

    expect(CurrentFamily::using($bianchi->id, fn (): array => deadlinesBetween()))->toBe([]);
});

test('the bounds are included', function (): void {
    CurrentFamily::using(Family::factory()->create()->id, function (): void {
        foreach (['2026-11-01', '2026-11-15', '2026-11-30', '2026-10-31', '2026-12-01'] as $day) {
            Document::factory()->create(['family_id' => CurrentFamily::id(), 'status' => DocumentStatus::Active, 'expiry_date' => $day]);
        }

        $found = deadlinesBetween('2026-11-01', '2026-11-30');

        expect(array_map(fn (CardDeadline $d): string => $d->date->toDateString(), $found))->toBe(['2026-11-01', '2026-11-15', '2026-11-30']);
    });
});

test('the queries do not grow with the cards', function (): void {
    CurrentFamily::using(Family::factory()->create()->id, function (): void {
        Document::factory()->count(6)->create(['family_id' => CurrentFamily::id(), 'status' => DocumentStatus::Active, 'expiry_date' => '2026-11-20', 'person_id' => Person::factory()->create(['family_id' => CurrentFamily::id()])->id]);

        DB::enableQueryLog();
        deadlinesBetween();
        $queries = count(DB::getQueryLog());
        DB::flushQueryLog();
        Document::factory()->count(6)->create(['family_id' => CurrentFamily::id(), 'status' => DocumentStatus::Active, 'expiry_date' => '2026-11-21', 'person_id' => Person::factory()->create(['family_id' => CurrentFamily::id()])->id]);
        DB::flushQueryLog();
        deadlinesBetween();

        expect(count(DB::getQueryLog()))->toBe($queries)
            ->and($queries)->toBeLessThanOrEqual(count(DeadlineSources::all()) * 2 + 1);
    });
});
