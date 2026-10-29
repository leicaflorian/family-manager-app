<?php

declare(strict_types=1);

use App\Actions\Budget\SaveMovement;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    // The seeder has no `member`: one is made in the Bianchi family
    $this->rosa = User::factory()->create(['role' => Role::Member, 'family_id' => $this->marco->family_id]);
});

/** The planned expense of the Bianchi with this title. */
function movementPlan(string $title): PlannedExpense
{
    return insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', $title)->sole());
}

/** The id of an account of the Bianchi, by name. */
function movementAccount(string $name): int
{
    return insideBianchi(fn (): int => FinancialAccount::query()->where('name', $name)->sole()->id);
}

/** The expense of the Bianchi with this title. */
function movementExpense(string $title): Expense
{
    return insideBianchi(fn (): Expense => Expense::query()->where('title', $title)->latest('date')->firstOrFail());
}

it('redirects the budget to the movements', function (): void {
    $this->actingAs($this->marco)->get('/budget')->assertRedirect('/budget/movements');
});

it('sums the month', function (): void {
    $this->actingAs($this->marco)
        ->get('/budget/movements?month=2026-10')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/Movements')
            ->where('summary.month', '2026-10')
            ->where('summary.incomes', 1720)
            ->where('summary.salaries_in', 1)
            ->where('summary.salaries_expected', 2)
            ->where('summary.expenses', 1284.2)
            ->where('summary.payments', 15)
            ->where('summary.balance', 435.8)
            ->has('movements', 16)
            ->where('movements.0.title', 'Parcheggio')
            ->where('movements.1.title', 'Stipendio di ottobre')
            ->where('movements.1.kind', 'income')
            ->where('selected', null));
});

it('opens the current month when none is asked', function (): void {
    $this->actingAs($this->marco)
        ->get('/budget/movements')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('summary.month', '2026-10'));
});

it('filters by kind and account', function (): void {
    $card = movementAccount('Carta Laura');

    $this->actingAs($this->marco)
        ->get('/budget/movements?month=2026-10&kind=income')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('movements', 1)
            ->where('movements.0.kind', 'income')
            ->where('summary.payments', 15));

    $this->actingAs($this->marco)
        ->get("/budget/movements?month=2026-10&kind=expense&account={$card}")
        ->assertInertia(function (AssertableInertia $page): void {
            $movements = collect($page->toArray()['props']['movements']);
            $summary = $page->toArray()['props']['summary'];

            expect($movements->pluck('title')->all())->toContain('Visita dal dentista', 'Radiografia')
                ->and($movements->pluck('kind')->unique()->all())->toBe(['expense'])
                ->and($movements->pluck('account')->unique()->all())->toBe(['Carta Laura'])
                ->and($summary['payments'])->toBe($movements->count());
        });
});

it('answers 404 to a query that is not a month, a kind, an account or a movement', function (string $query): void {
    $this->actingAs($this->marco)->get("/budget/movements?{$query}")->assertNotFound();
})->with(['month=2026-13', 'month=ottobre', 'kind=gift', 'account=99999', 'movement=expense:99999', 'movement=gift:1']);

it('opens a movement beside the list', function (): void {
    $dentist = movementExpense('Visita dal dentista');

    $this->actingAs($this->marco)
        ->get("/budget/movements?month=2026-10&movement=expense:{$dentist->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('selected.id', $dentist->id)
            ->where('selected.kind', 'expense')
            ->where('selected.amount', 80)
            ->where('selected.can_delete', true));
});

it('shows a movement on its page', function (): void {
    $dentist = movementExpense('Visita dal dentista');

    $this->actingAs($this->marco)
        ->get("/budget/movements/expense/{$dentist->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/Movement')
            ->where('movement.title', 'Visita dal dentista')
            ->has('options.accounts')
            ->has('options.planned'));
});

it('offers the open due dates of the planned expense of a movement, with its own', function (): void {
    $dance = movementExpense('Retta di novembre');

    $this->actingAs($this->marco)
        ->get("/budget/movements/expense/{$dance->id}")
        ->assertInertia(function (AssertableInertia $page): void {
            $values = collect($page->toArray()['props']['occurrences'])->pluck('value');

            // The 05/11 due date is paid by this very movement, the 05/10 one by another
            expect($values)->toContain('2026-11-05', '2026-12-05')->not->toContain('2026-10-05');
        });
});

