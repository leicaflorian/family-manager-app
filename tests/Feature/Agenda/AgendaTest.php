<?php

declare(strict_types=1);

use App\Data\AgendaEntryData;
use App\Enums\DocumentStatus;
use App\Enums\ExceptionType;
use App\Enums\VehicleStatus;
use App\Models\Document;
use App\Models\Expense;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Models\PlannedIncome;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Agenda;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
});

afterEach(fn () => Date::setTestNow());

/**
 * The props of the Agenda as an account sees it.
 *
 * @param  User  $user  the account
 * @param  string  $query  the query string, for example `?months=3`
 * @return array<string, mixed>
 */
function agendaFor(User $user, string $query = ''): array
{
    $props = test()->actingAs($user)->get('/agenda'.$query)->assertOk()->viewData('page')['props'];

    return $props['agenda'];
}

/**
 * Only some keys of the entries, to compare them.
 *
 * @param  list<array<string, mixed>>  $entries  the entries
 * @param  list<string>  $keys  the keys to keep
 * @return list<array<string, mixed>>
 */
function agendaColumns(array $entries, array $keys): array
{
    return array_map(static fn (array $entry): array => array_intersect_key($entry, array_flip($keys)), $entries);
}

test('the agenda of one month has the twelve entries of the board in its order', function (): void {
    $agenda = agendaFor(boardFamily());

    expect($agenda['months'])->toBe(1)
        ->and($agenda['from'])->toBe('2026-10-26')
        ->and($agenda['to'])->toBe('2026-11-26')
        ->and(agendaColumns($agenda['entries'], ['kind', 'date', 'title', 'subtitle', 'person', 'amount']))->toBe([
            ['kind' => 'deadline', 'date' => '2026-09-30', 'title' => 'Tessera sanitaria di Giulia', 'subtitle' => 'Document of Giulia', 'person' => 'Giulia', 'amount' => null],
            ['kind' => 'income', 'date' => '2026-10-27', 'title' => 'Stipendio di Marco', 'subtitle' => 'Marco - Studio Rinaldi', 'person' => 'Marco', 'amount' => 1850.0],
            ['kind' => 'payment', 'date' => '2026-10-30', 'title' => 'Bolletta gas', 'subtitle' => 'Gas - Enel', 'person' => 'Marco', 'amount' => 84.2],
            ['kind' => 'payment', 'date' => '2026-10-31', 'title' => 'Bolletta acqua', 'subtitle' => 'Water - Hera', 'person' => 'Marco', 'amount' => 37.9],
            ['kind' => 'deadline', 'date' => '2026-11-04', 'title' => 'End of the free trial', 'subtitle' => 'Netflix', 'person' => 'Laura', 'amount' => null],
            ['kind' => 'payment', 'date' => '2026-11-04', 'title' => 'Primo pagamento', 'subtitle' => 'Netflix', 'person' => 'Laura', 'amount' => 13.99],
            ['kind' => 'payment', 'date' => '2026-11-06', 'title' => 'Bolletta luce di ottobre', 'subtitle' => 'Electricity - Edison', 'person' => 'Marco', 'amount' => 62.4],
            ['kind' => 'payment', 'date' => '2026-11-10', 'title' => 'Rata 33 di 48', 'subtitle' => 'Finanziamento Panda', 'person' => 'Marco', 'amount' => 189.0],
            ['kind' => 'deadline', 'date' => '2026-11-14', 'title' => "Scade la carta d'identità", 'subtitle' => 'Document of Marco', 'person' => 'Marco', 'amount' => null],
            ['kind' => 'payment', 'date' => '2026-11-15', 'title' => 'Rata mutuo', 'subtitle' => 'Mutuo casa', 'person' => 'Marco', 'amount' => 612.0],
            ['kind' => 'payment', 'date' => '2026-11-21', 'title' => 'Policy renewal', 'subtitle' => 'RC auto Panda - UnipolSai', 'person' => 'Marco', 'amount' => 418.0],
            ['kind' => 'income', 'date' => '2026-11-23', 'title' => 'Stipendio di Laura', 'subtitle' => 'Laura - Comune di Bologna', 'person' => 'Laura', 'amount' => 1720.0],
        ]);

    $payments = array_filter($agenda['entries'], static fn (array $entry): bool => $entry['kind'] === 'payment');
    $byArea = array_count_values(array_column($agenda['entries'], 'area'));
    ksort($byArea);

    expect(count($payments))->toBe(7)
        ->and(round(array_sum(array_column($payments, 'amount')), 2))->toBe(1417.49)
        ->and($byArea)->toBe(['banks' => 2, 'general' => 2, 'home' => 3, 'insurance' => 1, 'subscriptions' => 2, 'work' => 2])
        ->and(collect($agenda['entries'])->firstWhere('title', "Scade la carta d'identità")['reminder_done'])->toBeTrue()
        ->and(collect($agenda['entries'])->firstWhere('title', 'Tessera sanitaria di Giulia')['reminder_done'])->toBeFalse()
        ->and(array_column($agenda['people'], 'first_name'))->toBe(['Marco', 'Laura', 'Giulia'])
        ->and($agenda['filters'])->toBe(['months' => 1, 'person_id' => null, 'hidden_areas' => []]);
});

