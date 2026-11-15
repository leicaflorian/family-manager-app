<?php

declare(strict_types=1);

use App\Data\AgendaEntryData;
use App\Enums\DocumentStatus;
use App\Enums\ExceptionType;
use App\Enums\FinancialAccountType;
use App\Enums\ReminderStatus;
use App\Enums\VehicleStatus;
use App\Models\Document;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Models\PlannedIncome;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Agenda;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
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
 * What the old Agenda showed: the late deadlines and the entries of today and after, without the late payments.
 *
 * @param  array<string, mixed>  $agenda  the props of the Agenda
 * @return list<array<string, mixed>>
 */
function agendaUpcoming(array $agenda): array
{
    return array_values(array_filter($agenda['entries'], static fn (array $entry): bool => $entry['kind'] !== 'payment' || $entry['date'] >= $agenda['from']));
}

/**
 * How many things to do are late, as the page counts them: the entries before `from`.
 *
 * @param  array<string, mixed>  $agenda  the props of the Agenda
 */
function agendaLate(array $agenda): int
{
    return count(array_filter($agenda['entries'], static fn (array $entry): bool => $entry['date'] < $agenda['from']));
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

test('the agenda of one month has the entries of the board in its order, without the deadline already done', function (): void {
    $agenda = agendaFor(boardFamily());

    expect($agenda['months'])->toBe(1)
        ->and($agenda['from'])->toBe('2026-10-26')
        ->and($agenda['to'])->toBe('2026-11-26')
        ->and(agendaColumns(agendaUpcoming($agenda), ['kind', 'date', 'title', 'subtitle', 'person', 'amount']))->toBe([
            ['kind' => 'deadline', 'date' => '2026-09-30', 'title' => 'Tessera sanitaria di Giulia', 'subtitle' => 'Document of Giulia', 'person' => 'Giulia', 'amount' => null],
            ['kind' => 'payment', 'date' => '2026-10-30', 'title' => 'Bolletta gas', 'subtitle' => 'Gas - Enel', 'person' => 'Marco', 'amount' => 84.2],
            ['kind' => 'payment', 'date' => '2026-10-31', 'title' => 'Bolletta acqua', 'subtitle' => 'Water - Hera', 'person' => 'Marco', 'amount' => 37.9],
            ['kind' => 'deadline', 'date' => '2026-11-04', 'title' => 'End of the free trial', 'subtitle' => 'Netflix', 'person' => 'Laura', 'amount' => null],
            ['kind' => 'payment', 'date' => '2026-11-04', 'title' => 'Primo pagamento', 'subtitle' => 'Netflix', 'person' => 'Laura', 'amount' => 13.99],
            ['kind' => 'payment', 'date' => '2026-11-06', 'title' => 'Bolletta luce di ottobre', 'subtitle' => 'Electricity - Edison', 'person' => 'Marco', 'amount' => 62.4],
            ['kind' => 'payment', 'date' => '2026-11-10', 'title' => 'Rata 33 di 48', 'subtitle' => 'Finanziamento Panda', 'person' => 'Marco', 'amount' => 189.0],
            ['kind' => 'payment', 'date' => '2026-11-15', 'title' => 'Rata mutuo', 'subtitle' => 'Mutuo casa', 'person' => 'Marco', 'amount' => 612.0],
            ['kind' => 'payment', 'date' => '2026-11-21', 'title' => 'Policy renewal', 'subtitle' => 'RC auto Panda - UnipolSai', 'person' => 'Marco', 'amount' => 418.0],
        ]);

    $payments = array_filter(agendaUpcoming($agenda), static fn (array $entry): bool => $entry['kind'] === 'payment');
    $byArea = array_count_values(array_column(agendaUpcoming($agenda), 'area'));
    ksort($byArea);

    expect(count($payments))->toBe(7)
        ->and(round(array_sum(array_column($payments, 'amount')), 2))->toBe(1417.49)
        ->and($byArea)->toBe(['banks' => 2, 'general' => 1, 'home' => 3, 'insurance' => 1, 'subscriptions' => 2])
        // The identity card of Marco has its reminder done: it is in the done view
        ->and(array_column($agenda['entries'], 'title'))->not->toContain("Scade la carta d'identità")
        ->and(collect($agenda['entries'])->firstWhere('title', 'Tessera sanitaria di Giulia')['reminder_done'])->toBeFalse()
        ->and(array_column($agenda['people'], 'first_name'))->toBe(['Marco', 'Laura', 'Giulia'])
        ->and($agenda['filters'])->toBe(['months' => 1, 'person_id' => null, 'hidden_areas' => [], 'calendar' => false]);
});

test('each entry names the thing first and, below, what happens to it', function (): void {
    $entries = agendaUpcoming(agendaFor(boardFamily()));

    expect(agendaColumns($entries, ['name', 'detail']))->toBe([
        ['name' => 'Tessera sanitaria di Giulia', 'detail' => 'Document expiry'],
        ['name' => 'Gas - Enel', 'detail' => 'Bolletta gas'],
        ['name' => 'Water - Hera', 'detail' => 'Bolletta acqua'],
        ['name' => 'Netflix', 'detail' => 'End of the free trial'],
        ['name' => 'Netflix', 'detail' => 'Primo pagamento'],
        ['name' => 'Electricity - Edison', 'detail' => 'Bolletta luce di ottobre'],
        ['name' => 'Finanziamento Panda', 'detail' => 'Rata 33 di 48'],
        ['name' => 'Mutuo casa', 'detail' => 'Rata mutuo'],
        ['name' => 'RC auto Panda - UnipolSai', 'detail' => 'Policy renewal'],
    ]);
});

test('a payment titled as its card does not say the name twice', function (): void {
    $marco = boardFamily();
    insideFamilyOf($marco, function (): void {
        $netflix = Subscription::query()->where('name', 'Netflix')->sole();
        PlannedExpense::query()->whereMorphedTo('card', $netflix)->update(['title' => 'Netflix']);
    });

    $netflix = collect(agendaFor($marco)['entries'])->first(static fn (array $entry): bool => $entry['kind'] === 'payment' && $entry['name'] === 'Netflix');

    expect($netflix['detail'])->toBe('Payment');
});

test('a payment without a card is named by its title, with the organization below', function (): void {
    $marco = boardFamily();
    insideFamilyOf($marco, fn (): PlannedExpense => PlannedExpense::query()->create([
        'title' => 'Tassa rifiuti',
        'amount' => 120,
        'is_recurring' => false,
        'start_date' => '2026-11-02',
        'organization_id' => Organization::query()->create(['name' => 'Comune di Bologna'])->id,
    ]));

    $entry = collect(agendaFor($marco)['entries'])->firstWhere('title', 'Tassa rifiuti');

    expect($entry['name'])->toBe('Tassa rifiuti')
        ->and($entry['detail'])->toBe('Comune di Bologna');
});

test('a deadline carries the state of its automatic reminder and its next notice', function (): void {
    $marco = boardFamily();
    // The reminder of the health card was born before its day passed: the sync makes none for a past day
    insideFamilyOf($marco, function (): void {
        $card = Document::query()->where('title', 'Tessera sanitaria di Giulia')->sole();
        $reminder = Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'is_automatic' => true, 'source_field' => 'documents.expiry_date', 'due_date' => '2026-09-30', 'status' => ReminderStatus::Open]);
        DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => $card->getMorphClass(), 'remindable_id' => $card->id]);
    });
    $entries = collect(agendaFor($marco)['entries']);
    $by = static fn (string $name, string $detail): array => $entries->first(static fn (array $entry): bool => $entry['name'] === $name && $entry['detail'] === $detail);

    expect(Arr::only($by('Tessera sanitaria di Giulia', 'Document expiry')['reminder'], ['state', 'next_notice']))->toBe(['state' => 'late', 'next_notice' => null])
        // A deadline whose reminder is done is not among the things to do
        ->and($entries->pluck('name'))->not->toContain("Scade la carta d'identità")
        ->and($by('Netflix', 'End of the free trial')['reminder']['state'])->toBe('open')
        ->and($by('Netflix', 'End of the free trial')['reminder']['next_notice'])->toBeString()
        ->and($by('Gas - Enel', 'Bolletta gas')['reminder'])->toBeNull();
});