it('opens the form of a new movement, from a planned expense', function (): void {
    $dance = movementPlan('Retta danza');

    $this->actingAs($this->marco)
        ->get("/budget/movements/create?kind=expense&planned={$dance->id}")
        ->assertInertia(function (AssertableInertia $page) use ($dance): void {
            $props = $page->toArray()['props'];

            expect($props['movement'])->toBeNull()
                ->and($props['start']['kind'])->toBe('expense')
                ->and($props['start']['planned_id'])->toBe((string) $dance->id)
                ->and(collect($props['occurrences'])->pluck('value')->first())->toBe('2026-12-05');
        });
});

it('fills a new expense from its planned expense', function (): void {
    $dance = movementPlan('Retta danza');

    $this->actingAs($this->marco)
        ->post('/budget/movements', [
            'kind' => 'expense', 'title' => 'Retta di dicembre', 'amount' => 45, 'date' => '2026-12-04',
            'planned_id' => $dance->id, 'category_id' => '', 'organization_id' => '', 'financial_account_id' => '', 'person_id' => '',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $expense = movementExpense('Retta di dicembre');

    expect($expense->planned_expense_id)->toBe($dance->id)
        ->and($expense->category_id)->toBe($dance->category_id)
        ->and($expense->organization_id)->toBe($dance->organization_id)
        ->and($expense->financial_account_id)->toBe($dance->financial_account_id)
        ->and($expense->person_id)->toBe($dance->person_id)
        ->and($expense->occurrence_date?->toDateString())->toBe('2026-12-05');
});

it('keeps what is written and takes the default account when nothing says otherwise', function (): void {
    $this->actingAs($this->marco)
        ->post('/budget/movements', [
            'kind' => 'expense', 'title' => 'Gelato', 'amount' => '3,50', 'date' => '2026-10-25', 'fee_amount' => 0.5,
        ])
        ->assertSessionHasErrors('amount');

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'Gelato', 'amount' => 3.5, 'date' => '2026-10-25', 'fee_amount' => 0.5]);

    $gelato = movementExpense('Gelato');

    expect($gelato->financial_account_id)->toBe(movementAccount('Conto BPER'))
        ->and((float) $gelato->fee_amount)->toBe(0.5)
        ->and($gelato->planned_expense_id)->toBeNull()
        ->and($gelato->occurrence_date)->toBeNull();
});

it('creates an income from a planned income', function (): void {
    $planned = insideBianchi(fn () => PlannedIncome::query()->where('title', 'Stipendio di Marco')->sole());

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'income', 'title' => 'Busta paga di Ottobre', 'amount' => 1850, 'date' => '2026-10-27', 'planned_id' => $planned->id, 'type' => 'salary'])
        ->assertSessionHasNoErrors();

    $income = insideBianchi(fn (): Income => Income::query()->where('title', 'Busta paga di Ottobre')->sole());

    expect($income->planned_income_id)->toBe($planned->id)
        ->and($income->occurrence_date?->toDateString())->toBe('2026-10-27')
        ->and($income->type->value)->toBe('salary')
        ->and($income->employment_id)->toBe($planned->employment_id)
        ->and($income->person_id)->toBe($planned->person_id);
});

it('requires the kind, the title, the amount and the date', function (): void {
    $this->actingAs($this->marco)
        ->post('/budget/movements', [])
        ->assertSessionHasErrors(['kind', 'title', 'amount', 'date']);

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'x', 'amount' => 1, 'date' => '2026-10-01', 'fee_amount' => -1])
        ->assertSessionHasErrors('fee_amount');
});

it('refuses an id of another family', function (): void {
    $rossi = Family::factory()->create();
    $foreign = FinancialAccount::factory()->create(['family_id' => $rossi->id]);

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'x', 'amount' => 1, 'date' => '2026-10-01', 'financial_account_id' => $foreign->id])
        ->assertSessionHasErrors('financial_account_id');
});

it('refuses an occurrence that is already paid', function (): void {
    $dance = movementPlan('Retta danza');
    $payload = ['kind' => 'expense', 'title' => 'Retta', 'amount' => 45, 'date' => '2026-11-04', 'planned_id' => $dance->id];

    // The 05/11 due date was paid on 20/10; a day that is not a due date is refused too
    $this->actingAs($this->marco)->post('/budget/movements', [...$payload, 'occurrence_date' => '2026-11-05'])
        ->assertSessionHasErrors('occurrence_date');
    $this->actingAs($this->marco)->post('/budget/movements', [...$payload, 'occurrence_date' => '2026-11-06'])
        ->assertSessionHasErrors('occurrence_date');
    $this->actingAs($this->marco)->post('/budget/movements', [...$payload, 'occurrence_date' => '2026-12-05'])
        ->assertSessionHasNoErrors();

    expect(movementExpense('Retta')->occurrence_date?->toDateString())->toBe('2026-12-05');
});

