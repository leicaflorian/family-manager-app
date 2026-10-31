<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Comparison;
use App\Services\ExpenseCategoryPaths;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
});

/** The comparison of a month as the Bianchi see it on 26/10/2026. */
function comparisonOf(string $month, ?User $user = null): array
{
    $user ??= User::query()->where('email', 'marco.bianchi@example.com')->sole();

    return test()->actingAs($user)
        ->withHeader('Accept-Language', 'it')
        ->get("/budget/comparison?month={$month}")
        ->assertOk()
        ->viewData('page')['props'];
}

it('compares October 2026', function (): void {
    $this->actingAs($this->marco)
        ->get('/budget/comparison?month=2026-10')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/Comparison')
            ->where('comparison.month', '2026-10')
            ->where('comparison.planned', 1469.3)
            ->where('comparison.spent', 1284.2)
            ->where('comparison.incomes', 1720)
            ->where('comparison.incomes_planned', 3570)
            ->where('comparison.days_left', 5));
});

it('answers the current month when no month is asked', function (): void {
    $this->actingAs($this->marco)->get('/budget/comparison')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('comparison.month', '2026-10'));
});

it('has no days left in a month that is not the current one', function (): void {
    $this->actingAs($this->marco)->get('/budget/comparison?month=2026-09')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('comparison.days_left', null));
});

it('answers 404 to a wrong month', function (): void {
    $this->actingAs($this->marco)->get('/budget/comparison?month=2026-13')->assertNotFound();
    $this->actingAs($this->marco)->get('/budget/comparison?month=october')->assertNotFound();
});

it('rolls subcategories up to their main one', function (): void {
    $categories = comparisonOf('2026-10')['comparison']['categories'];
    $utilities = collect($categories)->firstWhere('name', 'Bollette e utenze');

    expect($utilities['spent'])->toBe(29.9)
        ->and($utilities['planned'])->toBe(223.3)
        ->and($utilities['estimated'])->toBeFalse()
        ->and($utilities['late_title'])->toBe('Luce');
    // Luce, Gas and Acqua are not rows of their own
    expect(collect($categories)->pluck('name')->all())->not->toContain('Luce', 'Gas', 'Acqua');
});

it('counts an expense nobody planned', function (): void {
    $categories = collect(comparisonOf('2026-10')['comparison']['categories']);
    $health = $categories->firstWhere('name', 'Salute');

    expect($health['spent'])->toBe(108.4)
        ->and($health['planned'])->toBe(0.0);
});

it('puts an expense without a category in Senza categoria, last', function (): void {
    insideBianchi(fn (): Expense => Expense::query()->create([
        'title' => 'Mancia', 'date' => '2026-10-10', 'amount' => 5, 'category_id' => null,
    ]));

    $categories = comparisonOf('2026-10')['comparison']['categories'];
    $last = end($categories);

    expect($last['name'])->toBe('Senza categoria')
        ->and($last['spent'])->toBe(5.0)
        ->and($last['planned'])->toBe(0.0);
});

it('marks a category whose due dates are all estimated', function (): void {
    $categories = collect(comparisonOf('2026-10')['comparison']['categories']);

    expect($categories->firstWhere('name', 'Spesa')['estimated'])->toBeTrue()
        ->and($categories->firstWhere('name', 'Casa e affitto')['estimated'])->toBeFalse();
});

it('sums the main categories to the totals of the month', function (): void {
    $comparison = comparisonOf('2026-10')['comparison'];

    expect(round(collect($comparison['categories'])->sum('planned'), 2))->toBe($comparison['planned'])
        ->and(round(collect($comparison['categories'])->sum('spent'), 2))->toBe($comparison['spent']);
});

