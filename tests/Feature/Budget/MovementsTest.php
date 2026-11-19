<?php

declare(strict_types=1);

use App\Actions\Budget\SaveMovement;
use App\Enums\Role;
use App\Models\Belonging;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\File;
use App\Models\FinancialAccount;
use App\Models\HealthEvent;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Http\UploadedFile;
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
            ->missing('selected')
            ->missing('occurrences')
            ->missing('options'));
});

it('counts only the salaries expected on the account that is shown', function (): void {
    $this->actingAs($this->marco)
        ->get('/budget/movements?month=2026-10&account='.movementAccount('Carta Laura'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('summary.salaries_in', 0)
            ->where('summary.salaries_expected', 0));

    $this->actingAs($this->marco)
        ->get('/budget/movements?month=2026-10&account='.movementAccount('Conto BPER'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('summary.salaries_expected', 2));
});

it('counts a salary without an account on the default account', function (): void {
    // Marco's salary has no account, so it comes to the default one (Conto BPER); Laura's goes to her card
    insideBianchi(fn (): int => PlannedIncome::query()->where('title', 'Stipendio di Marco')->update(['financial_account_id' => null]));
    insideBianchi(fn (): int => PlannedIncome::query()->where('title', 'Stipendio di Laura')->update(['financial_account_id' => movementAccount('Carta Laura')]));

    foreach (['Conto BPER' => 1, 'Carta Laura' => 1] as $account => $expected) {
        $this->actingAs($this->marco)
            ->get('/budget/movements?month=2026-10&account='.movementAccount($account))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('summary.salaries_expected', $expected));
    }
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

it('answers 404 to a query that is not a month, a kind or an account', function (string $query): void {
    $this->actingAs($this->marco)->get("/budget/movements?{$query}")->assertNotFound();
})->with(['month=2026-13', 'month=ottobre', 'kind=gift', 'account=99999']);

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

it('offers the due dates around the movement of its page, not around today', function (): void {
    $september = insideBianchi(fn (): Expense => Expense::query()->where('title', 'Retta di settembre')->whereDate('date', '2025-09-05')->sole());

    $this->actingAs($this->marco)
        ->get("/budget/movements/expense/{$september->id}")
        ->assertInertia(function (AssertableInertia $page): void {
            $values = collect($page->toArray()['props']['occurrences'])->pluck('value');

            // Its own due date is more than a year before today, and comes first
            expect($values->first())->toBe('2025-09-05');
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

    foreach (['/budget/movements/create', '/budget/movements/expense/'.movementExpense('Parcheggio')->id] as $address) {
        $this->actingAs($this->marco)
            ->get("{$address}?planned_kind=expense&planned={$dance->id}&planned_date=2026-10-26")
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('occurrences.0.value', '2026-12-05'));
    }

    $rossi = Family::factory()->create();
    $foreign = PlannedExpense::factory()->create(['family_id' => $rossi->id]);
    $this->actingAs($this->marco)->get("/budget/movements/create?planned_kind=expense&planned={$foreign->id}")->assertNotFound();
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

it('starts a new expense from a card, with the category of its type', function (): void {
    [$tv, $dentist, $health] = insideBianchi(fn (): array => [
        Belonging::query()->where('name', 'TV Samsung')->sole()->id,
        HealthEvent::query()->where('title', 'Dentista di Laura')->sole()->id,
        ExpenseCategory::query()->whereNull('parent_id')->where('card_type', 'health_event')->sole()->id,
    ]);

    $this->actingAs($this->marco)
        ->get("/budget/movements/create?card=belonging:{$tv}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('start.kind', 'expense')
            ->where('start.card', "belonging:{$tv}")
            ->where('start.category_id', null));

    $this->actingAs($this->marco)
        ->get("/budget/movements/create?card=health_event:{$dentist}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('start.card', "health_event:{$dentist}")
            ->where('start.category_id', (string) $health));
});

it('ignores a card of another family on a new expense', function (): void {
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): int => Belonging::query()->create(['name' => 'Frigo'])->id);

    $this->actingAs($this->marco)
        ->get("/budget/movements/create?card=belonging:{$theirs}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('start.card', null)
            ->where('start.category_id', null));
});

it('goes back to the card after adding an expense from it', function (): void {
    $tv = insideBianchi(fn (): int => Belonging::query()->where('name', 'TV Samsung')->sole()->id);

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'Staffa a muro', 'amount' => 35, 'date' => '2026-10-20', 'card' => "belonging:{$tv}", 'back_to_card' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect("/cards/belonging/{$tv}");

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'Senza scheda', 'amount' => 5, 'date' => '2026-10-20', 'back_to_card' => true])
        ->assertRedirectContains('/budget/movements');
});

/** A document of the Bianchi family. */
function movementDocument(string $title = 'Scontrino'): Document
{
    return insideBianchi(fn (): Document => Document::factory()->create(['family_id' => movementExpense('Visita dal dentista')->family_id, 'title' => $title]));
}

it('links the documents chosen when a movement is added', function (): void {
    $document = movementDocument();

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'Gelato', 'amount' => 3, 'date' => '2026-10-25', 'link_documents' => [$document->id]])
        ->assertSessionHasNoErrors();

    expect(insideBianchi(fn (): array => movementExpense('Gelato')->documents()->pluck('documents.id')->all()))->toBe([$document->id]);
});

