<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Belonging;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\HealthEvent;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Deadlines;
use App\Support\CurrentFamily;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);
});

/**
 * The columns the form of a planned expense sends, with the ones a test changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function savePlannedValues(array $changes = []): array
{
    return [
        'title' => 'Palestra',
        'amount' => 35.5,
        'start_date' => '2026-11-03',
        'is_recurring' => true,
        'interval' => 1,
        'interval_unit' => 'month',
        'is_estimated' => false,
        ...$changes,
    ];
}

/** A planned expense of the Bianchi by its title. */
function savePlannedOf(string $title): PlannedExpense
{
    return insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', $title)->sole());
}

it('saves a planned expense with the default account when none is given', function (): void {
    $this->actingAs($this->marco)
        ->post('/budget/planned/expense', savePlannedValues(['financial_account_id' => null]))
        ->assertRedirect();

    $saved = savePlannedOf('Palestra');
    $default = insideBianchi(fn (): FinancialAccount => FinancialAccount::query()->where('is_default', true)->firstOrFail());

    expect($saved->financial_account_id)->toBe($default->id)
        ->and((float) $saved->amount)->toBe(35.5)
        ->and($saved->start_date?->toDateString())->toBe('2026-11-03');
});

it('keeps the account that is given', function (): void {
    $card = insideBianchi(fn (): FinancialAccount => FinancialAccount::query()->where('name', 'Carta Laura')->sole());

    $this->actingAs($this->marco)
        ->post('/budget/planned/expense', savePlannedValues(['financial_account_id' => (string) $card->id]))
        ->assertRedirect();

    expect(savePlannedOf('Palestra')->financial_account_id)->toBe($card->id);
});

it('goes to the page of the new planned expense', function (): void {
    $response = $this->actingAs($this->marco)->post('/budget/planned/expense', savePlannedValues());
    $saved = savePlannedOf('Palestra');

    $response->assertRedirect("/budget/planned/expense/{$saved->id}");
});

it('links the card of Refers to', function (): void {
    $utility = insideBianchi(fn (): Utility => Utility::query()->firstOrFail());

    $this->actingAs($this->marco)
        ->post('/budget/planned/expense', savePlannedValues(['card' => "utility:{$utility->id}"]))
        ->assertRedirect();

    expect(savePlannedOf('Palestra'))->card_type->toBe('utility')->card_id->toBe($utility->id);
});

it('moves the card when Refers to changes and clears it when emptied', function (): void {
    $utility = insideBianchi(fn (): Utility => Utility::query()->firstOrFail());
    $planned = savePlannedOf('Retta danza');
    $this->actingAs($this->marco);

    $bills = (string) insideBianchi(fn (): int => ExpenseCategory::query()->where('name', 'Bollette e utenze')->sole()->id);

    $this->put("/budget/planned/expense/{$planned->id}", savePlannedValues(['title' => 'Retta danza', 'category_id' => $bills, 'card' => "utility:{$utility->id}"]))->assertRedirect();
    $moved = savePlannedOf('Retta danza');
    expect($moved->card_type)->toBe('utility')->and($moved->card_id)->toBe($utility->id);

    $this->put("/budget/planned/expense/{$planned->id}", savePlannedValues(['title' => 'Retta danza', 'card' => null]))->assertRedirect();
    expect(savePlannedOf('Retta danza'))->card_type->toBeNull()->card_id->toBeNull();
});

it('refuses a card of another family in Refers to', function (): void {
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $foreign = CurrentFamily::using($rossi->family_id, fn (): Utility => Utility::factory()->create(['family_id' => $rossi->family_id]));

    $this->actingAs($this->marco)
        ->post('/budget/planned/expense', savePlannedValues(['card' => "utility:{$foreign->id}"]))
        ->assertSessionHasErrors('card');

    expect(insideBianchi(fn (): int => PlannedExpense::query()->where('title', 'Palestra')->count()))->toBe(0);
});

it('refuses a card of an unknown type', function (): void {
    $this->actingAs($this->marco)
        ->post('/budget/planned/expense', savePlannedValues(['card' => 'boat:1']))
        ->assertSessionHasErrors('card');
});