it('refuses an occurrence without a planned expense', function (): void {
    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'x', 'amount' => 1, 'date' => '2026-10-01', 'occurrence_date' => '2026-12-05'])
        ->assertSessionHasErrors('occurrence_date');
});

it('changes a movement and keeps its own due date', function (): void {
    $dance = movementExpense('Retta di novembre');

    $this->actingAs($this->marco)
        ->put("/budget/movements/expense/{$dance->id}", [
            'title' => 'Retta di novembre (anticipata)', 'amount' => 46, 'date' => '2026-10-21',
            'planned_id' => $dance->planned_expense_id, 'occurrence_date' => '2026-11-05', 'financial_account_id' => $dance->financial_account_id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $dance->refresh();

    expect($dance->title)->toBe('Retta di novembre (anticipata)')
        ->and((float) $dance->amount)->toBe(46.0)
        ->and($dance->occurrence_date?->toDateString())->toBe('2026-11-05');
});

it('deletes an expense and keeps its documents', function (): void {
    $dentist = movementExpense('Visita dal dentista');
    $document = insideBianchi(fn (): Document => tap(Document::factory()->create(['family_id' => $dentist->family_id]), fn (Document $document) => $dentist->documents()->attach($document)));

    $this->actingAs($this->marco)
        ->delete("/budget/movements/expense/{$dentist->id}")
        ->assertRedirect('/budget/movements?month=2026-10');

    $this->assertDatabaseMissing('expenses', ['id' => $dentist->id]);
    $this->assertDatabaseMissing('documentables', ['documentable_type' => 'expense', 'documentable_id' => $dentist->id]);
    $this->assertDatabaseHas('documents', ['id' => $document->id]);
});

it('lets a member create and edit a movement but not delete it', function (): void {
    $this->actingAs($this->rosa)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'Pizza', 'amount' => 18, 'date' => '2026-10-24'])
        ->assertSessionHasNoErrors();

    $pizza = movementExpense('Pizza');

    $this->actingAs($this->rosa)
        ->put("/budget/movements/expense/{$pizza->id}", ['title' => 'Pizza e birra', 'amount' => 24, 'date' => '2026-10-24'])
        ->assertSessionHasNoErrors();
    expect($pizza->refresh()->title)->toBe('Pizza e birra');

    $this->actingAs($this->rosa)->delete("/budget/movements/expense/{$pizza->id}")->assertForbidden();
    $this->assertDatabaseHas('expenses', ['id' => $pizza->id]);

    $this->actingAs($this->rosa)
        ->get("/budget/movements/expense/{$pizza->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('movement.can_edit', true)->where('movement.can_delete', false));
});

it('answers 404 to a movement of another family', function (): void {
    $rossi = Family::factory()->create();
    $foreignExpense = Expense::factory()->create(['family_id' => $rossi->id]);
    $foreignIncome = Income::factory()->create(['family_id' => $rossi->id]);
    $body = ['title' => 'x', 'amount' => 1, 'date' => '2026-10-01'];

    foreach ([$this->marco, $this->rosa] as $user) {
        $this->actingAs($user)->get("/budget/movements/expense/{$foreignExpense->id}")->assertNotFound();
        $this->actingAs($user)->get("/budget/movements/income/{$foreignIncome->id}")->assertNotFound();
        $this->actingAs($user)->put("/budget/movements/expense/{$foreignExpense->id}", $body)->assertNotFound();
        $this->actingAs($user)->delete("/budget/movements/expense/{$foreignExpense->id}")->assertNotFound();
        $this->actingAs($user)->get("/budget/movements?movement=expense:{$foreignExpense->id}")->assertNotFound();
    }

    $this->actingAs($this->marco)->get('/budget/movements/gift/1')->assertNotFound();
    $this->assertDatabaseHas('expenses', ['id' => $foreignExpense->id]);
});

it('refuses a day that registers a due date already paid, when none is chosen', function (): void {
    $dance = movementPlan('Retta danza');

    // 04/11 is in the window of the 05/11 due date, paid on 20/10
    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'Retta doppia', 'amount' => 45, 'date' => '2026-11-04', 'planned_id' => $dance->id])
        ->assertSessionHasErrors(['occurrence_date' => 'This due date is already paid: choose another one.']);

    $this->assertDatabaseMissing('expenses', ['title' => 'Retta doppia']);
});

