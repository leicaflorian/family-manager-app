<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\User;
use App\Support\CurrentFamily;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->actingAs($this->marco);
    $this->car = insideBianchi(fn (): ExpenseCategory => ExpenseCategory::query()->where('name', 'Auto')->sole());
    $this->groceries = insideBianchi(fn (): ExpenseCategory => ExpenseCategory::query()->where('name', 'Spesa')->sole());
    $this->parking = insideBianchi(fn (): ExpenseCategory => ExpenseCategory::query()->create(['name' => 'Parcheggi', 'parent_id' => $this->car->id, 'sort' => 1]));
});

it('lists the expense categories with their card', function (): void {
    $this->get(route('expense-categories.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/ExpenseCategories')
        ->where('canEdit', true)
        ->has('cardTypes', 13)
        ->where('cardTypes.4', ['value' => 'vehicle', 'label' => 'Vehicle', 'help' => null])
        ->where('categories', function (Collection $categories): bool {
            $car = $categories->firstWhere('name', 'Auto');
            $groceries = $categories->firstWhere('name', 'Spesa');

            expect($car['card_type'])->toBe('vehicle')
                ->and($car['card_label'])->toBe('Vehicle')
                ->and($car['children'][0]['name'])->toBe('Parcheggi')
                ->and($car['children'][0]['card_type'])->toBe('vehicle')
                ->and($car['children'][0]['card_label'])->toBe('Vehicle')
                ->and($groceries['card_type'])->toBeNull()
                ->and($groceries['card_label'])->toBeNull();

            return true;
        }));
});

it('gives the card type of every category, with the inheritance', function (): void {
    $types = insideBianchi(fn (): array => ExpenseCategory::cardTypes());

    expect($types[$this->car->id])->toBe('vehicle')
        ->and($types[$this->parking->id])->toBe('vehicle')
        ->and($types)->not->toHaveKey($this->groceries->id);
});

it('sets the card of a main category', function (): void {
    $health = insideBianchi(fn (): ExpenseCategory => ExpenseCategory::query()->where('name', 'Salute')->sole());
    $health->update(['card_type' => null]);

    $this->patch(route('expense-categories.update', $health), ['card_type' => 'health_event'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('expense-categories.index'));

    expect($health->fresh()?->card_type)->toBe('health_event');
});

it('empties the card', function (): void {
    $this->patch(route('expense-categories.update', $this->car), ['card_type' => null])->assertSessionHasNoErrors();

    expect($this->car->fresh()?->card_type)->toBeNull();
});

it('refuses a card type that does not exist', function (): void {
    $this->patch(route('expense-categories.update', $this->car), ['card_type' => 'boat'])->assertSessionHasErrors('card_type');

    expect($this->car->fresh()?->card_type)->toBe('vehicle');
});

it('refuses a card on a subcategory', function (): void {
    $this->patch(route('expense-categories.update', $this->parking), ['card_type' => 'vehicle'])->assertSessionHasErrors('card_type');

    expect($this->parking->fresh()?->card_type)->toBeNull();
});

it('does not let a member change the card', function (): void {
    $rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);
    $this->actingAs($rosa);

    $this->get(route('expense-categories.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('canEdit', false));
    $this->patch(route('expense-categories.update', $this->groceries), ['card_type' => 'home'])->assertForbidden();

    expect($this->groceries->fresh()?->card_type)->toBeNull();
});

it('answers 404 for a category of another family', function (): void {
    $rossi = registerFamily('Rossi', 'anna@example.com');
    $other = CurrentFamily::using($rossi->family_id, fn (): ExpenseCategory => ExpenseCategory::query()->whereNull('parent_id')->firstOrFail());

    $this->patch(route('expense-categories.update', $other), ['card_type' => 'vehicle'])->assertNotFound();
    $this->patch(route('expense-categories.update', $other), ['card_type' => 'boat'])->assertNotFound();

    expect(CurrentFamily::using($rossi->family_id, fn (): ?string => $other->fresh()?->card_type))->toBeNull();
});
