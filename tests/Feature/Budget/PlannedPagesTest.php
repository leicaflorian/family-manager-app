<?php

declare(strict_types=1);

use App\Actions\Banks\MatchBankTransactions;
use App\Enums\Role;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Employment;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Utility;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);
});

/** The address of a planned expense of the Bianchi, found by its title. */
function plannedPageAddress(string $title, string $suffix = ''): string
{
    $planned = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', $title)->sole());

    return "/budget/planned/expense/{$planned->id}{$suffix}";
}

it('lists the active planned expenses and incomes by next due date', function (): void {
    $this->actingAs($this->marco)
        ->get('/budget/planned')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/Planned')
            ->has('expenses', 12)
            ->has('incomes', 2)
            ->has('ended', 0)
            ->where('monthly_out', 1482.6)
            ->where('monthly_in', 3570)
            ->where('expenses.0.title', 'Gas')
            ->where('expenses.0.next_date', '2026-10-30')
            ->where('expenses.0.estimated', true)
            ->where('expenses.0.person', 'Marco')
            ->where('expenses.0.account', 'Conto BPER')
            ->where('incomes.0.title', 'Stipendio di Marco')
            ->where('incomes.0.next_date', '2026-10-27')
            ->where('canEdit', true));
});

it('shows the next due date that is not late and not paid', function (): void {
    $this->actingAs($this->marco)
        ->get('/budget/planned')
        ->assertInertia(function (AssertableInertia $page): void {
            $rows = collect($page->toArray()['props']['expenses'])->keyBy('title');

            // The 23/10 bill is late, the 23/11 one moved to 06/11
            expect($rows['Luce']['next_date'])->toBe('2026-11-06')
                // The 05/11 instalment was paid on 20/10
                ->and($rows['Retta danza']['next_date'])->toBe('2026-12-05')
                ->and($rows['Netflix']['recurring'])->toBeTrue()
                ->and($rows['Netflix']['person'])->toBe('Laura')
                ->and($rows['Spesa']['person'])->toBeNull();
        });
});

it('puts the planned expenses that ended in the ended list', function (): void {
    insideBianchi(fn (): int => PlannedExpense::query()->where('title', 'Netflix')->update(['end_date' => '2026-10-01']));

    $this->actingAs($this->marco)
        ->get('/budget/planned')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('expenses', 11)
            ->has('ended', 1)
            ->where('ended.0.title', 'Netflix')
            ->where('ended.0.ended', true));
});