it('checks again, inside the transaction, that the due date is not paid', function (): void {
    $dance = movementPlan('Retta danza');

    expect(fn (): mixed => insideBianchi(fn () => resolve(SaveMovement::class)->handle('expense', [
        'title' => 'Retta doppia', 'amount' => 45, 'date' => '2026-11-04', 'planned_id' => $dance->id, 'occurrence_date' => '2026-11-05',
    ])))->toThrow(ValidationException::class);

    $this->assertDatabaseMissing('expenses', ['title' => 'Retta doppia']);
});

it('locks the planned item before checking its due date, so two saves cannot both pay it', function (): void {
    $dance = movementPlan('Retta danza');
    DB::enableQueryLog();

    insideBianchi(fn () => resolve(SaveMovement::class)->handle('expense', [
        'title' => 'Retta di gennaio', 'amount' => 45, 'date' => '2027-01-04', 'planned_id' => $dance->id,
    ]));

    $locks = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'planned_expenses') && str_contains($query['query'], 'for update'));
    expect($locks)->toHaveCount(1);
});

it('lets a movement keep its own due date when its day is changed without naming it', function (): void {
    $dance = movementExpense('Retta di novembre');

    $this->actingAs($this->marco)
        ->put("/budget/movements/expense/{$dance->id}", ['title' => $dance->title, 'amount' => 45, 'date' => '2026-10-21', 'planned_id' => $dance->planned_expense_id, 'occurrence_date' => ''])
        ->assertSessionHasNoErrors();

    expect($dance->refresh()->occurrence_date?->toDateString())->toBe('2026-11-05');
});

it('offers the open due dates of whichever planned expense is asked, by a reload of the page', function (): void {
    $dance = movementPlan('Retta danza');

    foreach (['/budget/movements', '/budget/movements/create', '/budget/movements/expense/'.movementExpense('Parcheggio')->id] as $address) {
        $this->actingAs($this->marco)
            ->get("{$address}?planned_kind=expense&planned={$dance->id}&planned_date=2026-10-26")
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('occurrences.0.value', '2026-12-05'));
    }

    $rossi = Family::factory()->create();
    $foreign = PlannedExpense::factory()->create(['family_id' => $rossi->id]);
    $this->actingAs($this->marco)->get("/budget/movements?planned_kind=expense&planned={$foreign->id}")->assertNotFound();
});

it('gives the employments to the form of an income', function (): void {
    $this->actingAs($this->marco)
        ->get('/budget/movements/create?kind=income')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('options.employments', 2)->has('options.employments.0.label'));
});

it('keeps the employment of an income', function (): void {
    $planned = insideBianchi(fn () => PlannedIncome::query()->where('title', 'Stipendio di Laura')->sole());

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'income', 'title' => 'Altro', 'amount' => 10, 'date' => '2026-10-02', 'type' => 'other', 'employment_id' => $planned->employment_id])
        ->assertSessionHasNoErrors();

    expect(insideBianchi(fn (): ?int => Income::query()->where('title', 'Altro')->sole()->employment_id))->toBe($planned->employment_id);
});

it('requires the type of an income', function (): void {
    foreach ([[], ['type' => ''], ['type' => 'gift']] as $extra) {
        $this->actingAs($this->marco)
            ->post('/budget/movements', ['kind' => 'income', 'title' => 'x', 'amount' => 1, 'date' => '2026-10-02', ...$extra])
            ->assertSessionHasErrors('type');
    }

    $income = insideBianchi(fn (): Income => Income::query()->latest('id')->firstOrFail());
    $this->actingAs($this->marco)
        ->put("/budget/movements/income/{$income->id}", ['title' => 'x', 'amount' => 1, 'date' => '2026-10-02', 'type' => ''])
        ->assertSessionHasErrors('type');
});

it('answers 404 to a new movement of an unknown kind', function (): void {
    $this->actingAs($this->marco)->get('/budget/movements/create?kind=gift')->assertNotFound();
    $this->actingAs($this->marco)->get('/budget/movements/create')->assertOk();
});
