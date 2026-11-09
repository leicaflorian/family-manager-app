<?php

declare(strict_types=1);

use App\Data\Search\SearchResultData;
use App\Enums\ExceptionType;
use App\Enums\SearchGroup;
use App\Models\Employment;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Models\PlannedIncome;
use App\Services\Search\DueResults;
use App\Support\CurrentFamily;
use App\Support\SearchTerms;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Date::setTestNow(Date::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
    $this->family = Family::query()->firstOrFail();
});

afterEach(fn () => Date::setTestNow());

/**
 * The results found for some text, inside the family.
 *
 * @return list<SearchResultData>
 */
function dueResults(Family $family, string $text, int $limit = 10): array
{
    return CurrentFamily::using($family->id, fn (): array => resolve(DueResults::class)->results(SearchTerms::fromQuery($text), $limit));
}

it('belongs to the dues group', function (): void {
    expect(resolve(DueResults::class)->group())->toBe(SearchGroup::Dues);
});

it('finds the instalment of the loan by the card title, with its next due date', function (): void {
    $results = dueResults($this->family, 'Panda');
    $panda = array_values(array_filter($results, static fn (SearchResultData $r): bool => $r->subtitle === 'Finanziamento Panda'));

    expect($panda)->toHaveCount(1)
        ->and($panda[0]->due_in)->toBe('2026-11-10')
        ->and($panda[0]->amount)->toBe(189.0)
        ->and($panda[0]->date)->toBeNull()
        ->and($panda[0]->area)->toBe('banks')
        ->and($panda[0]->icon)->toBeNull()
        ->and($panda[0]->tone)->toBeNull()
        ->and($panda[0]->group)->toBe(SearchGroup::Dues)
        ->and($panda[0]->href)->toBe(route('budget.deadlines.show', ['kind' => 'expense', 'id' => explode('-', $panda[0]->key)[2], 'date' => '2026-11-10']));
});

it('finds a deadline by the organization name', function (): void {
    $titles = array_map(static fn (SearchResultData $r): string => $r->title, dueResults($this->family, 'UnipolSai'));

    expect($titles)->toContain('Assicurazione auto');
});

it('sends the icon and tone of a deadline without a card', function (): void {
    CurrentFamily::using($this->family->id, fn () => PlannedExpense::factory()->create([
        'family_id' => $this->family->id, 'title' => 'Zzz abbonamento palestra', 'amount' => 30, 'start_date' => '2026-11-05',
    ]));

    $rows = dueResults($this->family, 'Zzz');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->area)->toBeNull()
        ->and($rows[0]->icon)->toBe('calendar-clock')
        ->and($rows[0]->tone)->toBe('expenses')
        ->and($rows[0]->subtitle)->toBeNull();
});

it('shows a late unpaid due date with its past date', function (): void {
    CurrentFamily::using($this->family->id, fn () => PlannedExpense::factory()->create([
        'family_id' => $this->family->id, 'title' => 'Zzz tassa', 'amount' => 50, 'start_date' => '2026-09-15', 'is_recurring' => false,
    ]));

    $rows = dueResults($this->family, 'Zzz');

    expect($rows)->toHaveCount(1)->and($rows[0]->due_in)->toBe('2026-09-15');
});

it('leaves out an item with every due date paid', function (): void {
    CurrentFamily::using($this->family->id, function (): void {
        $planned = PlannedExpense::factory()->create([
            'family_id' => $this->family->id, 'title' => 'Zzz chiusa', 'amount' => 50, 'start_date' => '2026-08-15', 'end_date' => '2026-09-15',
        ]);

        foreach (['2026-08-15', '2026-09-15'] as $day) {
            Expense::factory()->create([
                'family_id' => $this->family->id, 'planned_expense_id' => $planned->id, 'occurrence_date' => $day, 'date' => $day, 'amount' => 50,
            ]);
        }
    });

    $search = resolve(DueResults::class);

    expect(dueResults($this->family, 'Zzz'))->toBe([])
        ->and(CurrentFamily::using($this->family->id, fn (): int => $search->count(SearchTerms::fromQuery('Zzz'))))->toBe(0);
});

it('finds a deadline by amount and by the day it falls on, and an income too', function (): void {
    CurrentFamily::using($this->family->id, function (): void {
        PlannedExpense::factory()->create(['family_id' => $this->family->id, 'title' => 'Zzz uno', 'amount' => 77.5, 'start_date' => '2026-11-05']);
        PlannedIncome::factory()->create(['family_id' => $this->family->id, 'title' => 'Zzz affitto', 'amount' => 500, 'start_date' => '2026-11-07']);
    });

    expect(array_map(static fn (SearchResultData $r): string => $r->title, dueResults($this->family, 'Zzz 77,50')))->toBe(['Zzz uno'])
        ->and(array_map(static fn (SearchResultData $r): string => $r->title, dueResults($this->family, 'Zzz 07/11/2026')))->toBe(['Zzz affitto']);

    $income = dueResults($this->family, 'affitto');
    expect($income[0]->tone)->toBe('income')
        ->and($income[0]->icon)->toBe('calendar-clock')
        ->and($income[0]->href)->toContain('/income/');
});

