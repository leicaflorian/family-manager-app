<?php

declare(strict_types=1);

use App\Data\CardBudgetData;
use App\Data\CardNumberData;
use App\Data\DeadlineData;
use App\Data\PaymentYearData;
use App\Data\PlannedRowData;
use App\Enums\Role;
use App\Models\Belonging;
use App\Models\Employment;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\HealthEvent;
use App\Models\Home;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\CardBudgets;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
});

afterEach(fn () => Date::setTestNow());

/**
 * The budget of a card of the Bianchi, as Marco reads it on 26/10/2026.
 *
 * @param  Closure(): Model  $card  finds the card inside the family
 */
function budgetOf(Closure $card, ?User $user = null): ?CardBudgetData
{
    $user ??= User::query()->where('email', 'marco.bianchi@example.com')->sole();

    return insideBianchi(fn (): ?CardBudgetData => resolve(CardBudgets::class)->of($card(), $user, CarbonImmutable::today('Europe/Rome')));
}

/**
 * A number of the budget, found by its label.
 */
function budgetNumber(CardBudgetData $budget, string $label): CardNumberData
{
    return collect($budget->numbers)->firstWhere('label', $label) ?? throw new RuntimeException("No number {$label}");
}

/**
 * The electricity of the Bianchi.
 */
function luce(): Utility
{
    return Utility::query()->whereRelation('organization', 'name', 'Edison')->sole();
}

it('shows the electricity of the canvas', function (): void {
    $budget = budgetOf(luce(...));

    expect($budget)->toBeInstanceOf(CardBudgetData::class);
    assert($budget instanceof CardBudgetData);

    $planned = budgetNumber($budget, 'Planned');
    $paid = budgetNumber($budget, 'Paid in 2026');

    expect($budget->next)->toBeNull()
        ->and($planned->amount)->toBe(72.0)
        ->and($planned->hint)->toBe('per month, estimated')
        ->and($paid->amount)->toBe(642.1)
        ->and($paid->hint)->toBe('9 bills')
        ->and($budget->due_label)->toBe('To pay')
        ->and($budget->due[0]->title)->toBe('Bolletta luce di settembre')
        ->and($budget->due[0]->date)->toBe('2026-10-23')
        ->and($budget->due[0]->amount)->toBe(71.3)
        ->and($budget->due[0]->late)->toBeTrue()
        ->and($budget->due[1]->date)->toBe('2026-11-06')
        ->and($budget->due[1]->amount)->toBe(62.4)
        ->and($budget->due[1]->late)->toBeFalse()
        ->and($budget->planned_label)->toBe('Expected expenses')
        ->and(array_map(fn (PlannedRowData $row): string => $row->title, $budget->planned))->toBe(['Luce'])
        ->and($budget->can_add_planned)->toBeTrue()
        ->and($budget->payments_label)->toBe('Bills paid')
        ->and($budget->payments[0]->year)->toBe(2026)
        ->and($budget->payments[0]->total)->toBe(642.1)
        ->and($budget->payments[0]->payments[0]->title)->toBe('Bolletta luce di agosto')
        ->and($budget->payments[0]->payments[0]->date)->toBe('2026-09-23')
        ->and($budget->payments[0]->payments[0]->account)->toBe('Conto BPER')
        ->and($budget->payments[0]->payments[0]->amount)->toBe(68.9)
        ->and($budget->can_pay)->toBeTrue()
        ->and(array_map(fn (DeadlineData $deadline): string => $deadline->original_date, $budget->pay))->toBe(['2026-10-23']);
});

it('shows the renewal of a policy and its premiums', function (): void {
    $budget = budgetOf(fn (): InsurancePolicy => InsurancePolicy::query()->sole());
    assert($budget instanceof CardBudgetData);
    $premium = budgetNumber($budget, 'Premium');

    expect($budget->next_label)->toBe('Renewal')
        ->and($budget->next?->title)->toBe('Assicurazione auto')
        ->and($budget->next?->amount)->toBe(418.0)
        ->and($budget->next?->date)->toBe('2026-11-21')
        ->and($premium->amount)->toBe(418.0)
        ->and($premium->hint)->toBe('every year')
        ->and($budget->payments_label)->toBe('Premiums paid')
        ->and($budget->payments[0]->year)->toBe(2025)
        ->and($budget->payments[0]->total)->toBe(418.0);
});