it('shows a planned expense with its year, next due dates and exceptions', function (): void {
    $this->actingAs($this->marco)
        ->get(plannedPageAddress('Retta danza'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/PlannedItem')
            ->where('detail.row.title', 'Retta danza')
            ->where('detail.year_total', 465)
            ->where('detail.skipped_months', [7, 8])
            ->has('detail.next', 4)
            ->where('detail.next.0.date', '2026-11-05')
            ->where('detail.next.0.payment.date', '2026-10-20')
            ->where('detail.next.1.date', '2026-12-05')
            ->where('detail.next.1.amount', 60)
            ->where('detail.next.1.base_amount', 45)
            ->has('detail.exceptions', 5)
            ->where('detail.exceptions.0.occurrence_date', '2027-08-05')
            ->where('detail.exceptions.0.type', 'skip')
            ->where('detail.exceptions.0.notes', 'Vacanze')
            ->where('detail.exceptions.0.deadline.original_date', '2027-08-05')
            ->where('detail.exceptions.0.deadline.skipped', true)
            ->where('detail.exceptions.2.occurrence_date', '2026-12-05')
            ->where('detail.exceptions.2.deadline.amount', 60)
            ->where('detail.exceptions.2.deadline.base_amount', 45)
            ->where('detail.exceptions.2.deadline.skipped', false)
            ->has('detail.payments')
            ->where('detail.payments.0.year', 2026)
            ->where('detail.can_edit', true)
            ->where('detail.can_pay', true)
            ->where('accounts.0.label', 'Conto BPER'));
});

it('shows the data of a planned expense', function (): void {
    App::setLocale('en');

    $this->actingAs($this->marco)
        ->get(plannedPageAddress('Retta danza'))
        ->assertInertia(function (AssertableInertia $page): void {
            $values = collect($page->toArray()['props']['detail']['values'])->pluck('text', 'label');

            expect($values['Category'])->toBe('Scuola e attività › Sport')
                ->and($values['Organization'])->toBe('Scuola Passo a Due')
                ->and($values['Bank account'])->toBe('Carta Laura')
                ->and($values['Holder'])->toBe('Giulia')
                ->and($values)->toHaveKey('Refers to');
        });
});

it('shows a planned income', function (): void {
    $planned = insideBianchi(fn (): PlannedIncome => PlannedIncome::query()->where('title', 'Stipendio di Laura')->sole());

    $this->actingAs($this->marco)
        ->get("/budget/planned/income/{$planned->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('detail.row.title', 'Stipendio di Laura')
            ->where('detail.row.amount', 1720)
            ->where('detail.row.account', 'Conto BPER')
            ->where('detail.year_total', 20640)
            ->where('kind', 'income'));
});

it('prefills a new planned expense from a card', function (): void {
    $netflix = insideBianchi(fn (): Subscription => Subscription::query()->where('name', 'Netflix')->sole());

    $this->actingAs($this->marco)
        ->get("/budget/planned/expense/create?card=subscription:{$netflix->id}")
        ->assertInertia(function (AssertableInertia $page) use ($netflix): void {
            $page->component('budget/EditPlanned')
                ->where('planned', null)
                ->where('kind', 'expense')
                ->where('values.title', 'Netflix')
                ->where('values.card', "subscription:{$netflix->id}")
                ->where('values.interval', 1)
                ->where('values.interval_unit', 'month')
                ->where('values.is_recurring', true)
                ->where('values.start_date', '2026-10-05');

            $props = $page->toArray()['props'];
            $options = $props['options'];

            expect(collect($options['organizations'])->firstWhere('value', $props['values']['organization_id'])['label'])->toBe('Netflix')
                ->and(collect($options['accounts'])->firstWhere('value', $props['values']['financial_account_id'])['label'])->toBe('Carta Laura')
                ->and(collect($options['people'])->firstWhere('value', $props['values']['person_id'])['label'])->toContain('Laura');
        });
});

it('opens a new planned expense without a card and ignores a card of nowhere', function (string $card): void {
    $this->actingAs($this->marco)
        ->get("/budget/planned/expense/create?card={$card}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('values.card', null)
            ->where('values.title', '')
            ->where('values.interval_unit', 'month'));
})->with(['nonsense', 'subscription:99999', 'boat:1']);

it('starts a policy every year', function (): void {
    $policy = insideBianchi(fn (): InsurancePolicy => InsurancePolicy::query()->firstOrFail());

    $this->actingAs($this->marco)
        ->get("/budget/planned/expense/create?card=insurance_policy:{$policy->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('values.interval_unit', 'year')
            ->where('values.organization_id', (string) $policy->organization_id));
});

it('prefills a new planned expense from a loan, a utility and an enrollment', function (string $model, string $type, Closure $title): void {
    $card = insideBianchi(fn (): Model => $model::query()->firstOrFail());

    $this->actingAs($this->marco)
        ->withHeader('Accept-Language', 'it')
        ->get("/budget/planned/expense/create?card={$type}:{$card->getKey()}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('values.title', $title($card))
            ->where('values.card', "{$type}:{$card->getKey()}")
            ->where('values.interval_unit', 'month')
            ->where('values.organization_id', (string) $card->organization_id));
})->with([
    'loan' => [Loan::class, 'loan', fn (Loan $card): string => $card->name],
    'utility' => [Utility::class, 'utility', fn (Utility $card): string => $card->type->label()],
    'enrollment' => [Enrollment::class, 'enrollment', fn (): string => 'Quota sport'],
]);

it('gives the amount and the end of a loan to a new planned expense', function (): void {
    $loan = insideBianchi(fn (): Loan => Loan::query()->whereNotNull('installment_amount')->firstOrFail());

    $this->actingAs($this->marco)
        ->get("/budget/planned/expense/create?card=loan:{$loan->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('values.amount', fn (mixed $amount): bool => (float) $amount === (float) $loan->installment_amount)
            ->where('values.end_date', $loan->end_date?->toDateString())
            ->where('values.start_date', $loan->start_date?->toDateString()));
});

it('gives the categories with their path', function (): void {
    $this->actingAs($this->marco)
        ->get(plannedPageAddress('Retta danza', '/edit'))
        ->assertInertia(function (AssertableInertia $page): void {
            $labels = collect($page->toArray()['props']['options']['categories'])->pluck('label');

            expect($labels)->toContain('Scuola e attività')
                ->and($labels)->toContain('Scuola e attività › Sport')
                ->and($labels->search('Scuola e attività'))->toBeLessThan($labels->search('Scuola e attività › Sport'));
        });
});

it('opens the form of a planned expense with its values and the choices', function (): void {
    $this->actingAs($this->marco)
        ->get(plannedPageAddress('Retta danza', '/edit'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/EditPlanned')
            ->where('planned.title', 'Retta danza')
            ->where('values.title', 'Retta danza')
            ->where('values.amount', 45)
            ->where('values.interval_unit', 'month')
            ->where('values.start_date', '2025-09-05')
            ->where('values.end_date', null)
            ->has('options.accounts', 2)
            ->has('options.categories')
            ->has('options.organizations')
            ->has('options.people')
            ->has('options.cards')
            ->where('options.employments', [])
            ->where('options.income_types', []));
});

it('gives an income its type and its employments', function (): void {
    $this->actingAs($this->marco)
        ->get('/budget/planned/income/create')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('kind', 'income')
            ->has('options.income_types', 4)
            ->has('options.employments', 2)
            ->where('options.cards', [])
            ->where('values.type', 'salary'));
});

it('does not give the form to a member', function (): void {
    $this->actingAs($this->rosa)
        ->get('/budget/planned')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('canEdit', false));
});

