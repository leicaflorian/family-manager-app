<?php

declare(strict_types=1);

use App\Enums\IntervalUnit;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Support\CurrentFamily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->marco = registerFamily();
    $this->familyId = $this->marco->family_id;
    [$this->supplier, $this->holder, $this->account, $this->category] = inSubscriptionFamily($this, fn (): array => [
        Organization::factory()->create(['family_id' => $this->familyId, 'name' => 'Hostinger']),
        Person::factory()->create(['family_id' => $this->familyId]),
        FinancialAccount::factory()->create(['family_id' => $this->familyId]),
        ExpenseCategory::factory()->create(['family_id' => $this->familyId, 'name' => 'Abbonamenti']),
    ]);
});

/**
 * Runs some work inside the family of the test.
 */
function inSubscriptionFamily(object $test, callable $work): mixed
{
    return CurrentFamily::using($test->familyId, $work);
}

/**
 * What the form of a new subscription sends, with the planned expense section.
 *
 * @param  array<string, mixed>  $planned
 * @return array<string, mixed>
 */
function subscriptionForm(object $test, array $planned = []): array
{
    return [
        'name' => 'Hostinger',
        'type' => 'web',
        'organization_id' => $test->supplier->id,
        'person_id' => $test->holder->id,
        'financial_account_id' => $test->account->id,
        'activation_date' => '2026-11-15',
        'planned' => [
            'amount' => 35.88,
            'interval' => 1,
            'interval_unit' => 'year',
            'start_date' => '2026-11-15',
            'category_id' => $test->category->id,
            'paid' => false,
            ...$planned,
        ],
    ];
}

/**
 * The planned expenses of the family of the test.
 *
 * @return Collection<int, PlannedExpense>
 */
function subscriptionPlannedOfFamily(object $test): Collection
{
    return inSubscriptionFamily($test, fn (): Collection => PlannedExpense::query()->get()->toBase());
}

it('creates the planned expense of a new subscription', function (): void {
    $this->actingAs($this->marco)->post(route('cards.store', 'subscription'), subscriptionForm($this))->assertRedirect();

    $card = inSubscriptionFamily($this, fn (): Subscription => Subscription::query()->sole());
    $planned = subscriptionPlannedOfFamily($this)->sole();

    expect($planned->title)->toBe('Hostinger')
        ->and((float) $planned->amount)->toBe(35.88)
        ->and($planned->is_recurring)->toBeTrue()
        ->and($planned->interval)->toBe(1)
        ->and($planned->interval_unit)->toBe(IntervalUnit::Year)
        ->and($planned->start_date?->toDateString())->toBe('2026-11-15')
        ->and($planned->organization_id)->toBe($this->supplier->id)
        ->and($planned->person_id)->toBe($this->holder->id)
        ->and($planned->financial_account_id)->toBe($this->account->id)
        ->and($planned->category_id)->toBe($this->category->id)
        ->and($planned->card_type)->toBe('subscription')
        ->and($planned->card_id)->toBe($card->id)
        ->and(inSubscriptionFamily($this, fn (): int => Expense::query()->count()))->toBe(0);
});

it('records the first payment when asked', function (): void {
    $this->actingAs($this->marco)->post(route('cards.store', 'subscription'), subscriptionForm($this, ['paid' => true]))->assertRedirect();

    $planned = subscriptionPlannedOfFamily($this)->sole();
    $payment = inSubscriptionFamily($this, fn (): Expense => Expense::query()->sole());

    expect($payment->planned_expense_id)->toBe($planned->id)
        ->and($payment->occurrence_date?->toDateString())->toBe('2026-11-15')
        ->and($payment->date->toDateString())->toBe('2026-11-15')
        ->and((float) $payment->amount)->toBe(35.88);
});

it('creates nothing without a cost', function (): void {
    $this->actingAs($this->marco)->post(route('cards.store', 'subscription'), subscriptionForm($this, ['amount' => null, 'paid' => true]))->assertRedirect();

    expect(inSubscriptionFamily($this, fn (): int => Subscription::query()->count()))->toBe(1)
        ->and(subscriptionPlannedOfFamily($this))->toBeEmpty()
        ->and(inSubscriptionFamily($this, fn (): int => Expense::query()->count()))->toBe(0);
});

it('asks for the first payment when there is a cost', function (): void {
    $this->actingAs($this->marco)->post(route('cards.store', 'subscription'), subscriptionForm($this, ['start_date' => null]))->assertSessionHasErrors('planned.start_date');

    expect(inSubscriptionFamily($this, fn (): int => Subscription::query()->count()))->toBe(0)
        ->and(subscriptionPlannedOfFamily($this))->toBeEmpty();
});

it('refuses a category of another family', function (): void {
    $stranger = registerFamily('Rossi', 'rossi@example.com');
    $foreign = CurrentFamily::using($stranger->family_id, fn (): ExpenseCategory => ExpenseCategory::factory()->create(['family_id' => $stranger->family_id]));

    $this->actingAs($this->marco)->post(route('cards.store', 'subscription'), subscriptionForm($this, ['category_id' => $foreign->id]))->assertSessionHasErrors('planned.category_id');

    expect(inSubscriptionFamily($this, fn (): int => Subscription::query()->count()))->toBe(0);
});

it('ignores the section when changing a subscription', function (): void {
    $card = inSubscriptionFamily($this, fn (): Subscription => Subscription::factory()->create(['family_id' => $this->familyId, 'name' => 'Hostinger']));

    $this->actingAs($this->marco)->put(route('cards.update', ['type' => 'subscription', 'id' => $card->id]), subscriptionForm($this))->assertRedirect();

    expect(subscriptionPlannedOfFamily($this))->toBeEmpty();
});

it('ignores the section for another card type', function (): void {
    $this->actingAs($this->marco)->post(route('cards.store', 'insurance_policy'), [
        'title' => 'RC auto',
        'type' => 'car',
        'planned' => subscriptionForm($this)['planned'],
    ])->assertRedirect();

    expect(subscriptionPlannedOfFamily($this))->toBeEmpty();
});

it('offers the expense categories only on a new subscription', function (): void {
    $this->actingAs($this->marco)->get(route('cards.create', ['card' => 'subscription']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('expenseCategories', fn (Collection $options): bool => $options->contains('label', 'Abbonamenti')));

    $this->actingAs($this->marco)->get(route('cards.create', ['card' => 'insurance_policy']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->missing('expenseCategories'));
});