it('shows the instalment of a loan without planned expenses', function (): void {
    $budget = budgetOf(fn (): Loan => Loan::query()->where('name', 'Finanziamento Panda')->sole());
    assert($budget instanceof CardBudgetData);

    expect($budget->next_label)->toBe('Next instalment')
        ->and($budget->next?->date)->toBe('2026-11-10')
        ->and($budget->next?->amount)->toBe(189.0)
        ->and($budget->installment_number)->toBe(33)
        ->and($budget->installments_count)->toBe(48)
        ->and($budget->installments_paid)->toBe(32)
        ->and($budget->paid_amount)->toBe(6048.0)
        ->and($budget->left_amount)->toBe(3024.0)
        ->and(budgetNumber($budget, 'Instalment')->amount)->toBe(189.0)
        ->and(budgetNumber($budget, 'Instalment')->hint)->toBe('every month, on day 10')
        ->and(budgetNumber($budget, 'Total to pay')->amount)->toBe(9072.0)
        ->and(budgetNumber($budget, 'Total to pay')->hint_amount)->toBe(8000.0)
        ->and(budgetNumber($budget, 'Cost')->amount)->toBe(1072.0)
        ->and($budget->planned_label)->toBeNull()
        ->and($budget->planned)->toBe([])
        ->and($budget->payments_label)->toBe('Instalments paid')
        ->and($budget->payments[0]->total)->toBe(1890.0)
        ->and($budget->can_pay)->toBeTrue();
});

it('shows the income of a job', function (): void {
    $budget = budgetOf(fn (): Employment => Employment::query()->whereRelation('organization', 'name', 'Studio Rinaldi')->sole());
    assert($budget instanceof CardBudgetData);
    $year = budgetNumber($budget, 'In 2026');

    expect($budget->next_label)->toBe('Arriving')
        ->and($budget->next?->kind)->toBe('income')
        ->and($budget->next?->date)->toBe('2026-10-27')
        ->and($budget->next?->amount)->toBe(1850.0)
        ->and($budget->kind)->toBe('income')
        ->and(budgetNumber($budget, 'Salary')->amount)->toBe(1850.0)
        ->and($year->amount)->toBe(16650.0)
        ->and($year->hint)->toBe('9 salaries received')
        ->and($budget->planned_label)->toBe('Expected income')
        ->and($budget->payments_label)->toBe('Salaries received')
        ->and($budget->payments[0]->count)->toBe(9);
});

it('sums what a vehicle costs with its policies', function (): void {
    $budget = budgetOf(fn (): Vehicle => Vehicle::query()->sole());
    assert($budget instanceof CardBudgetData);
    $cost = budgetNumber($budget, 'What you pay');

    // Fuel 12 × 80,00, the road tax 198,00 and the policy 418,00: the board leaves out the road tax and the fuel.
    expect($cost->amount)->toBe(1576.0)
        ->and($cost->hint)->toBe('in the next 12 months')
        ->and($budget->next_label)->toBe('Next due date')
        ->and($budget->next?->title)->toBe('Carburante')
        ->and($budget->next?->date)->toBe('2026-11-01')
        ->and($budget->planned_label)->toBeNull()
        ->and($budget->payments_label)->toBe('Expenses');
});

it('sums what a home costs with its utilities', function (): void {
    $budget = budgetOf(fn (): Home => Home::query()->sole());
    assert($budget instanceof CardBudgetData);
    $utilities = budgetNumber($budget, 'Utilities');

    // Electricity 854,40, gas 689,20, water 227,90 and internet 358,80; the mortgage is a loan, not of the home.
    expect(budgetNumber($budget, 'What you pay')->amount)->toBe(2130.3)
        ->and($utilities->amount)->toBeNull()
        ->and($utilities->count)->toBe(4)
        ->and($utilities->hint)->toBe('electricity, gas, water, internet')
        ->and($budget->next)->toBeNull()
        ->and($budget->due_label)->toBe('Next due dates')
        ->and($budget->due[0]->title)->toBe('Bolletta luce di settembre')
        ->and($budget->due[0]->late)->toBeTrue()
        ->and(array_map(fn (DeadlineData $deadline): string => $deadline->planned_title, $budget->due))->toBe(['Luce', 'Gas', 'Acqua', 'Internet - Fastweb', 'Luce']);
});

