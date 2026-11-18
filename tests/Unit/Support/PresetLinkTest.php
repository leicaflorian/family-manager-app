<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\Income;
use App\Models\Vehicle;
use App\Support\PresetLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->marco = registerFamily();
});

test('an upload link can name an expense or an income of the family', function (): void {
    $expense = Expense::factory()->for($this->marco->family)->create();
    $income = Income::factory()->for($this->marco->family)->create();

    insideBianchi(function () use ($expense, $income): void {
        expect(PresetLink::fromUpload('expense:'.$expense->id))->toBe(['type' => 'expense', 'id' => $expense->id])
            ->and(PresetLink::fromUpload('income:'.$income->id))->toBe(['type' => 'income', 'id' => $income->id]);
    });
});

test('an upload link of an expense of another family is dropped', function (): void {
    $theirs = Expense::factory()->create();

    insideBianchi(fn () => expect(PresetLink::fromUpload('expense:'.$theirs->id))->toBeNull());
});

test('an upload link still names a card of the family', function (): void {
    $panda = Vehicle::factory()->for($this->marco->family)->create();

    insideBianchi(fn () => expect(PresetLink::fromUpload('vehicle:'.$panda->id))->toBe(['type' => 'vehicle', 'id' => $panda->id]));
});

test('the card link of a form never takes an expense', function (): void {
    $expense = Expense::factory()->for($this->marco->family)->create();

    insideBianchi(fn () => expect(PresetLink::from('expense:'.$expense->id))->toBeNull());
});

test('the address of a link is the card or the movement', function (): void {
    expect(PresetLink::route(['type' => 'income', 'id' => 5]))->toEndWith('/budget/movements/income/5')
        ->and(PresetLink::route(['type' => 'vehicle', 'id' => 5]))->toEndWith('/cards/vehicle/5')
        ->and(PresetLink::movement(Expense::factory()->make(['id' => 9])))->toBe(['type' => 'expense', 'id' => 9]);
});
