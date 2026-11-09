<?php

declare(strict_types=1);

use App\Data\Search\SearchResultData;
use App\Enums\IncomeType;
use App\Enums\SearchGroup;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Income;
use App\Models\Organization;
use App\Models\Person;
use App\Services\Search\MovementResults;
use App\Support\CurrentFamily;
use App\Support\SearchTerms;

beforeEach(function (): void {
    $this->family = Family::factory()->create();
});

/**
 * The results found for some text, inside the family.
 *
 * @return list<SearchResultData>
 */
function movementResults(Family $family, string $text, int $limit = 10): array
{
    return CurrentFamily::using($family->id, fn (): array => resolve(MovementResults::class)->results(SearchTerms::fromQuery($text), $limit));
}

/**
 * The titles found for some text.
 *
 * @return list<string>
 */
function movementTitles(Family $family, string $text, int $limit = 10): array
{
    return array_map(static fn (SearchResultData $result): string => $result->title, movementResults($family, $text, $limit));
}

/**
 * Makes an expense in the family of the test.
 *
 * @param  array<string, mixed>  $attributes  attributes of the expense
 */
function searchExpense(Family $family, string $title, string $date, float|string $amount, array $attributes = []): Expense
{
    return CurrentFamily::using($family->id, fn (): Expense => Expense::factory()->create([
        'family_id' => $family->id, 'title' => $title, 'date' => $date, 'amount' => $amount, ...$attributes,
    ]));
}

/**
 * Makes an income in the family of the test.
 *
 * @param  array<string, mixed>  $attributes  attributes of the income
 */
function searchIncome(Family $family, ?string $title, string $date, float|string $amount, array $attributes = []): Income
{
    return CurrentFamily::using($family->id, fn (): Income => Income::factory()->create([
        'family_id' => $family->id, 'title' => $title, 'date' => $date, 'amount' => $amount, ...$attributes,
    ]));
}

it('finds the expenses by title, the most recent first, with the amount', function (): void {
    searchExpense($this->family, 'Tagliando Panda', '2026-03-10', 180);
    searchExpense($this->family, 'Gomme invernali Panda', '2026-10-02', 420.5);
    searchExpense($this->family, 'Bollo Panda', '2026-01-31', 195.2);
    searchExpense($this->family, 'Spesa al supermercato', '2026-10-20', 60);

    $results = movementResults($this->family, 'Panda');

    expect(array_map(static fn (SearchResultData $r): string => $r->title, $results))
        ->toBe(['Gomme invernali Panda', 'Tagliando Panda', 'Bollo Panda'])
        ->and($results[0]->amount)->toBe(420.5)
        ->and($results[0]->date)->toBe('2026-10-02')
        ->and($results[0]->group)->toBe(SearchGroup::Movements)
        ->and($results[0]->subtitle)->toBe('Expense')
        ->and($results[0]->icon)->toBe('receipt')
        ->and($results[0]->tone)->toBe('expenses')
        ->and($results[0]->area)->toBeNull();
});

it('gives each row its key and the address of the movement', function (): void {
    $expense = searchExpense($this->family, 'Tagliando Panda', '2026-03-10', 180);
    $income = searchIncome($this->family, 'Rimborso Panda', '2026-04-10', 50);

    $rows = collect(movementResults($this->family, 'Panda'))->keyBy('key');

    expect($rows['expense-'.$expense->id]->href)->toBe(route('budget.movements.show', ['kind' => 'expense', 'id' => $expense->id]))
        ->and($rows['income-'.$income->id]->href)->toBe(route('budget.movements.show', ['kind' => 'income', 'id' => $income->id]))
        ->and($rows['income-'.$income->id]->subtitle)->toBe('Income entry')
        ->and($rows['income-'.$income->id]->icon)->toBe('arrow-down-left')
        ->and($rows['income-'.$income->id]->tone)->toBe('income');
});

it('finds an amount only in the movements of that amount', function (): void {
    searchExpense($this->family, 'Bolletta del gas', '2026-10-05', '84.20');
    searchExpense($this->family, 'Bolletta della luce', '2026-10-06', '84.00');

    expect(movementTitles($this->family, '84,20'))->toBe(['Bolletta del gas']);
});

it('matches an amount per word, the other words are still text', function (): void {
    searchExpense($this->family, 'Bolletta del gas', '2026-10-05', '84.20');
    searchExpense($this->family, 'Bolletta della luce', '2026-10-06', '84.20');

    expect(movementTitles($this->family, 'gas 84,20'))->toBe(['Bolletta del gas']);
});

it('finds the movements of a day', function (): void {
    searchExpense($this->family, 'Pizzeria', '2026-10-15', 30);
    searchIncome($this->family, 'Stipendio', '2026-10-15', 1500);
    searchExpense($this->family, 'Cinema', '2026-10-16', 20);

    expect(movementTitles($this->family, '15/10/2026'))->toBe(['Pizzeria', 'Stipendio']);
});

it('finds an income through its organization and its person', function (): void {
    $employer = CurrentFamily::using($this->family->id, fn (): Organization => Organization::factory()->create(['family_id' => $this->family->id, 'name' => 'Studio Rinaldi']));
    $marco = CurrentFamily::using($this->family->id, fn (): Person => Person::factory()->create(['family_id' => $this->family->id, 'first_name' => 'Marco']));
    searchIncome($this->family, 'Stipendio di settembre', '2026-09-27', 1800, ['organization_id' => $employer->id, 'person_id' => $marco->id]);
    searchIncome($this->family, 'Rimborso', '2026-09-28', 50);

    expect(movementTitles($this->family, 'Studio Rinaldi'))->toBe(['Stipendio di settembre'])
        ->and(movementTitles($this->family, 'Marco'))->toBe(['Stipendio di settembre']);
});

it('names an income without a title by its type', function (): void {
    searchIncome($this->family, null, '2026-09-27', 1800, ['type' => IncomeType::Salary, 'notes' => 'Settembre']);

    expect(movementTitles($this->family, 'Settembre'))->toBe([IncomeType::Salary->label()]);
});

it('mixes expenses and incomes by date and cuts them to the limit', function (): void {
    searchExpense($this->family, 'Panda uno', '2026-01-01', 10);
    searchIncome($this->family, 'Panda due', '2026-03-01', 10);
    searchExpense($this->family, 'Panda tre', '2026-02-01', 10);

    expect(movementTitles($this->family, 'Panda', 2))->toBe(['Panda due', 'Panda tre']);
});

it('counts expenses and incomes together, without the limit', function (): void {
    searchExpense($this->family, 'Panda uno', '2026-01-01', 10);
    searchIncome($this->family, 'Panda due', '2026-03-01', 10);
    searchExpense($this->family, 'Panda tre', '2026-02-01', 10);

    $count = CurrentFamily::using($this->family->id, fn (): int => resolve(MovementResults::class)->count(SearchTerms::fromQuery('Panda')));

    expect($count)->toBe(3)
        ->and(resolve(MovementResults::class)->group())->toBe(SearchGroup::Movements);
});

it('does not find the movements of another family', function (): void {
    $other = Family::factory()->create();
    searchExpense($other, 'Tagliando Panda', '2026-03-10', 180);
    searchIncome($other, 'Rimborso Panda', '2026-03-10', 50);

    expect(movementResults($this->family, 'Panda'))->toBe([]);
});
