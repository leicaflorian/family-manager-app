<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Family;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    // The planned items of the tests carry the word "zzq", so the seeder's own never match
    insideBianchi(function (): void {
        foreach ([['Zzq uno', '2026-11-05', 19.99], ['ZZQ due', '2026-11-10', 20], ['zzq tre', '2026-12-01', 100], ['Altro zzq', '2026-11-20', 100.01]] as [$title, $start, $amount]) {
            PlannedExpense::factory()->create(['family_id' => CurrentFamily::id(), 'title' => $title, 'start_date' => $start, 'amount' => $amount]);
        }
        PlannedExpense::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Zzq finita', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'amount' => 30]);
        PlannedIncome::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Rimborso zzq', 'start_date' => '2026-11-15', 'amount' => 50]);
    });
});

/** Opens *Planned expenses* with an address query. */
function searchPlanned(string $query): TestResponse
{
    return test()->actingAs(test()->marco)->get('/budget/planned?'.$query);
}

/**
 * The titles of a list of rows of the page.
 *
 * @param  list<array{title: string}>  $rows
 * @return list<string>
 */
function plannedTitles(array $rows): array
{
    return array_column($rows, 'title');
}

it('finds the planned items whose title contains the text, in any case, in every list', function (): void {
    searchPlanned('search=zzq')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('expenses', fn ($rows): bool => plannedTitles($rows->all()) === ['Zzq uno', 'ZZQ due', 'Altro zzq', 'zzq tre'])
        ->where('incomes', fn ($rows): bool => plannedTitles($rows->all()) === ['Rimborso zzq'])
        ->where('ended', fn ($rows): bool => plannedTitles($rows->all()) === ['Zzq finita'])
        ->where('filters.search', 'zzq'));
});

it('keeps the monthly averages of all the planned items', function (): void {
    $all = searchPlanned('')->viewData('page')['props'];

    searchPlanned('search=zzq&min=20&max=20')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('expenses', 1)
        ->where('monthly_out', fn ($amount): bool => (float) $amount === (float) $all['monthly_out'])
        ->where('monthly_in', fn ($amount): bool => (float) $amount === (float) $all['monthly_in']));
});

it('keeps the amounts between min and max, both included, the ended ones too', function (): void {
    searchPlanned('search=zzq&min=20&max=100')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('expenses', fn ($rows): bool => plannedTitles($rows->all()) === ['ZZQ due', 'zzq tre'])
        ->where('incomes', fn ($rows): bool => plannedTitles($rows->all()) === ['Rimborso zzq'])
        ->where('ended', fn ($rows): bool => plannedTitles($rows->all()) === ['Zzq finita'])
        ->where('filters.min', 20)
        ->where('filters.max', 100));
});

it('keeps the next due dates between from and to, both included, and hides the ended ones', function (): void {
    searchPlanned('search=zzq&from=2026-11-05&to=2026-11-20')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('expenses', fn ($rows): bool => plannedTitles($rows->all()) === ['Zzq uno', 'ZZQ due', 'Altro zzq'])
        ->where('incomes', fn ($rows): bool => plannedTitles($rows->all()) === ['Rimborso zzq'])
        ->has('ended', 0)
        ->where('filters.from', '2026-11-05')
        ->where('filters.to', '2026-11-20'));
});

it('hides the ended ones with a first day alone', function (): void {
    searchPlanned('search=zzq&from=2026-11-21')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('expenses', fn ($rows): bool => plannedTitles($rows->all()) === ['zzq tre'])
        ->has('incomes', 0)
        ->has('ended', 0));
});

it('shows everything without filters, and only spaces is no search', function (): void {
    searchPlanned('search=%20%20')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('expenses', 16)
        ->has('incomes', 3)
        ->has('ended', 1)
        ->where('filters', ['search' => null, 'min' => null, 'max' => null, 'from' => null, 'to' => null]));
});

it('rejects wrong filters with an error on the field', function (string $query, string $field): void {
    searchPlanned($query)->assertSessionHasErrors($field);
})->with([
    'max under min' => ['min=50&max=10', 'max'],
    'to before from' => ['from=2026-11-10&to=2026-11-01', 'to'],
    'a negative amount' => ['min=-1', 'min'],
    'a day that is not a day' => ['from=10/11/2026', 'from'],
    'a text too long' => ['search='.str_repeat('a', 101), 'search'],
]);

it('lets a member search too', function (): void {
    $rosa = User::factory()->create(['role' => Role::Member, 'family_id' => $this->marco->family_id]);

    $this->actingAs($rosa)->get('/budget/planned?search=zzq')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('expenses', 4));
});

it('never finds the planned items of another family', function (): void {
    $other = Family::factory()->create();
    CurrentFamily::using($other->id, function () use ($other): void {
        PlannedExpense::factory()->create(['family_id' => $other->id, 'title' => 'Zzq altrui', 'start_date' => '2026-11-07', 'amount' => 30]);
        PlannedIncome::factory()->create(['family_id' => $other->id, 'title' => 'Zzq entrata altrui', 'start_date' => '2026-11-08', 'amount' => 40]);
    });

    searchPlanned('search=zzq')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('expenses', fn ($rows): bool => plannedTitles($rows->all()) === ['Zzq uno', 'ZZQ due', 'Altro zzq', 'zzq tre'])
        ->where('incomes', fn ($rows): bool => plannedTitles($rows->all()) === ['Rimborso zzq'])
        ->where('ended', fn ($rows): bool => plannedTitles($rows->all()) === ['Zzq finita']));
});