test('a deadline whose reminder is done leaves the things to do for the done view', function (): void {
    $marco = boardFamily();
    // The reminder of the late health card, born before its day passed
    $reminder = insideFamilyOf($marco, function (): Reminder {
        $card = Document::query()->where('title', 'Tessera sanitaria di Giulia')->sole();
        $reminder = Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'is_automatic' => true, 'source_field' => 'documents.expiry_date', 'due_date' => '2026-09-30', 'status' => ReminderStatus::Open]);
        DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => $card->getMorphClass(), 'remindable_id' => $card->id]);

        return $reminder;
    });
    $before = agendaFor($marco);

    $this->actingAs($marco)->post(route('reminders.done', $reminder))->assertRedirect();

    $after = agendaFor($marco);
    $done = $this->actingAs($marco)->get('/agenda?view=done')->viewData('page')['props']['done'];

    expect(array_column($before['entries'], 'name'))->toContain('Tessera sanitaria di Giulia')
        ->and(array_column($after['entries'], 'name'))->not->toContain('Tessera sanitaria di Giulia')
        ->and(array_column($after['entries'], 'name'))->not->toContain("Scade la carta d'identità")
        ->and(agendaLate($after))->toBe(agendaLate($before) - 1)
        ->and(array_column($done, 'key'))->toContain('reminder:'.$reminder->id);
});