it('shows the movements of an account in this month', function (): void {
    $budget = budgetOf(fn (): FinancialAccount => FinancialAccount::query()->where('name', 'Conto BPER')->sole());
    assert($budget instanceof CardBudgetData);
    $in = budgetNumber($budget, 'Income in October');
    $out = budgetNumber($budget, 'Spending in October');

    expect($in->amount)->toBe(1720.0)
        ->and($in->hint)->toBe('1 salary of 2')
        ->and($out->amount)->toBe(1104.2)
        ->and($out->hint)->toBe('11 payments')
        ->and($budget->due_label)->toBe('Going out from here')
        ->and($budget->due[0]->planned_title)->toBe('Gas')
        ->and(collect($budget->due)->pluck('planned_title')->all())->toContain('Mutuo casa')
        ->and(collect($budget->due)->pluck('planned_title')->all())->not->toContain('Retta danza')
        ->and(collect($budget->due)->every(fn (DeadlineData $deadline): bool => ! $deadline->late && $deadline->date <= '2026-11-25'))->toBeTrue()
        ->and($budget->payments_label)->toBeNull();
});

it('gives no salaries to an account that never receives one', function (): void {
    $budget = budgetOf(fn (): FinancialAccount => FinancialAccount::query()->where('name', 'Carta Laura')->sole());
    assert($budget instanceof CardBudgetData);

    expect(budgetNumber($budget, 'Income in October')->hint)->toBeNull();
});

it('asks to create the planned expense first', function (): void {
    $budget = budgetOf(fn (): Subscription => Subscription::query()->create(['name' => 'Spotify', 'type' => 'streaming']));
    assert($budget instanceof CardBudgetData);

    expect($budget->planned)->toBe([])
        ->and($budget->payments)->toBe([])
        ->and($budget->next)->toBeNull()
        ->and($budget->can_pay)->toBeFalse()
        ->and($budget->can_add_planned)->toBeTrue();
});

it('lets a member pay but not add a planned expense', function (): void {
    $rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);
    $budget = budgetOf(luce(...), $rosa);
    assert($budget instanceof CardBudgetData);

    expect($budget->can_pay)->toBeTrue()->and($budget->can_add_planned)->toBeFalse();
});

it('gives a card without planned expenses only its expenses', function (): void {
    $budget = budgetOf(fn (): Model => Belonging::query()->sole());
    assert($budget instanceof CardBudgetData);

    expect($budget->payments_label)->toBe('Expenses')
        ->and($budget->payments)->toBe([])
        ->and($budget->planned_label)->toBeNull()
        ->and($budget->can_pay)->toBeFalse();
});

it('never counts a planned expense of another family', function (): void {
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $luce = insideBianchi(luce(...));
    CurrentFamily::using($rossi->family_id, fn () => PlannedExpense::factory()->forCard($luce)->create([
        'family_id' => $rossi->family_id, 'title' => 'Furto', 'amount' => 999, 'start_date' => '2026-10-01',
    ]));

    $budget = budgetOf(luce(...));
    assert($budget instanceof CardBudgetData);

    expect(array_map(fn (PlannedRowData $row): string => $row->title, $budget->planned))->toBe(['Luce'])
        ->and(budgetNumber($budget, 'Planned')->amount)->toBe(72.0)
        ->and(collect($budget->due)->pluck('planned_title')->unique()->all())->toBe(['Luce']);
});

it('opens the payments of a card year by year', function (): void {
    $id = insideBianchi(fn (): int => luce()->id);

    $this->actingAs($this->marco)
        ->get("/cards/utility/{$id}/payments")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('cards/CardPayments')
            ->where('card.label', 'Electricity - Edison')
            ->where('budget.payments_label', 'Bills paid')
            ->where('budget.payments.0.total', 642.1)
            ->has('budget.payments.0.payments', 9)
            ->where('numbers.0.label', 'Paid in 2026')
            ->where('numbers.0.amount', 642.1)
            ->where('numbers.1.label', 'On average')
            ->where('numbers.1.amount', 71.34)
            ->where('numbers.1.hint', 'per bill, :amount planned')
            ->where('numbers.1.hint_amount', 72)
            ->where('counts.2026', '9 bills'));
});