it('lists the unpaid due dates of the month, late ones first', function (): void {
    $unpaid = comparisonOf('2026-10')['comparison']['unpaid'];

    expect($unpaid[0])->toMatchArray(['title' => 'Bolletta luce di settembre', 'date' => '2026-10-23', 'late' => true, 'amount' => 71.3])
        ->and(collect($unpaid)->pluck('kind')->unique()->all())->toBe(['expense'])
        ->and(collect($unpaid)->every(fn (array $row): bool => $row['payment'] === null))->toBeTrue();
});

it('has the last six months with the current one marked', function (): void {
    $months = comparisonOf('2026-10')['comparison']['months'];

    expect(collect($months)->pluck('month')->all())->toBe(['2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'])
        ->and(collect($months)->pluck('current')->all())->toBe([false, false, false, false, false, true])
        ->and($months[5])->toMatchArray(['spent' => 1284.2, 'planned' => 1469.3]);
});

it('ends the six months on the month asked', function (): void {
    $months = comparisonOf('2026-03')['comparison']['months'];

    expect($months[0]['month'])->toBe('2025-10')->and($months[5]['month'])->toBe('2026-03')
        ->and(collect($months)->pluck('current')->unique()->all())->toBe([false]);
});

it('lets a member look at it', function (): void {
    $rosa = User::factory()->create(['role' => Role::Member, 'family_id' => $this->marco->family_id]);

    $this->actingAs($rosa)->get('/budget/comparison')->assertOk();
});

it('never mixes the categories of another family', function (): void {
    $other = User::factory()->create();
    CarbonImmutable::setTestNow('2026-10-26 10:00');

    $this->actingAs($other)->get('/budget/comparison?month=2026-10')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('comparison.planned', 0)->where('comparison.spent', 0)->where('comparison.categories', []));
});

it('is computed by the Comparison service for a day in the month', function (): void {
    $today = CarbonImmutable::parse('2026-10-26', 'Europe/Rome');
    $data = insideBianchi(fn () => resolve(Comparison::class)->of($today->startOfMonth(), $today));

    expect($data->planned)->toBe(1469.3)->and($data->days_left)->toBe(5);
});

it('keeps the spending of a category whose parents loop in No category', function (): void {
    insideBianchi(function (): void {
        $first = ExpenseCategory::query()->create(['name' => 'Giro A']);
        $second = ExpenseCategory::query()->create(['name' => 'Giro B', 'parent_id' => $first->id]);
        $first->update(['parent_id' => $second->id]);
        Expense::query()->create(['title' => 'Misteri', 'date' => '2026-10-11', 'amount' => 7, 'category_id' => $second->id]);
    });

    $comparison = comparisonOf('2026-10')['comparison'];
    $last = collect($comparison['categories'])->last();

    expect($last['name'])->toBe('Senza categoria')->and($last['spent'])->toBe(7.0)
        ->and(round(collect($comparison['categories'])->sum('spent'), 2))->toBe($comparison['spent']);
});

it('walks the expense categories once for their paths and their main categories', function (): void {
    insideBianchi(function (): void {
        $home = ExpenseCategory::query()->create(['name' => 'Casa prova']);
        $gas = ExpenseCategory::query()->create(['name' => 'Gas prova', 'parent_id' => $home->id]);
        $first = ExpenseCategory::query()->create(['name' => 'Giro A']);
        $second = ExpenseCategory::query()->create(['name' => 'Giro B', 'parent_id' => $first->id]);
        $first->update(['parent_id' => $second->id]);

        $tree = ExpenseCategoryPaths::walk(ExpenseCategory::query()->get());

        expect($tree[$gas->id])->toBe(['path' => 'Casa prova › Gas prova', 'main' => $home->id])
            ->and($tree[$home->id])->toBe(['path' => 'Casa prova', 'main' => $home->id])
            ->and($tree)->not->toHaveKey($first->id)
            ->and($tree)->not->toHaveKey($second->id)
            ->and(ExpenseCategoryPaths::all()[$gas->id])->toBe('Casa prova › Gas prova');
    });
});