test('the keys and the addresses of the entries', function (): void {
    $marco = boardFamily();
    $entries = collect(agendaFor($marco)['entries'])->keyBy('title');
    [$gas, $healthCard, $netflix] = insideFamilyOf($marco, fn (): array => [
        PlannedExpense::query()->where('title', 'Bolletta gas')->sole(),
        Document::query()->where('expiry_date', '2026-09-30')->sole(),
        Subscription::query()->where('name', 'Netflix')->sole(),
    ]);

    expect($entries['Bolletta gas']['key'])->toBe("payment:{$gas->id}:2026-10-30")
        ->and($entries['Bolletta gas']['href'])->toBe(route('budget.deadlines.show', ['kind' => 'expense', 'id' => $gas->id, 'date' => '2026-10-30']).'?pay=1')
        ->and($entries['Tessera sanitaria di Giulia']['key'])->toBe("deadline:documents.expiry_date:{$healthCard->id}:2026-09-30")
        ->and($entries['Tessera sanitaria di Giulia']['href'])->toBe(route('documents.show', $healthCard))
        ->and($entries['End of the free trial']['key'])->toBe("deadline:subscriptions.trial_end_date:{$netflix->id}:2026-11-04")
        ->and($entries['End of the free trial']['href'])->toBe(route('cards.show', ['type' => 'subscription', 'id' => $netflix->id]))
        ->and($entries['End of the free trial']['person_id'])->toBe($netflix->person_id);
});

test('an unpaid payment of yesterday is late in the agenda', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, fn (): PlannedExpense => agendaPlanned(null, 'Bolletta luce', 50, '2026-10-25', ['is_recurring' => false]));

    expect(array_column(agendaFor($marco)['entries'], 'date'))->toBe(['2026-10-25']);

    // The Home and the calendar keep their own window: 0 days back by default, 30 for them
    $today = CarbonImmutable::today('Europe/Rome');
    $until = CarbonImmutable::parse('2026-11-26', 'Europe/Rome');
    expect(insideFamilyOf($marco, fn (): array => resolve(Agenda::class)->entries($today, $until)))->toBe([]);
    $entries = insideFamilyOf($marco, fn (): array => resolve(Agenda::class)->entries($today, $until, paymentsBack: 30));
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
        ->and($entries['Tessera sanitaria di Giulia']['area'])->toBe('general');
});

test('a payment without a card is general', function (): void {
    $marco = registerFamily();
    insideFamilyOf($marco, function (): void {
        agendaPlanned(null, 'Regalo', 30, '2026-11-02', ['is_recurring' => false, 'organization_id' => Organization::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Negozio'])->id]);
    });

    expect(agendaColumns(agendaFor($marco)['entries'], ['title', 'subtitle', 'area', 'person']))->toBe([
        ['title' => 'Regalo', 'subtitle' => 'Negozio', 'area' => 'general', 'person' => null],
    ]);
});

test('a paid due date leaves the agenda', function (): void {
    $marco = boardFamily();
    insideFamilyOf($marco, function (): void {
        $gas = PlannedExpense::query()->where('title', 'Bolletta gas')->sole();
        Expense::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $gas->id, 'occurrence_date' => '2026-10-30', 'amount' => 84.20, 'date' => '2026-10-26']);
    });

    expect(array_column(array_filter(agendaFor($marco)['entries'], static fn (array $entry): bool => $entry['date'] === '2026-10-30'), 'title'))->not->toContain('Bolletta gas');
});

test('a skipped due date is not in the agenda', function (): void {
    $marco = boardFamily();
    insideFamilyOf($marco, function (): void {
        $gas = PlannedExpense::query()->where('title', 'Bolletta gas')->sole();
        PlannedExpenseException::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $gas->id, 'type' => ExceptionType::Skip, 'occurrence_date' => '2026-10-30']);
    });

    expect(array_column(array_filter(agendaFor($marco)['entries'], static fn (array $entry): bool => $entry['date'] === '2026-10-30'), 'title'))->not->toContain('Bolletta gas');
});