it('links a new planned income to the employment of the card', function (): void {
    $job = insideBianchi(fn (): Employment => Employment::query()->whereRelation('organization', 'name', 'Studio Rinaldi')->sole());

    $this->actingAs($this->marco)
        ->get("/budget/planned/income/create?card=employment:{$job->id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/EditPlanned')
            ->where('kind', 'income')
            ->where('values.employment_id', (string) $job->id)
            ->where('values.person_id', (string) $job->person_id)
            ->where('values.organization_id', (string) $job->organization_id)
            ->where('values.type', 'salary'));
});

it('never presets a new planned income with an employment of another family', function (): void {
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): int => Employment::factory()->create(['family_id' => $rossi->family_id])->id);

    // As for an expense, a card of another family is ignored, not echoed
    $this->actingAs($this->marco)
        ->get("/budget/planned/income/create?card=employment:{$theirs}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('values.employment_id', null)
            ->where('values.person_id', null));
});

/**
 * Turns the bank link on for the Bianchi and gives them three booked Netflix payments, one a month, on one account.
 *
 * @param  list<string>  $amounts  the three amounts, oldest first
 * @return list<BankTransaction> the three transactions, oldest first
 */
function bankPlannedPagesNetflix(array $amounts = ['-13.99', '-13.99', '-15.99']): array
{
    config(['bank_feed.provider' => 'fake']);

    return insideBianchi(function () use ($amounts): array {
        $family = Family::query()->where('name', 'Bianchi')->sole();
        $family->forceFill(['bank_feed_enabled' => true])->save();
        $account = FinancialAccount::factory()->create(['family_id' => $family->id, 'name' => 'Conto BancoPosta']);
        $rows = [];

        foreach (['2026-08-12', '2026-09-12', '2026-10-12'] as $index => $day) {
            $rows[] = BankTransaction::factory()->create([
                'family_id' => $family->id,
                'financial_account_id' => $account->id,
                'text' => 'ADDEBITO DIRETTO SDD NETFLIX INTERNATIONAL BV '.($index + 1),
                'counterparty' => 'Netflix International',
                'booked_on' => $day,
                'value_on' => $day,
                'amount' => $amounts[$index],
            ]);
        }

        return $rows;
    });
}