it('refuses a card of another type than the category', function (): void {
    $utility = insideBianchi(fn (): Utility => Utility::query()->firstOrFail());
    $car = (string) insideBianchi(fn (): int => ExpenseCategory::query()->where('name', 'Auto')->sole()->id);

    $this->actingAs($this->marco)
        ->post('/budget/planned/expense', savePlannedValues(['category_id' => $car, 'card' => "utility:{$utility->id}"]))
        ->assertSessionHasErrors(['card' => 'Choose a card of the type of the category.']);
});

it('checks the card against the category the planned expense keeps when a change does not send one', function (): void {
    $utility = insideBianchi(fn (): Utility => Utility::query()->firstOrFail());
    $planned = savePlannedOf('Retta danza');

    $this->actingAs($this->marco)
        ->put("/budget/planned/expense/{$planned->id}", savePlannedValues(['title' => 'Retta danza', 'card' => "utility:{$utility->id}"]))
        ->assertSessionHasErrors('card');
});

it('keeps a card of another type already linked', function (): void {
    $mortgage = savePlannedOf('Mutuo casa');

    $this->actingAs($this->marco)
        ->put("/budget/planned/expense/{$mortgage->id}", savePlannedValues([
            'title' => 'Mutuo casa', 'amount' => 612, 'start_date' => '2018-06-15', 'end_date' => '2041-06-15',
            'category_id' => (string) $mortgage->category_id, 'card' => "loan:{$mortgage->card_id}",
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(savePlannedOf('Mutuo casa'))->card_type->toBe('loan')->card_id->toBe($mortgage->card_id);
});

it('links any card when the category has none', function (): void {
    $panda = insideBianchi(fn (): Vehicle => Vehicle::query()->where('name', 'Fiat Panda')->sole());
    $groceries = (string) insideBianchi(fn (): int => ExpenseCategory::query()->where('name', 'Spesa')->sole()->id);

    $this->actingAs($this->marco)
        ->post('/budget/planned/expense', savePlannedValues(['category_id' => $groceries, 'card' => "vehicle:{$panda->id}"]))
        ->assertSessionHasNoErrors();

    expect(savePlannedOf('Palestra'))->card_type->toBe('vehicle')->card_id->toBe($panda->id);
});

it('links a planned expense to a health event', function (): void {
    $visits = insideBianchi(fn (): HealthEvent => HealthEvent::query()->where('title', 'Visite di Giulia')->sole());
    $health = (string) insideBianchi(fn (): int => ExpenseCategory::query()->where('name', 'Salute')->sole()->id);

    $this->actingAs($this->marco)
        ->post('/budget/planned/expense', savePlannedValues(['category_id' => $health, 'card' => "health_event:{$visits->id}"]))
        ->assertSessionHasNoErrors();

    expect(savePlannedOf('Palestra'))->card_type->toBe('health_event')->card_id->toBe($visits->id);
});

it('presets the category of the card', function (): void {
    $light = insideBianchi(fn (): Utility => Utility::query()->where('type', 'electricity')->sole());
    $bills = (string) insideBianchi(fn (): int => ExpenseCategory::query()->where('name', 'Bollette e utenze')->sole()->id);

    $this->actingAs($this->marco)
        ->get("/budget/planned/expense/create?card=utility:{$light->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('values.card', "utility:{$light->id}")
            ->where('values.category_id', $bills));
});

it('leaves the category empty when no category has the type', function (): void {
    $tv = insideBianchi(fn (): Belonging => Belonging::query()->where('name', 'TV Samsung')->sole());

    $this->actingAs($this->marco)
        ->get("/budget/planned/expense/create?card=belonging:{$tv->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('values.card', "belonging:{$tv->id}")
            ->where('values.category_id', null));
});

it('refuses an organization, an account, a person and a category of another family', function (string $field, string $model): void {
    $rossi = registerFamily('Rossi', 'rossi2@example.com');
    $foreign = CurrentFamily::using($rossi->family_id, fn () => $model::factory()->create(['family_id' => $rossi->family_id]));

    $this->actingAs($this->marco)
        ->post('/budget/planned/expense', savePlannedValues([$field => (string) $foreign->id]))
        ->assertSessionHasErrors($field);
})->with([
    'organization' => ['organization_id', Organization::class],
    'account' => ['financial_account_id', FinancialAccount::class],
    'person' => ['person_id', Person::class],
    'category' => ['category_id', ExpenseCategory::class],
]);

it('asks for the title, the amount and the start', function (): void {
    $this->actingAs($this->marco)
        ->post('/budget/planned/expense', ['interval' => 0, 'interval_unit' => 'fortnight'])
        ->assertSessionHasErrors(['title', 'amount', 'start_date', 'interval', 'interval_unit']);
});

it('saves a planned income, which asks for its type', function (): void {
    $this->actingAs($this->marco)
        ->post('/budget/planned/income', savePlannedValues(['title' => 'Affitto garage']))
        ->assertSessionHasErrors('type');

    $this->post('/budget/planned/income', savePlannedValues(['title' => 'Affitto garage', 'type' => 'other']))->assertRedirect();

    $income = insideBianchi(fn (): PlannedIncome => PlannedIncome::query()->where('title', 'Affitto garage')->sole());
    expect($income->type->value)->toBe('other')
        ->and($income->financial_account_id)->not->toBeNull();
});

it('changes a planned expense and keeps the exceptions', function (): void {
    $planned = savePlannedOf('Retta danza');
    $exceptions = insideBianchi(fn (): int => $planned->exceptions()->count());

    $this->actingAs($this->marco)
        ->put("/budget/planned/expense/{$planned->id}", savePlannedValues(['title' => 'Retta danza', 'amount' => 50]))
        ->assertRedirect("/budget/planned/expense/{$planned->id}");

    $changed = savePlannedOf('Retta danza');
    expect((float) $changed->amount)->toBe(50.0)->and(insideBianchi(fn (): int => $changed->exceptions()->count()))->toBe($exceptions);
});

it('deletes a planned expense and leaves its payments without the link', function (): void {
    $planned = savePlannedOf('Retta danza');
    $payments = insideBianchi(fn (): int => Expense::query()->where('planned_expense_id', $planned->id)->count());

    $this->actingAs($this->marco)->delete("/budget/planned/expense/{$planned->id}")
        ->assertRedirect('/budget/planned')
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Deleted.');

    expect($payments)->toBeGreaterThan(0)
        ->and(insideBianchi(fn (): int => PlannedExpense::query()->where('title', 'Retta danza')->count()))->toBe(0)
        ->and(insideBianchi(fn (): int => Expense::query()->whereNull('planned_expense_id')->where('title', 'like', 'Retta di%')->count()))->toBe($payments);
});

it('answers 404 to a planned item of another family or of an unknown kind', function (string $method, string $path): void {
    $rossi = registerFamily('Rossi', 'rossi3@example.com');
    $foreign = CurrentFamily::using($rossi->family_id, fn (): PlannedExpense => PlannedExpense::factory()->create(['family_id' => $rossi->family_id, 'start_date' => '2026-12-05']));

    $this->actingAs($this->marco)
        ->call($method, strtr($path, ['{id}' => (string) $foreign->id]), savePlannedValues())
        ->assertNotFound();
})->with([
    'show' => ['GET', '/budget/planned/expense/{id}'],
    'edit' => ['GET', '/budget/planned/expense/{id}/edit'],
    'update' => ['PUT', '/budget/planned/expense/{id}'],
    'delete' => ['DELETE', '/budget/planned/expense/{id}'],
    'unknown kind' => ['GET', '/budget/planned/gift/1'],
    'unknown kind to save' => ['POST', '/budget/planned/gift'],
]);

it('a member sees the planned expenses but cannot save them', function (): void {
    $planned = savePlannedOf('Retta danza');
    $this->actingAs($this->rosa);

    $this->get('/budget/planned')->assertOk();
    $this->get("/budget/planned/expense/{$planned->id}")->assertOk();
    $this->get('/budget/planned/expense/create')->assertForbidden();
    $this->get("/budget/planned/expense/{$planned->id}/edit")->assertForbidden();
    $this->post('/budget/planned/expense', savePlannedValues())->assertForbidden();
    $this->put("/budget/planned/expense/{$planned->id}", savePlannedValues())->assertForbidden();
    $this->delete("/budget/planned/expense/{$planned->id}")->assertForbidden();

    expect(savePlannedOf('Retta danza')->title)->toBe('Retta danza');
});

it('stores an interval of one when Every is cleared', function (): void {
    $this->actingAs($this->marco)
        ->post('/budget/planned/expense', savePlannedValues(['interval' => null]))
        ->assertRedirect();

    expect(savePlannedOf('Palestra')->interval)->toBe(1);
});

it('keeps the account when a change does not send one, and uses the default when it is emptied', function (): void {
    $planned = savePlannedOf('Retta danza');
    $card = $planned->financial_account_id;
    $default = insideBianchi(fn (): int => FinancialAccount::query()->where('is_default', true)->firstOrFail()->id);
    $this->actingAs($this->marco);

    $this->put("/budget/planned/expense/{$planned->id}", savePlannedValues(['title' => 'Retta danza']))->assertRedirect();
    expect(savePlannedOf('Retta danza')->financial_account_id)->toBe($card)->not->toBe($default);

    $this->put("/budget/planned/expense/{$planned->id}", savePlannedValues(['title' => 'Retta danza', 'financial_account_id' => null]))->assertRedirect();
    expect(savePlannedOf('Retta danza')->financial_account_id)->toBe($default);
});

it('moves the payments and the exceptions with the due dates when the schedule changes', function (): void {
    $planned = savePlannedOf('Retta danza');

    $this->actingAs($this->marco)
        ->put("/budget/planned/expense/{$planned->id}", savePlannedValues(['title' => 'Retta danza', 'amount' => 45, 'start_date' => '2025-09-10', 'financial_account_id' => $planned->financial_account_id]))
        ->assertRedirect();

    $today = CarbonImmutable::today('Europe/Rome');
    $deadlines = insideBianchi(fn (): array => resolve(Deadlines::class)->between([savePlannedOf('Retta danza')], CarbonImmutable::parse('2025-09-01'), CarbonImmutable::parse('2026-11-30')));
    $byDay = [];

    foreach ($deadlines as $deadline) {
        $byDay[$deadline->occurrence->originalDate->toDateString()] = $deadline;
    }

    expect(array_filter($deadlines, static fn (Deadline $deadline): bool => $deadline->isLate($today)))->toBe([])
        ->and($byDay['2026-10-10']->payment?->title)->toBe('Retta di ottobre')
        ->and($byDay['2026-11-10']->payment?->title)->toBe('Retta di novembre')
        ->and($byDay['2025-09-10']->isPaid())->toBeTrue()
        ->and($byDay)->not->toHaveKey('2026-07-10')
        ->and($byDay)->not->toHaveKey('2026-08-10')
        ->and(insideBianchi(fn (): int => Expense::query()->where('planned_expense_id', $planned->id)->whereDay('occurrence_date', 5)->count()))->toBe(0);
});

it('leaves the payments alone when the schedule does not change', function (): void {
    $planned = savePlannedOf('Retta danza');
    $before = insideBianchi(fn (): array => Expense::query()->where('planned_expense_id', $planned->id)->orderBy('id')->pluck('occurrence_date')->map(fn ($date): ?string => $date?->toDateString())->all());

    $this->actingAs($this->marco)
        ->put("/budget/planned/expense/{$planned->id}", savePlannedValues(['title' => 'Retta danza', 'amount' => 50, 'start_date' => '2025-09-05']))
        ->assertRedirect();

    expect(insideBianchi(fn (): array => Expense::query()->where('planned_expense_id', $planned->id)->orderBy('id')->pluck('occurrence_date')->map(fn ($date): ?string => $date?->toDateString())->all()))->toBe($before);
});