test('the keys and the addresses of the entries', function (): void {
    $marco = boardFamily();
    $entries = collect(agendaFor($marco)['entries'])->keyBy('title');
    [$gas, $salary, $identity, $netflix] = insideFamilyOf($marco, fn (): array => [
        PlannedExpense::query()->where('title', 'Bolletta gas')->sole(),
        PlannedIncome::query()->where('title', 'Stipendio di Marco')->sole(),
        Document::query()->where('expiry_date', '2026-11-14')->sole(),
        Subscription::query()->where('name', 'Netflix')->sole(),
    ]);

    expect($entries['Bolletta gas']['key'])->toBe("payment:{$gas->id}:2026-10-30")
        ->and($entries['Bolletta gas']['href'])->toBe(route('budget.deadlines.show', ['kind' => 'expense', 'id' => $gas->id, 'date' => '2026-10-30']).'?pay=1')
        ->and($entries['Stipendio di Marco']['key'])->toBe("income:{$salary->id}:2026-10-27")
        ->and($entries['Stipendio di Marco']['href'])->toBe(route('budget.deadlines.show', ['kind' => 'income', 'id' => $salary->id, 'date' => '2026-10-27']).'?pay=1')
        ->and($entries["Scade la carta d'identità"]['key'])->toBe("deadline:documents.expiry_date:{$identity->id}:2026-11-14")
        ->and($entries["Scade la carta d'identità"]['href'])->toBe(route('documents.show', $identity))
        ->and($entries['End of the free trial']['key'])->toBe("deadline:subscriptions.trial_end_date:{$netflix->id}:2026-11-04")
        ->and($entries['End of the free trial']['href'])->toBe(route('cards.show', ['type' => 'subscription', 'id' => $netflix->id]))
        ->and($entries['End of the free trial']['person_id'])->toBe($netflix->person_id);
});

test('an unpaid payment of yesterday is not in the agenda', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, fn (): PlannedExpense => agendaPlanned(null, 'Bolletta luce', 50, '2026-10-25', ['is_recurring' => false]));

    expect(agendaFor($marco)['entries'])->toBe([]);

    // The Home and the calendar look 30 days back
    $entries = insideFamilyOf($marco, fn (): array => resolve(Agenda::class)->entries(CarbonImmutable::today('Europe/Rome'), CarbonImmutable::parse('2026-11-26', 'Europe/Rome'), paymentsBack: 30));
    expect(array_map(static fn (AgendaEntryData $entry): string => $entry->date, $entries))->toBe(['2026-10-25']);
});

test('a deadline of 90 days ago is there and one of 91 days ago is not', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        Document::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Ninety', 'status' => DocumentStatus::Expired, 'expiry_date' => '2026-07-28']);
        Document::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Ninety-one', 'status' => DocumentStatus::Expired, 'expiry_date' => '2026-07-27']);
    });

    expect(array_column(agendaFor($marco)['entries'], 'title'))->toBe(['Ninety'])
        ->and(agendaFor($marco)['entries'][0]['subtitle'])->toBe('Document');
});

