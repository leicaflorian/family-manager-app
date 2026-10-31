<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->panda = insideBianchi(fn (): Vehicle => Vehicle::query()->where('name', 'Fiat Panda')->sole());
    $this->car = insideBianchi(fn (): ExpenseCategory => ExpenseCategory::query()->where('name', 'Auto')->sole());
    $this->parking = insideBianchi(fn (): ExpenseCategory => ExpenseCategory::query()->create(['name' => 'Parcheggi', 'parent_id' => $this->car->id, 'sort' => 1]));
    $this->groceries = insideBianchi(fn (): ExpenseCategory => ExpenseCategory::query()->where('name', 'Spesa')->sole());
});

/**
 * The fields of a new expense, with the ones a test changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function cardExpense(array $changes = []): array
{
    return ['kind' => 'expense', 'title' => 'Parcheggio', 'amount' => 12, 'date' => '2026-10-24', ...$changes];
}

/** The expense of the Bianchi with this title. */
function cardExpenseOf(string $title): Expense
{
    return insideBianchi(fn (): Expense => Expense::query()->where('title', $title)->latest('id')->firstOrFail());
}

it('links an expense to the vehicle of its category', function (): void {
    $this->actingAs($this->marco)
        ->post('/budget/movements', cardExpense(['category_id' => (string) $this->parking->id, 'card' => "vehicle:{$this->panda->id}"]))
        ->assertSessionHasNoErrors();

    expect(cardExpenseOf('Parcheggio'))->card_type->toBe('vehicle')->card_id->toBe($this->panda->id);
});

it('refuses a card of another type than the category', function (): void {
    $light = insideBianchi(fn (): Utility => Utility::query()->firstOrFail());

    $this->actingAs($this->marco)
        ->post('/budget/movements', cardExpense(['category_id' => (string) $this->parking->id, 'card' => "utility:{$light->id}"]))
        ->assertSessionHasErrors(['card' => 'Choose a card of the type of the category.']);
});

it('refuses a card of another family', function (): void {
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $foreign = CurrentFamily::using($rossi->family_id, fn (): Vehicle => Vehicle::factory()->create(['family_id' => $rossi->family_id]));

    $this->actingAs($this->marco)
        ->post('/budget/movements', cardExpense(['category_id' => (string) $this->parking->id, 'card' => "vehicle:{$foreign->id}"]))
        ->assertSessionHasErrors('card');
});

it('links any card when the category has none', function (): void {
    $this->actingAs($this->marco)
        ->post('/budget/movements', cardExpense(['category_id' => (string) $this->groceries->id, 'card' => "vehicle:{$this->panda->id}"]))
        ->assertSessionHasNoErrors();

    expect(cardExpenseOf('Parcheggio'))->card_type->toBe('vehicle')->card_id->toBe($this->panda->id);
});

it('checks the card against the category the expense keeps when a change does not send one', function (): void {
    $light = insideBianchi(fn (): Utility => Utility::query()->firstOrFail());
    $parking = cardExpenseOf('Parcheggio');

    $this->actingAs($this->marco)
        ->put("/budget/movements/expense/{$parking->id}", ['title' => 'Parcheggio', 'amount' => 12, 'date' => '2026-10-24', 'card' => "utility:{$light->id}"])
        ->assertSessionHasErrors('card');
});

it('takes the card of the planned expense it pays', function (): void {
    $roadTax = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', 'Bollo auto')->sole());

    $this->actingAs($this->marco)
        ->post('/budget/movements', cardExpense(['title' => 'Bollo 2027', 'amount' => 198, 'date' => '2027-01-31', 'planned_id' => $roadTax->id, 'card' => '', 'category_id' => '']))
        ->assertSessionHasNoErrors();

    expect(cardExpenseOf('Bollo 2027'))->card_type->toBe('vehicle')->card_id->toBe($this->panda->id);
});

it('accepts the card of the planned expense it pays, of another type than the category', function (): void {
    $mortgage = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', 'Mutuo casa')->sole());
    $home = (string) insideBianchi(fn (): int => ExpenseCategory::query()->where('name', 'Casa e affitto')->sole()->id);
    $values = ['title' => 'Rata mutuo novembre', 'amount' => 612, 'date' => '2026-11-15', 'occurrence_date' => '2026-11-15', 'planned_id' => $mortgage->id, 'category_id' => $home];

    $this->actingAs($this->marco)
        ->post('/budget/movements', cardExpense([...$values, 'card' => "loan:{$mortgage->card_id}"]))
        ->assertSessionHasNoErrors();

    expect(cardExpenseOf('Rata mutuo novembre'))->card_type->toBe('loan')->card_id->toBe($mortgage->card_id);

    $light = insideBianchi(fn (): Utility => Utility::query()->firstOrFail());

    $this->post('/budget/movements', cardExpense([...$values, 'date' => '2026-12-15', 'occurrence_date' => '2026-12-15', 'card' => "utility:{$light->id}"]))
        ->assertSessionHasErrors(['card' => 'Choose a card of the type of the category.']);
});

it('lets a member save an expense with a card', function (): void {
    $rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);

    $this->actingAs($rosa)
        ->post('/budget/movements', cardExpense(['category_id' => (string) $this->parking->id, 'card' => "vehicle:{$this->panda->id}"]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(cardExpenseOf('Parcheggio'))->card_type->toBe('vehicle')->card_id->toBe($this->panda->id);
});

it('gives the form of a saved expense its card', function (): void {
    $parking = cardExpenseOf('Parcheggio');
    insideBianchi(fn () => $parking->update(['card_type' => 'vehicle', 'card_id' => $this->panda->id]));

    $this->actingAs($this->marco)
        ->get("/budget/movements/expense/{$parking->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('movement.card', "vehicle:{$this->panda->id}"));
});

it('does not take a card for an income', function (): void {
    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'income', 'title' => 'Regalo', 'amount' => 50, 'date' => '2026-10-24', 'type' => 'other', 'card' => "vehicle:{$this->panda->id}"])
        ->assertSessionHasNoErrors();
});