test('more months show more entries', function (): void {
    $marco = boardFamily();
    $three = agendaFor($marco, '?months=3');

    expect($three['months'])->toBe(3)
        ->and($three['to'])->toBe('2027-01-26')
        ->and(count($three['entries']))->toBeGreaterThan(count(agendaUpcoming(agendaFor($marco))));
});

test('an unknown months value counts as one month', function (string $query): void {
    $agenda = agendaFor(boardFamily(), $query);

    expect($agenda['months'])->toBe(1)
        ->and($agenda['to'])->toBe('2026-11-26')
        ->and(agendaUpcoming($agenda))->toHaveCount(9);
})->with(['?months=2', '?months=abc', '?months=', '?months[]=3']);

test('a member sees the agenda', function (): void {
    $marco = boardFamily();
    $member = User::factory()->create(['family_id' => $marco->family_id]);

    $this->actingAs($member)->get('/agenda')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Agenda')->has('agenda.entries'));
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

test('the agenda sends the periods it offers', function (): void {
    expect(agendaFor(boardFamily())['periods'])->toBe([1, 3, 6, 12]);
});

test('the filters accept every area the agenda sends and nothing else', function (): void {
    $marco = boardFamily();
    $values = array_column(agendaFor($marco)['areas'], 'value');
    $payload = fn (array $areas): array => ['months' => 1, 'person_id' => null, 'hidden_areas' => $areas];

    $this->actingAs($marco)->put('/agenda/filters', $payload($values))->assertSessionHasNoErrors();
    $this->actingAs($marco)->put('/agenda/filters', $payload(['nowhere']))->assertSessionHasErrors('hidden_areas.0');
});

test('the agenda has no incomes', function (): void {
    $agenda = agendaFor(boardFamily(), '?months=3');

    expect($agenda['entries'])->not->toBeEmpty()
        ->and(array_column($agenda['entries'], 'kind'))->not->toContain('income');
});

test('the agenda keeps an unpaid payment for 90 days', function (): void {
    $marco = boardFamily();
    insideFamilyOf($marco, function (): void {
        agendaPlanned(null, 'Tre settimane fa', 10.0, '2026-10-15', ['is_recurring' => false]);
        agendaPlanned(null, 'Tre mesi fa', 10.0, '2026-07-20', ['is_recurring' => false]);
    });

    $titles = array_column(agendaFor($marco)['entries'], 'title');

    expect($titles)->toContain('Tre settimane fa')->not->toContain('Tre mesi fa');
});

test('the agenda counts what is late on the page, from the entries', function (): void {
    $agenda = agendaFor(boardFamily());

    expect(agendaLate($agenda))->toBeGreaterThan(0)
        ->and($agenda)->not->toHaveKey('overdue')
        ->and($agenda['view'])->toBe('todo')
        ->and($agenda)->not->toHaveKeys(['done', 'done_next_page']);
});

test('the agenda sends the notices of a deadline reminder', function (): void {
    $this->seed(BianchiFamilySeeder::class);
    $marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();

    $entries = agendaFor($marco, '?months=3')['entries'];
    $entry = collect($entries)->first(static fn (array $row): bool => $row['kind'] === 'deadline' && $row['reminder'] !== null && $row['reminder']['state'] !== 'done');

    expect($entry)->not->toBeNull()
        ->and($entry['reminder']['id'])->toBeInt()
        ->and($entry['reminder']['notice_days'])->not->toBeEmpty()
        ->and($entry['reminder']['recipients'])->toContain('Marco', 'Laura')
        ->and($entry['reminder']['recipients'])->not->toContain('Marco Bianchi');
});

test('a payment says its account and how its planned expense repeats', function (): void {
    $marco = boardFamily();
    insideFamilyOf($marco, function (): void {
        FinancialAccount::query()->create(['name' => 'Conto BPER', 'type' => FinancialAccountType::CheckingAccount, 'is_default' => true]);
        $card = FinancialAccount::query()->create(['name' => 'Carta Laura', 'type' => FinancialAccountType::Card]);
        PlannedExpense::query()->create([
            'title' => 'Gas - Hera',
            'amount' => 84.2,
            'is_recurring' => true,
            'interval' => 2,
            'interval_unit' => 'month',
            'start_date' => '2026-11-02',
        ]);
        PlannedExpense::query()->create(['title' => 'Palestra', 'amount' => 30, 'is_recurring' => false, 'start_date' => '2026-11-03', 'financial_account_id' => $card->id]);
    });
    $entries = collect(agendaFor($marco)['entries']);

    expect($entries->firstWhere('title', 'Gas - Hera'))
        ->toMatchArray(['account' => 'Conto BPER', 'planned' => 'Gas - Hera, every 2 months'])
        ->and($entries->firstWhere('title', 'Palestra')['account'])->toBe('Carta Laura')
        ->and($entries->firstWhere('kind', 'deadline')['account'])->toBeNull()
        ->and($entries->firstWhere('kind', 'deadline')['planned'])->toBeNull();
});

test('a payment of one time says it is once', function (): void {
    $marco = boardFamily();
    insideFamilyOf($marco, fn (): PlannedExpense => PlannedExpense::query()->create([
        'title' => 'Tassa rifiuti',
        'amount' => 120,
        'is_recurring' => false,
        'start_date' => '2026-11-02',
    ]));

    expect(collect(agendaFor($marco)['entries'])->firstWhere('title', 'Tassa rifiuti')['planned'])->toBe('Tassa rifiuti, once, on 02/11/2026');
});

/**
 * A partial reload of the Agenda with only the props of *I paid it*, as the panel asks for them.
 *
 * @param  User  $user  the account
 * @param  string  $key  the key of the entry, for example `payment:12:2026-10-25`
 */
function agendaPaymentReload(User $user, string $key): TestResponse
{
    $version = test()->actingAs($user)->get('/agenda')->viewData('page')['version'];

    return test()->actingAs($user)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => 'Agenda',
        'X-Inertia-Partial-Data' => 'payment,accounts',
    ])->get('/agenda?payment='.urlencode($key));
}