it('links and unlinks documents when an income is changed', function (): void {
    $income = insideBianchi(fn (): Income => Income::query()->latest('date')->firstOrFail());
    $old = movementDocument('Vecchio');
    $new = movementDocument('Nuovo');
    insideBianchi(fn () => $income->documents()->attach($old));

    $this->actingAs($this->rosa)
        ->put("/budget/movements/income/{$income->id}", ['title' => $income->title, 'amount' => 10, 'date' => '2026-10-02', 'link_documents' => [$new->id], 'unlink_documents' => [$old->id]])
        ->assertSessionHasNoErrors();

    expect(insideBianchi(fn (): array => $income->documents()->pluck('documents.id')->all()))->toBe([$new->id]);
});

it('answers 404 and saves nothing for a document of another family', function (string $list): void {
    $rossi = Family::factory()->create();
    $foreign = CurrentFamily::using($rossi->id, fn (): Document => Document::factory()->create(['family_id' => $rossi->id]));
    $dentist = movementExpense('Visita dal dentista');

    $this->actingAs($this->marco)
        ->put("/budget/movements/expense/{$dentist->id}", ['title' => 'Cambiato', 'amount' => 80, 'date' => '2026-10-10', $list => [$foreign->id]])
        ->assertNotFound();

    expect($dentist->refresh()->title)->toBe('Visita dal dentista');
})->with(['link_documents', 'unlink_documents']);

it('answers 404 and saves nothing for a document of another family on a new movement', function (): void {
    $rossi = Family::factory()->create();
    $foreign = CurrentFamily::using($rossi->id, fn (): Document => Document::factory()->create(['family_id' => $rossi->id]));

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'Zzz', 'amount' => 3, 'date' => '2026-10-25', 'link_documents' => [$foreign->id]])
        ->assertNotFound();

    expect(insideBianchi(fn (): int => Expense::query()->where('title', 'Zzz')->count()))->toBe(0);
});

it('opens Import with the files sent with a new movement, linked to it', function (): void {

    $response = $this->actingAs($this->marco)->post('/budget/movements', [
        'kind' => 'expense', 'title' => 'Con scontrino', 'amount' => 9, 'date' => '2026-10-25',
        'files' => [UploadedFile::fake()->create('scontrino.pdf', 100, 'application/pdf')],
    ]);

    $expense = movementExpense('Con scontrino');
    $file = insideBianchi(fn (): File => File::query()->toSort()->where('link_type', 'expense')->sole());

    $response->assertRedirect("/import?files={$file->id}&link=expense%3A{$expense->id}");
    expect($file->link_type)->toBe('expense')->and($file->link_id)->toBe($expense->id);
});

it('opens Import with the files sent when a movement is changed, even by a member', function (): void {
    $income = insideBianchi(fn (): Income => Income::query()->latest('date')->firstOrFail());

    $this->actingAs($this->rosa)
        ->put("/budget/movements/income/{$income->id}", [
            '_method' => 'put', 'title' => $income->title, 'amount' => 10, 'date' => '2026-10-02',
            'files' => [UploadedFile::fake()->create('busta.pdf', 100, 'application/pdf')],
        ])
        ->assertRedirectContains('link=income%3A'.$income->id);

    expect(insideBianchi(fn (): string => File::query()->toSort()->where('link_type', 'income')->sole()->link_type))->toBe('income');
});