it('answers 404 for the payments of a card of another family', function (): void {
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): int => Utility::factory()->create(['family_id' => $rossi->family_id])->id);

    $this->actingAs($this->marco)->get("/cards/utility/{$theirs}/payments")->assertNotFound();
});

it('sends the payments with the card and with its form', function (): void {
    $id = insideBianchi(fn (): int => luce()->id);

    $this->actingAs($this->marco)
        ->get("/cards/utility/{$id}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('card.budget.due.0.title', 'Bolletta luce di settembre')
            ->where('card.budget.planned.0.title', 'Luce'));
    $this->actingAs($this->marco)
        ->get("/cards/utility/{$id}/edit")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('budget.planned.0.title', 'Luce')
            ->where('budget.payments.0.year', 2026));
});

it('offers incomes on a job without a planned income', function (): void {
    $job = fn (): Employment => Employment::query()->create(['person_id' => Person::query()->where('first_name', 'Laura')->sole()->id, 'job_title' => 'Consulente']);
    $budget = budgetOf($job);
    assert($budget instanceof CardBudgetData);
    $rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);
    $member = budgetOf(fn (): Employment => Employment::query()->where('job_title', 'Consulente')->sole(), $rosa);
    assert($member instanceof CardBudgetData);

    expect($budget->kind)->toBe('income')
        ->and($budget->planned_label)->toBe('Expected income')
        ->and($budget->planned)->toBe([])
        ->and($budget->can_add_planned)->toBeTrue()
        ->and($budget->can_pay)->toBeFalse()
        ->and($member->kind)->toBe('income')
        ->and($member->can_add_planned)->toBeFalse();
});

it('reads the figures from the planned item still running', function (): void {
    insideBianchi(function (): void {
        $policy = InsurancePolicy::query()->sole();
        PlannedExpense::query()->whereMorphedTo('card', $policy)->update(['end_date' => '2026-01-01']);
        PlannedExpense::factory()->forCard($policy)->create(['family_id' => $policy->family_id, 'title' => 'Assicurazione auto 2026', 'amount' => 450, 'start_date' => '2026-11-21', 'interval_unit' => 'year']);
    });

    $budget = budgetOf(fn (): InsurancePolicy => InsurancePolicy::query()->sole());
    assert($budget instanceof CardBudgetData);

    expect(budgetNumber($budget, 'Premium')->amount)->toBe(450.0);
});

it('calls the box of a subscription paid at least once the next payment', function (): void {
    $budget = budgetOf(fn (): Subscription => Subscription::query()->where('name', 'Netflix')->sole());
    assert($budget instanceof CardBudgetData);
    expect($budget->next_label)->toBe('First payment');

    insideBianchi(function (): void {
        $netflix = PlannedExpense::query()->where('title', 'Netflix')->sole();
        Expense::query()->create(['title' => 'Netflix', 'amount' => 13.99, 'date' => '2026-10-20', 'planned_expense_id' => $netflix->id, 'occurrence_date' => '2026-11-04']);
    });
    $paid = budgetOf(fn (): Subscription => Subscription::query()->where('name', 'Netflix')->sole());
    assert($paid instanceof CardBudgetData);

    expect($paid->next_label)->toBe('Next payment');
});

/**
 * The payments of a year of a budget, or null when the year has none.
 */
function paymentYear(CardBudgetData $budget, int $year): ?PaymentYearData
{
    return array_find($budget->payments, static fn (PaymentYearData $data): bool => $data->year === $year);
}