test('an inspection of today at 23:30 in Rome is today', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-28 23:30', 'Europe/Rome'));
    $marco = registerFamily();
    insideFamilyOf($marco, fn (): Vehicle => Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'status' => VehicleStatus::Active, 'inspection_due_date' => '2026-10-28', 'road_tax_due_date' => null]));

    $agenda = agendaFor($marco);

    expect($agenda['from'])->toBe('2026-10-28')
        ->and(agendaColumns($agenda['entries'], ['date', 'title', 'area']))->toBe([['date' => '2026-10-28', 'title' => 'Inspection', 'area' => 'vehicles']]);
});

test('at 00:30 in Rome the day is already the new one, though it is still yesterday in UTC', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-29 00:30', 'Europe/Rome'));
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'status' => VehicleStatus::Active, 'inspection_due_date' => '2026-10-29', 'road_tax_due_date' => null]);
        Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'status' => VehicleStatus::Active, 'inspection_due_date' => '2026-10-28', 'road_tax_due_date' => null]);
    });

    $agenda = agendaFor($marco);
    $dates = array_column($agenda['entries'], 'date');

    // The page puts in the overdue group the deadlines before `from`
    expect($agenda['from'])->toBe('2026-10-29')
        ->and($dates)->toBe(['2026-10-28', '2026-10-29'])
        ->and($dates[0] < $agenda['from'])->toBeTrue()
        ->and($dates[1] < $agenda['from'])->toBeFalse();
});

test('of two payments of the same card on the same day only the lowest planned id merges with the deadline', function (): void {
    $marco = boardFamily();
    [$first, $second] = insideFamilyOf($marco, function (): array {
        $policy = InsurancePolicy::query()->sole();
        $first = PlannedExpense::query()->where('title', 'Rata RC auto')->sole();
        $second = agendaPlanned($policy, 'Furto e incendio', 95.00, '2026-11-21', ['is_recurring' => false, 'id' => $first->id + 1000]);

        return [$first, $second];
    });

    $found = array_values(array_filter(agendaFor($marco)['entries'], static fn (array $entry): bool => $entry['date'] === '2026-11-21'));

    expect(agendaColumns($found, ['key', 'kind', 'title', 'amount']))->toBe([
        ['key' => "payment:{$second->id}:2026-11-21", 'kind' => 'payment', 'title' => 'Furto e incendio', 'amount' => 95.0],
        ['key' => "payment:{$first->id}:2026-11-21", 'kind' => 'payment', 'title' => 'Policy renewal', 'amount' => 418.0],
    ])->and(array_unique(array_column($found, 'key')))->toHaveCount(2);
});

test('one month on 31/01/2027 ends on 28/02/2027', function (): void {
    expect(resolve(Agenda::class)->period(CarbonImmutable::parse('2027-01-31', 'Europe/Rome'), 1)->toDateString())->toBe('2027-02-28');

    Date::setTestNow(CarbonImmutable::parse('2027-01-31 10:00', 'Europe/Rome'));
    expect(agendaFor(registerFamily())['to'])->toBe('2027-02-28');
});

test('a deadline and a payment of the same card on the same day are one entry', function (): void {
    $marco = boardFamily();
    $planned = insideFamilyOf($marco, fn (): PlannedExpense => PlannedExpense::query()->where('title', 'Rata RC auto')->sole());

    $found = array_values(array_filter(agendaFor($marco)['entries'], static fn (array $entry): bool => $entry['date'] === '2026-11-21'));

    expect($found)->toHaveCount(1)
        ->and($found[0]['title'])->toBe('Policy renewal')
        ->and($found[0]['amount'])->toBe(418.0)
        ->and($found[0]['kind'])->toBe('payment')
        ->and($found[0]['area'])->toBe('insurance')
        ->and($found[0]['key'])->toBe("payment:{$planned->id}:2026-11-21")
        ->and($found[0]['href'])->toBe(route('budget.deadlines.show', ['kind' => 'expense', 'id' => $planned->id, 'date' => '2026-11-21']).'?pay=1');
});