it('sends a payment on request', function (): void {
    $marco = registerFamily();
    $planned = insideFamilyOf($marco, function (): PlannedExpense {
        FinancialAccount::query()->create(['name' => 'Conto BPER', 'type' => FinancialAccountType::CheckingAccount, 'is_default' => true]);

        return agendaPlanned(null, 'Bolletta luce', 50, '2026-10-25', ['is_recurring' => false]);
    });
    $key = "payment:{$planned->id}:2026-10-25";

    $this->actingAs($marco)->get('/agenda?payment='.urlencode($key))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Agenda')
            ->missing('payment')
            ->missing('accounts')
            ->reloadOnly(['payment', 'accounts'], fn (AssertableInertia $reload): AssertableInertia => $reload
                ->where('payment.planned_id', $planned->id)
                ->where('payment.original_date', '2026-10-25')
                ->where('payment.kind', 'expense')
                ->where('payment.amount', 50)
                ->where('accounts.0.label', 'Conto BPER')
                ->missing('agenda')));
});

it('sends the due date of an income on request', function (): void {
    $marco = registerFamily();
    $planned = insideFamilyOf($marco, fn (): PlannedIncome => PlannedIncome::factory()->create([
        'family_id' => CurrentFamily::id(), 'title' => 'Stipendio di Marco', 'amount' => 1850, 'start_date' => '2026-10-27', 'is_recurring' => false,
    ]));

    agendaPaymentReload($marco, "income:{$planned->id}:2026-10-27")
        ->assertOk()
        ->assertJsonPath('props.payment.kind', 'income')
        ->assertJsonPath('props.payment.planned_id', $planned->id)
        ->assertJsonPath('props.payment.original_date', '2026-10-27');
});

it('answers 404 to a payment of another family or a wrong key', function (string $key): void {
    $marco = registerFamily();
    $ours = insideFamilyOf($marco, fn (): PlannedExpense => agendaPlanned(null, 'Bolletta luce', 50, '2026-10-25', ['is_recurring' => false]));
    $rossi = registerFamily('Rossi', 'mario@example.com');
    $theirs = insideFamilyOf($rossi, fn (): PlannedExpense => agendaPlanned(null, 'Affitto', 700, '2026-10-25', ['is_recurring' => false]));

    agendaPaymentReload($marco, str_replace(['{rossi}', '{marco}'], [(string) $theirs->id, (string) $ours->id], $key))->assertNotFound();
})->with([
    'another family' => ['payment:{rossi}:2026-10-25'],
    'not a payment' => ['deadline:documents.expiry_date:1:2026-10-25'],
    // A payment of the family on a day that cannot exist
    'a wrong day' => ['payment:{marco}:2026-13-45'],
]);