it('counts the expenses linked to the vehicle', function (): void {
    $before = budgetOf(fn (): Vehicle => Vehicle::query()->sole());
    assert($before instanceof CardBudgetData);
    $year = paymentYear($before, 2026);
    assert($year instanceof PaymentYearData);

    insideBianchi(fn () => Expense::query()->where('title', 'Parcheggio')->sole()->card()->associate(Vehicle::query()->sole())->save());
    $after = budgetOf(fn (): Vehicle => Vehicle::query()->sole());
    assert($after instanceof CardBudgetData);
    $linked = paymentYear($after, 2026);
    assert($linked instanceof PaymentYearData);
    $parking = collect($linked->payments)->firstWhere('title', 'Parcheggio');

    expect($after->payments_label)->toBe('Expenses')
        ->and($parking?->date)->toBe('2026-10-24')
        ->and($parking?->amount)->toBe(12.0)
        ->and($linked->count)->toBe($year->count + 1)
        ->and(round($linked->total - $year->total, 2))->toBe(12.0);
});

it('shows a payment linked to its card once', function (): void {
    insideBianchi(fn () => Expense::query()->where('title', 'Bollo auto')->sole()->card()->associate(Vehicle::query()->sole())->save());

    $budget = budgetOf(fn (): Vehicle => Vehicle::query()->sole());
    assert($budget instanceof CardBudgetData);
    $year = paymentYear($budget, 2026);
    assert($year instanceof PaymentYearData);

    expect(collect($year->payments)->where('title', 'Bollo auto')->count())->toBe(1);
});

it('shows the expenses of a health event', function (): void {
    insideBianchi(fn () => Expense::query()->create([
        'title' => 'Visita dentista', 'amount' => 80, 'date' => '2026-10-08', 'card_type' => 'health_event',
        'card_id' => HealthEvent::query()->where('title', 'Dentista di Laura')->sole()->id,
    ]));

    $budget = budgetOf(fn (): HealthEvent => HealthEvent::query()->where('title', 'Dentista di Laura')->sole());
    assert($budget instanceof CardBudgetData);

    expect($budget->payments_label)->toBe('Expenses')
        ->and($budget->next)->toBeNull()
        ->and($budget->planned_label)->toBeNull()
        ->and($budget->numbers)->toBe([])
        ->and($budget->due_label)->toBeNull()
        ->and($budget->can_pay)->toBeFalse()
        ->and($budget->payments[0]->year)->toBe(2026)
        ->and($budget->payments[0]->total)->toBe(80.0)
        ->and($budget->payments[0]->payments[0]->title)->toBe('Visita dentista');
});

it('opens the expenses of a health event year by year', function (): void {
    $id = insideBianchi(function (): int {
        $event = HealthEvent::query()->where('title', 'Dentista di Laura')->sole();
        Expense::query()->create(['title' => 'Visita dentista', 'amount' => 80, 'date' => '2026-10-08', 'card_type' => 'health_event', 'card_id' => $event->id]);

        return $event->id;
    });

    $this->actingAs($this->marco)
        ->get("/cards/health_event/{$id}/payments")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('cards/CardPayments')
            ->where('budget.payments_label', 'Expenses')
            ->where('budget.payments.0.total', 80)
            ->where('numbers.0.label', 'Paid in 2026')
            ->where('numbers.0.amount', 80));
});

it('never shows an expense of another family linked to the same id', function (): void {
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $panda = insideBianchi(fn (): Vehicle => Vehicle::query()->sole());
    $event = insideBianchi(fn (): HealthEvent => HealthEvent::query()->where('title', 'Dentista di Laura')->sole());
    CurrentFamily::using($rossi->family_id, function () use ($rossi, $panda, $event): void {
        Expense::query()->create(['family_id' => $rossi->family_id, 'title' => 'Furto', 'amount' => 999, 'date' => '2026-10-20', 'card_type' => 'vehicle', 'card_id' => $panda->id]);
        Expense::query()->create(['family_id' => $rossi->family_id, 'title' => 'Furto', 'amount' => 999, 'date' => '2026-10-20', 'card_type' => 'health_event', 'card_id' => $event->id]);
    });

    $vehicle = budgetOf(fn (): Vehicle => Vehicle::query()->sole());
    $health = budgetOf(fn (): HealthEvent => HealthEvent::query()->where('title', 'Dentista di Laura')->sole());
    assert($vehicle instanceof CardBudgetData && $health instanceof CardBudgetData);

    expect(collect($vehicle->payments)->flatMap(fn (PaymentYearData $year): array => $year->payments)->pluck('title')->all())->not->toContain('Furto')
        ->and($health->payments)->toBe([]);
});