it('the planned form starts from the bank transaction with a rule that matches it', function (): void {
    [$first, , $last] = bankPlannedPagesNetflix();

    $this->actingAs($this->marco)
        ->get(route('budget.planned.create', ['kind' => 'expense', 'bank' => $last->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/EditPlanned')
            ->where('values.title', 'Netflix International')
            ->where('values.amount', 15.99)
            ->where('values.is_estimated', true)
            ->where('values.start_date', '2026-08-12')
            ->where('values.is_recurring', true)
            ->where('values.interval', 1)
            ->where('values.interval_unit', 'month')
            ->where('values.financial_account_id', (string) $first->financial_account_id)
            ->missing('values.bank_match'));

    // The same amount every month: not estimated, and the title is the text when there is no counterparty
    foreach (insideBianchi(fn () => BankTransaction::query()->get()) as $row) {
        $row->forceFill(['amount' => '-13.99', 'counterparty' => null])->save();
    }
    $this->get(route('budget.planned.create', ['kind' => 'expense', 'bank' => $last->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('values.title', $last->text)
            ->where('values.amount', 13.99)
            ->where('values.is_estimated', false));
});

it('the planned form of a transaction that does not come back every month is empty', function (): void {
    [$first, , $last] = bankPlannedPagesNetflix();
    $alone = insideBianchi(fn (): BankTransaction => BankTransaction::factory()->create(['family_id' => $first->family_id, 'text' => 'BAR CENTRALE', 'booked_on' => '2026-10-20']));
    $foreign = BankTransaction::factory()->create(['family_id' => Family::factory()->create()->id]);
    $this->actingAs($this->marco);

    foreach ([$alone->id, $foreign->id, 'abc', '999999'] as $id) {
        $this->get(route('budget.planned.create', ['kind' => 'expense', 'bank' => $id]))->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('values.title', ''));
    }

    // An outgoing payment does not start a planned income
    $this->get(route('budget.planned.create', ['kind' => 'income', 'bank' => $last->id]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('values.title', ''));

    $first->family->forceFill(['bank_feed_enabled' => false])->save();
    $this->get(route('budget.planned.create', ['kind' => 'expense', 'bank' => $last->id]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('values.title', ''));
});

it('saving that planned expense pays nothing by itself, its filter pays the three due dates with the three transactions', function (): void {
    $rows = bankPlannedPagesNetflix();
    $values = $this->actingAs($this->marco)
        ->get(route('budget.planned.create', ['kind' => 'expense', 'bank' => $rows[2]->id]))
        ->viewData('page')['props']['values'];

    $this->post('/budget/planned/expense', Arr::only($values, ['title', 'amount', 'is_estimated', 'start_date', 'is_recurring', 'interval', 'interval_unit', 'financial_account_id']))
        ->assertRedirect();

    $planned = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', 'Netflix International')->sole());
    $payments = fn () => insideBianchi(fn () => Expense::query()->where('planned_expense_id', $planned->id)->oldest('occurrence_date')->get());

    // The form has no rule any more and pays nothing: the bank filters do the paying
    expect($values)->not->toHaveKey('bank_match')
        ->and($payments())->toHaveCount(0);

    insideBianchi(fn (): int => resolve(MatchBankTransactions::class)->forFilter(
        BankFilter::factory()->pays($planned)->create(['family_id' => $planned->family_id, 'contains' => [], 'pattern' => 'NETFLIX.*']),
    ));

    expect($payments()->map(fn ($payment): ?string => $payment->occurrence_date?->toDateString())->all())->toBe(['2026-08-12', '2026-09-12', '2026-10-12'])
        ->and($payments()->map(fn ($payment): string => (string) $payment->amount)->all())->toBe(['13.99', '13.99', '15.99'])
        ->and(insideBianchi(fn () => BankTransaction::query()->whereKey(array_map(fn (BankTransaction $row): int => $row->id, $rows))->whereNull('expense_id')->count()))->toBe(0);
});

it('the planned form carries its filter', function (): void {
    bankPlannedPagesNetflix();
    $planned = insideBianchi(fn (): PlannedExpense => PlannedExpense::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id, 'title' => 'Netflix']));
    $filter = insideBianchi(fn (): BankFilter => BankFilter::factory()->pays($planned)->create(['family_id' => $planned->family_id, 'name' => 'Netflix filter', 'contains' => ['NETFLIX']]));
    $bare = insideBianchi(fn (): PlannedExpense => PlannedExpense::factory()->create(['family_id' => $planned->family_id, 'title' => 'Palestra']));
    $filterUrl = route('budget.bank.filters.create', ['planned' => 'expense:'.$bare->id], absolute: false);

    // An admin sees the filter of the saved item, and where to make one for an item without it
    $this->actingAs($this->marco)->get(route('budget.planned.edit', ['kind' => 'expense', 'id' => $planned->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('bank.on', true)
            ->where('bank.filter.id', $filter->id)
            ->where('bank.filter.name', 'Netflix filter')
            ->where('bank.filter.contains', ['NETFLIX'])
            ->where('bank.create_url', route('budget.bank.filters.create', ['planned' => 'expense:'.$planned->id], absolute: false))
            ->missing('values.bank_match')
            ->missing('planned.bank_match'));

    $this->get(route('budget.planned.edit', ['kind' => 'expense', 'id' => $bare->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('bank.filter', null)
            ->where('bank.create_url', $filterUrl));

    // A new item has no filter yet and nowhere to make it
    $this->get(route('budget.planned.create', ['kind' => 'expense']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('bank.on', true)
            ->where('bank.filter', null)
            ->where('bank.create_url', null));

    // Only an admin can open the edit form, and so the link to a filter that pays is always one he may follow
    // Bank link off: no filter, no link
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();
    $this->get(route('budget.planned.edit', ['kind' => 'expense', 'id' => $planned->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('bank', ['on' => false, 'filter' => null, 'create_url' => null]));
});