it('orders by due date, cuts after ordering and counts the same rows', function (): void {
    $search = resolve(DueResults::class);
    $terms = SearchTerms::fromQuery('a');

    $all = CurrentFamily::using($this->family->id, fn (): array => $search->results($terms, 100));
    $count = CurrentFamily::using($this->family->id, fn (): int => $search->count($terms));
    $dates = array_map(static fn (SearchResultData $r): ?string => $r->due_in, $all);
    $sorted = $dates;
    sort($sorted);

    expect($count)->toBe(count($all))->and($dates)->toBe($sorted)
        ->and(array_map(static fn (SearchResultData $r): string => $r->key, dueResults($this->family, 'a', 2)))->toBe(array_map(static fn (SearchResultData $r): string => $r->key, array_slice($all, 0, 2)));
});

it('does not find the deadlines of another family', function (): void {
    $other = Family::factory()->create();
    CurrentFamily::using($other->id, fn () => PlannedExpense::factory()->create([
        'family_id' => $other->id, 'title' => 'Zzz altrui', 'amount' => 10, 'start_date' => '2026-11-05',
        'organization_id' => Organization::factory()->create(['family_id' => $other->id])->id,
    ]));

    expect(dueResults($this->family, 'Zzz altrui'))->toBe([])
        ->and(array_map(static fn (SearchResultData $r): string => $r->title, dueResults($other, 'Zzz altrui')))->toBe(['Zzz altrui']);
});

it('skips a skipped due date and gives the next open one', function (): void {
    CurrentFamily::using($this->family->id, function (): void {
        $planned = PlannedExpense::factory()->create([
            'family_id' => $this->family->id, 'title' => 'Zzz saltata', 'amount' => 20, 'start_date' => '2026-11-05',
        ]);
        PlannedExpenseException::query()->create(['planned_expense_id' => $planned->id, 'type' => ExceptionType::Skip, 'occurrence_date' => '2026-11-05']);
    });

    $rows = dueResults($this->family, 'Zzz');

    expect($rows)->toHaveCount(1)->and($rows[0]->due_in)->toBe('2026-12-05');
});

it('gives the original date in the link and the moved date in due_in', function (): void {
    CurrentFamily::using($this->family->id, function (): void {
        $planned = PlannedExpense::factory()->create([
            'family_id' => $this->family->id, 'title' => 'Zzz spostata', 'amount' => 20, 'start_date' => '2026-11-05', 'is_recurring' => false,
        ]);
        PlannedExpenseException::query()->create([
            'planned_expense_id' => $planned->id, 'type' => ExceptionType::DateOverride, 'occurrence_date' => '2026-11-05', 'date_override' => '2026-11-12',
        ]);
    });

    $rows = dueResults($this->family, 'Zzz');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->due_in)->toBe('2026-11-12')
        ->and($rows[0]->href)->toEndWith('/occurrences/2026-11-05');
});

it('finds a planned income by the label of its employment', function (): void {
    CurrentFamily::using($this->family->id, function (): void {
        $employment = Employment::factory()->create(['family_id' => $this->family->id, 'person_id' => $this->family->people()->firstOrFail()->id]);
        PlannedIncome::factory()->create(['family_id' => $this->family->id, 'title' => 'Zzz stipendio', 'amount' => 1500, 'start_date' => '2026-11-27', 'employment_id' => $employment->id]);
    });

    $name = CurrentFamily::using($this->family->id, fn (): string => $this->family->people()->firstOrFail()->first_name);
    $rows = dueResults($this->family, $name.' Zzz');

    expect($rows)->toHaveCount(1)->and($rows[0]->title)->toBe('Zzz stipendio')->and($rows[0]->area)->toBe('work');
});

it('reads the planned items once for count then results on the same terms', function (): void {
    $search = resolve(DueResults::class);
    $terms = SearchTerms::fromQuery('Panda');

    CurrentFamily::using($this->family->id, function () use ($search, $terms): void {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $count = $search->count($terms);
        $afterCount = count(DB::getQueryLog());
        $rows = $search->results($terms, 10);

        expect(count(DB::getQueryLog()))->toBe($afterCount)->and(count($rows))->toBe($count);
        DB::disableQueryLog();
    });
});

it('recomputes for other terms', function (): void {
    $search = resolve(DueResults::class);

    CurrentFamily::using($this->family->id, function () use ($search): void {
        $panda = $search->count(SearchTerms::fromQuery('Panda'));
        $none = $search->count(SearchTerms::fromQuery('Qqqqq'));

        expect($panda)->toBeGreaterThan(0)->and($none)->toBe(0);
    });
});
