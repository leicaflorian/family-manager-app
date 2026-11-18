<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\Income;
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
    // The movements of the tests carry the word "zzq", so the seeder's own never match
    insideBianchi(function (): void {
        foreach ([['Zzq uno', '2026-10-05', 19.99], ['ZZQ due', '2026-09-20', 20], ['zzq tre', '2026-08-02', 100], ['Altro zzq', '2026-10-12', 100.01]] as [$title, $date, $amount]) {
            Expense::factory()->create(['family_id' => CurrentFamily::id(), 'title' => $title, 'date' => $date, 'amount' => $amount]);
        }
        Income::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Rimborso zzq', 'date' => '2026-10-20', 'amount' => 50]);
    });
});

/** Opens *Movements* with an address query. */
function searchMovements(string $query): TestResponse
{
    return test()->actingAs(test()->marco)->get('/budget/movements?'.$query);
}

it('finds the movements whose title contains the text, in any case and in more than one month', function (): void {
    searchMovements('search=zzq')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search.count', 5)
        ->where('search.months.0.month', '2026-10')
        ->where('search.months.1.month', '2026-09')
        ->where('search.months.2.month', '2026-08')
        ->has('search.months.0.movements', 3)
        ->where('search.months.0.movements.0.title', 'Rimborso zzq')
        ->where('filters.search', 'zzq'));
});

it('sums the shown movements, the incomes up and the expenses down', function (): void {
    searchMovements('search=zzq')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search.total', fn ($total): bool => abs($total - (50 - 19.99 - 20 - 100 - 100.01)) < 0.001)
        ->where('search.months.0.total', fn ($total): bool => abs($total - (50 - 19.99 - 100.01)) < 0.001));

    searchMovements('search=zzq&kind=expense')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search.count', 4)
        ->where('search.total', fn ($total): bool => abs($total - 240) < 0.001));
});

it('keeps the amounts between min and max, both included', function (): void {
    searchMovements('search=zzq&min=20&max=100')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search.count', 3)
        ->where('filters.min', 20)
        ->where('filters.max', 100));
});

it('keeps the days between from and to, both included', function (): void {
    searchMovements('search=zzq&from=2026-09-20&to=2026-10-05')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search.count', 2)
        ->where('filters.from', '2026-09-20'));
});

it('searches on amounts or days alone', function (): void {
    searchMovements('min=100&from=2026-10-01')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search.months', fn ($months): bool => collect($months)->flatMap(fn ($month): mixed => $month['movements'])->every(fn ($movement): bool => $movement['amount'] >= 100 && $movement['date'] >= '2026-10-01')
            && collect($months)->flatMap(fn ($month): mixed => $month['movements'])->contains('title', 'Altro zzq')));
});

it('narrows by account in search mode', function (): void {
    $account = insideBianchi(fn (): FinancialAccount => FinancialAccount::query()->firstOrFail());
    insideBianchi(fn () => Expense::query()->where('title', 'zzq tre')->update(['financial_account_id' => $account->id]));

    searchMovements('search=zzq&account='.$account->id)->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search.count', 1)
        ->where('filters.account', $account->id));
});

it('treats % and _ in the text as plain characters', function (): void {
    searchMovements('search='.urlencode('%'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search.count', 0)
        ->where('search.total', 0));
});

it('stays in month mode when the text is only spaces', function (): void {
    searchMovements('search=%20%20&month=2026-10')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search', null)
        ->where('filters.search', null)
        ->has('summary')
        ->has('movements'));
});

it('has no search without filters', function (): void {
    searchMovements('month=2026-10')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search', null)
        ->where('filters.kind', null));
});

it('rejects max under min and to before from with 422', function (): void {
    searchMovements('min=50&max=10')->assertSessionHasErrors('max');
    searchMovements('from=2026-10-10&to=2026-10-01')->assertSessionHasErrors('to');
});

it('answers 404 for an account of another family', function (): void {
    $other = Family::factory()->create();
    $account = CurrentFamily::using($other->id, fn (): FinancialAccount => FinancialAccount::factory()->create(['family_id' => $other->id]));

    searchMovements('search=zzq&account='.$account->id)->assertNotFound();
});

it('shows at most 200 movements and counts them all', function (): void {
    insideBianchi(function (): void {
        Expense::factory()->count(201)->create(['family_id' => CurrentFamily::id(), 'title' => 'Zzq tanti', 'date' => '2026-10-01', 'amount' => 1]);
    });

    searchMovements('search=zzq')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search.count', 206)
        ->where('search.total', fn ($total): bool => abs($total - (50 - 19.99 - 20 - 100 - 100.01 - 201)) < 0.001)
        ->where('search.months', fn ($months): bool => collect($months)->sum(fn ($month): int => count($month['movements'])) === 200));
});

it('lets a member search too', function (): void {
    $rosa = User::factory()->create(['role' => Role::Member, 'family_id' => $this->marco->family_id]);

    $this->actingAs($rosa)->get('/budget/movements?search=zzq')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('search.count', 5));
});

it('never finds the movements of another family', function (): void {
    $other = Family::factory()->create();
    CurrentFamily::using($other->id, function () use ($other): void {
        Expense::factory()->create(['family_id' => $other->id, 'title' => 'Zzq altrui', 'date' => '2026-10-15', 'amount' => 30]);
        Income::factory()->create(['family_id' => $other->id, 'title' => 'Zzq entrata altrui', 'date' => '2026-10-16', 'amount' => 40]);
    });

    searchMovements('search=zzq')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search.count', 5)
        ->where('search.total', fn ($total): bool => abs($total - (50 - 19.99 - 20 - 100 - 100.01)) < 0.001)
        ->where('search.months', fn ($months): bool => collect($months)->flatMap(fn ($month): mixed => $month['movements'])->doesntContain(fn ($movement): bool => str_contains((string) $movement['title'], 'altrui'))));
});