test('the end of the trial stays apart from the first payment', function (): void {
    $found = array_filter(agendaFor(boardFamily())['entries'], static fn (array $entry): bool => $entry['subtitle'] === 'Netflix');

    expect(array_column($found, 'kind'))->toBe(['deadline', 'payment']);
});

test('the areas', function (): void {
    $entries = collect(agendaFor(boardFamily())['entries'])->keyBy('title');

    expect($entries['Bolletta gas']['area'])->toBe('home')
        ->and($entries['Stipendio di Marco']['area'])->toBe('work')
        ->and($entries['Tessera sanitaria di Giulia']['area'])->toBe('general');
});

test('a payment or an income without a card is general', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        agendaPlanned(null, 'Regalo', 30, '2026-11-02', ['is_recurring' => false, 'organization_id' => Organization::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Negozio'])->id]);
        PlannedIncome::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Affitto', 'amount' => 400, 'start_date' => '2026-11-03', 'is_recurring' => false]);
    });

    expect(agendaColumns(agendaFor($marco)['entries'], ['title', 'subtitle', 'area', 'person']))->toBe([
        ['title' => 'Regalo', 'subtitle' => 'Negozio', 'area' => 'general', 'person' => null],
        ['title' => 'Affitto', 'subtitle' => '', 'area' => 'general', 'person' => null],
    ]);
});

test('a paid due date leaves the agenda', function (): void {
    $marco = boardFamily();
    insideFamilyOf($marco, function (): void {
        $gas = PlannedExpense::query()->where('title', 'Bolletta gas')->sole();
        Expense::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $gas->id, 'occurrence_date' => '2026-10-30', 'amount' => 84.20, 'date' => '2026-10-26']);
    });

    expect(array_column(agendaFor($marco)['entries'], 'title'))->not->toContain('Bolletta gas')->toHaveCount(11);
});

test('a skipped due date is not in the agenda', function (): void {
    $marco = boardFamily();
    insideFamilyOf($marco, function (): void {
        $gas = PlannedExpense::query()->where('title', 'Bolletta gas')->sole();
        PlannedExpenseException::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $gas->id, 'type' => ExceptionType::Skip, 'occurrence_date' => '2026-10-30']);
    });

    expect(array_column(agendaFor($marco)['entries'], 'title'))->not->toContain('Bolletta gas');
});

test('more months show more entries', function (): void {
    $marco = boardFamily();
    $three = agendaFor($marco, '?months=3');

    expect($three['months'])->toBe(3)
        ->and($three['to'])->toBe('2027-01-26')
        ->and(count($three['entries']))->toBeGreaterThan(12);
});

test('an unknown months value counts as one month', function (string $query): void {
    $agenda = agendaFor(boardFamily(), $query);

    expect($agenda['months'])->toBe(1)
        ->and($agenda['to'])->toBe('2026-11-26')
        ->and($agenda['entries'])->toHaveCount(12);
})->with(['?months=2', '?months=abc', '?months=', '?months[]=3']);

test('a member sees the agenda', function (): void {
    $marco = boardFamily();
    $member = User::factory()->create(['family_id' => $marco->family_id]);

    $this->actingAs($member)->get('/agenda')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Agenda', false)->has('agenda.entries', 12));
});

test('an account of the Rossi sees nothing of the Bianchi', function (): void {
    boardFamily();
    $rossi = registerFamily('Rossi', 'mario@example.com');

    $agenda = agendaFor($rossi);

    expect($agenda['entries'])->toBe([])
        ->and(array_column($agenda['people'], 'last_name'))->toBe(['Rossi']);
});

test('the agenda sends the ten areas with their names, the documents one last', function (): void {
    $areas = agendaFor(boardFamily())['areas'];

    expect(array_column($areas, 'value'))->toBe(['home', 'vehicles', 'insurance', 'banks', 'health', 'school', 'work', 'subscriptions', 'paperwork', 'general'])
        ->and(array_column($areas, 'label'))->toBe(['Home', 'Vehicles', 'Insurance', 'Banks and loans', 'Health', 'School and activities', 'Work', 'Subscriptions', 'Paperwork', 'People and documents']);
});
