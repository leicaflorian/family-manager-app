<?php

declare(strict_types=1);

use App\Enums\IntervalUnit;
use App\Models\Expense;
use App\Models\Family;
use App\Models\File;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Reminders\MissingExpenses;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * Turns the Budget off for the family of an account.
 *
 * @param  User  $user  the account
 */
function budgetOffCardsSwitch(User $user): void
{
    Family::query()->whereKey($user->family_id)->sole()->forceFill(['budget_enabled' => false])->save();
}

/**
 * The Bianchi of the seeder, with the Budget off, and Marco's account.
 */
function budgetOffCardsBianchi(): User
{
    Storage::fake('local');
    test()->seed(BianchiFamilySeeder::class);
    insideBianchi(fn (): int => File::query()->toSort()->delete());
    $marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    budgetOffCardsSwitch($marco);

    return $marco;
}

afterEach(fn () => $this->travelBack());

test('a new subscription creates no expense with the Budget off, even when already paid', function (): void {
    $marco = registerFamily();
    budgetOffCardsSwitch($marco);

    $this->actingAs($marco)->post(route('cards.store', 'subscription'), [
        'name' => 'Hostinger',
        'type' => 'web',
        'planned' => ['amount' => 35.88, 'interval' => 1, 'interval_unit' => 'year', 'start_date' => '2026-11-15', 'paid' => true],
    ])->assertRedirect();

    insideFamilyOf($marco, function (): void {
        expect(Subscription::query()->sole()->name)->toBe('Hostinger')
            ->and(PlannedExpense::query()->count())->toBe(0)
            ->and(Expense::query()->count())->toBe(0);
    });
});

test('replacing a policy with the Budget off moves its premium but not its payment', function (): void {
    $marco = registerFamily();
    [$old, $premium, $payment] = insideFamilyOf($marco, function () use ($marco): array {
        $vehicle = Vehicle::factory()->create(['family_id' => $marco->family_id]);
        $old = InsurancePolicy::factory()->create(['family_id' => $marco->family_id, 'title' => 'RC auto', 'vehicle_id' => $vehicle->id, 'expiry_date' => '2026-12-01']);
        $premium = PlannedExpense::factory()->forCard($old)->create(['family_id' => $marco->family_id, 'title' => 'Premium', 'amount' => 480, 'interval' => 1, 'interval_unit' => IntervalUnit::Year]);
        $payment = Expense::factory()->create(['family_id' => $marco->family_id, 'planned_expense_id' => $premium->id, 'card_type' => $old->getMorphClass(), 'card_id' => $old->id]);

        return [$old, $premium, $payment];
    });
    budgetOffCardsSwitch($marco);

    $this->actingAs($marco)->post(route('cards.store', 'insurance_policy'), [
        'title' => 'RC auto 2027', 'type' => 'car', 'vehicle_id' => $old->vehicle_id,
        'replace_policies' => [$old->id], 'replace_payment' => $payment->id,
    ])->assertRedirect();

    insideFamilyOf($marco, function () use ($old, $premium, $payment): void {
        $new = InsurancePolicy::query()->where('title', 'RC auto 2027')->sole();

        // The premium moves while nobody sees it, so the Budget finds it right when turned on again
        expect(InsurancePolicy::query()->findOrFail($old->id)->status->value)->toBe('cancelled')
            ->and(Expense::query()->findOrFail($payment->id)->card_id)->toBe($old->id)
            ->and(PlannedExpense::query()->findOrFail($premium->id)->card_id)->toBe($new->id);
    });
});

test('a card has no payments nor consumption with the Budget off', function (): void {
    $this->travelTo('2026-10-26 10:00:00');
    $marco = budgetOffCardsBianchi();
    $luce = insideBianchi(fn (): Utility => Utility::query()->whereRelation('organization', 'name', 'Edison')->sole());

    $this->actingAs($marco)->get(route('cards.show', ['type' => 'utility', 'id' => $luce->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('cards/Card')
        ->where('card.budget', null)
        ->where('card.consumption', null));

    $this->actingAs($marco)->get(route('cards.edit', ['type' => 'utility', 'id' => $luce->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('cards/EditCard')
        ->where('budget', null));
});

test('the rows of an area have no amount due with the Budget off', function (): void {
    $this->travelTo('2026-10-26 10:00:00');
    $marco = budgetOffCardsBianchi();

    $this->actingAs($marco)->get(route('areas.show', 'home'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('Area')
        ->where('area.cards', fn ($cards): bool => collect($cards)->isNotEmpty() && collect($cards)->every(fn (array $card): bool => $card['due_amount'] === null)));
});

test('a new subscription page has no expense categories with the Budget off', function (): void {
    $marco = registerFamily();
    budgetOffCardsSwitch($marco);

    $this->actingAs($marco)->get(route('cards.create', ['card' => 'subscription']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('cards/EditCard')
        ->missing('expenseCategories'));
});

test('no reminder misses its planned expense with the Budget off', function (): void {
    $marco = registerFamily();
    budgetOffCardsSwitch($marco);

    insideFamilyOf($marco, function (): void {
        $panda = Vehicle::factory()->create(['family_id' => CurrentFamily::id()]);
        $reminder = Reminder::factory()->create(['family_id' => CurrentFamily::id(), 'is_automatic' => true, 'source_field' => 'vehicles.road_tax_due_date', 'due_date' => '2026-11-14']);
        DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => $panda->getMorphClass(), 'remindable_id' => $panda->id]);

        expect(resolve(MissingExpenses::class)->of(collect([$reminder->load('link')]), CarbonImmutable::today('Europe/Rome')))->toBe([]);
    });
});

test('a loan saved with the Budget off creates no planned expense, and works out its instalments from the dates', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00', 'Europe/Rome'));
    $marco = registerFamily();
    budgetOffCardsSwitch($marco);

    $this->actingAs($marco)->post(route('cards.store', 'loan'), [
        'name' => 'Mutuo casa',
        'start_date' => '2025-01-01',
        'installments_count' => 240,
        'installment_amount' => 650,
    ])->assertRedirect();

    $loan = insideFamilyOf($marco, fn (): Loan => Loan::query()->sole());

    expect(insideFamilyOf($marco, fn (): int => PlannedExpense::query()->count()))->toBe(0);

    // The card shows the instalment, the total and the instalments past, with nothing of the payments
    $this->actingAs($marco)->get(route('cards.show', ['type' => 'loan', 'id' => $loan->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        // Amounts come back from JSON as whole numbers
        ->where('card.budget.numbers', fn ($numbers): bool => collect($numbers)->map(fn (array $number): array => [$number['label'], (float) $number['amount']])->values()->all() === [['Instalment', 650.0], ['Total to pay', 156000.0]])
        ->where('card.budget.installments_paid', 23)
        ->where('card.budget.installments_count', 240)
        ->where('card.budget.paid_amount', fn ($amount): bool => (float) $amount === 14950.0)
        ->where('card.budget.left_amount', fn ($amount): bool => (float) $amount === 141050.0)
        ->where('card.budget.next', null)
        ->where('card.budget.due_label', null)
        ->where('card.budget.planned_label', null)
        ->where('card.budget.payments_label', null)
        ->where('card.budget.can_pay', false)
        ->where('card.budget.can_add_expense', false));

    // A later change of the loan does not create it either
    insideFamilyOf($marco, fn (): bool => $loan->update(['installment_amount' => 660]));

    expect(insideFamilyOf($marco, fn (): int => PlannedExpense::query()->count()))->toBe(0);
});