it('refuses a file that is not a document', function (): void {

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'Zzz', 'amount' => 3, 'date' => '2026-10-25', 'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]])
        ->assertSessionHasErrors('files.0');
});

it('goes back to the page it came from after saving', function (): void {
    $dentist = movementExpense('Visita dal dentista');
    $list = '/budget/movements?month=2026-10&kind=expense';

    $this->actingAs($this->marco)
        ->put("/budget/movements/expense/{$dentist->id}", ['title' => 'Dentista', 'amount' => 80, 'date' => '2026-10-10', 'from' => $list])
        ->assertRedirect($list);

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'Altro', 'amount' => 3, 'date' => '2026-10-25', 'from' => $list])
        ->assertRedirect($list);
});

it('goes to the month, not to a movement, when `from` is not a path of the app', function (): void {
    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'Altro', 'amount' => 3, 'date' => '2026-10-25', 'from' => 'https://x.test'])
        ->assertRedirect('/budget/movements?month=2026-10');
});

it('keeps going back to the card when there are no files', function (): void {
    $tv = insideBianchi(fn (): int => Belonging::query()->where('name', 'TV Samsung')->sole()->id);

    $this->actingAs($this->marco)
        ->post('/budget/movements', ['kind' => 'expense', 'title' => 'Staffa', 'amount' => 35, 'date' => '2026-10-20', 'card' => "belonging:{$tv}", 'back_to_card' => true, 'from' => '/budget/movements?month=2026-10'])
        ->assertRedirect("/cards/belonging/{$tv}");
});

it('does not send the documents of the movements in the month list or in the search, but sends them on the movement page', function (): void {
    $parking = movementExpense('Parcheggio');
    $linked = movementDocument('Ricevuta del parcheggio');
    insideBianchi(fn () => $parking->documents()->attach($linked));

    $this->actingAs($this->marco)
        ->get('/budget/movements?month=2026-10')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('movements.0.title', 'Parcheggio')
            ->where('movements.0.documents', []));

    $this->actingAs($this->marco)
        ->get('/budget/movements?search=Parcheggio')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('search.months.0.movements.0.documents', []));

    $this->actingAs($this->marco)
        ->get("/budget/movements/expense/{$parking->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('movement.documents.0.id', $linked->id));
});

it('gives the page of a movement the documents to link and where to go back', function (): void {
    $dentist = movementExpense('Visita dal dentista');
    $linked = movementDocument('Già collegato');
    insideBianchi(fn () => $dentist->documents()->attach($linked));
    $free = movementDocument('Da collegare');

    $this->actingAs($this->marco)
        ->get("/budget/movements/expense/{$dentist->id}?from=/budget/movements%3Fmonth%3D2026-10")
        ->assertInertia(function (AssertableInertia $page) use ($free, $linked): void {
            $props = $page->toArray()['props'];
            $ids = collect($props['linkable'])->pluck('id');

            expect($props['back'])->toBe('/budget/movements?month=2026-10')
                ->and($props['hasLinkable'])->toBeTrue()
                ->and($props['canDeleteDocuments'])->toBeTrue()
                ->and($ids)->toContain($free->id)->not->toContain($linked->id)
                ->and($props)->toHaveKeys(['linkableNext', 'linkableSearch']);
        });
});

it('does not take a `from` outside the app for the way back, and a member cannot delete documents', function (): void {
    $dentist = movementExpense('Visita dal dentista');

    $this->actingAs($this->rosa)
        ->get("/budget/movements/expense/{$dentist->id}?from=//x.test")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('back', null)->where('canDeleteDocuments', false));
});

it('offers every document on the form of a new movement', function (): void {
    movementDocument();

    $this->actingAs($this->marco)
        ->get('/budget/movements/create?kind=expense&from=/budget/movements')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('back', '/budget/movements')
            ->where('hasLinkable', true)
            ->has('linkable')
            ->where('canDeleteDocuments', true));
});
