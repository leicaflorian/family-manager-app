<?php

declare(strict_types=1);

use App\Enums\InsurancePolicyStatus;
use App\Enums\IntervalUnit;
use App\Enums\Role;
use App\Models\Expense;
use App\Models\InsurancePolicy;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use App\Support\PolicyReplacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->marco = registerFamily();
    $this->familyId = $this->marco->family_id;
    $this->vehicle = inPolicyFamily($this, fn (): Vehicle => Vehicle::factory()->create(['family_id' => $this->familyId]));
    $this->old = inPolicyFamily($this, fn (): InsurancePolicy => InsurancePolicy::factory()->create([
        'family_id' => $this->familyId,
        'title' => 'RC auto',
        'vehicle_id' => $this->vehicle->id,
        'expiry_date' => '2026-12-01',
    ]));
});

/**
 * Runs some work inside the family of the test.
 */
function inPolicyFamily(object $test, callable $work): mixed
{
    return CurrentFamily::using($test->familyId, $work);
}

/**
 * What the form of a new car policy sends.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function policyForm(object $test, array $extra = []): array
{
    return ['title' => 'RC auto 2027', 'type' => 'car', 'vehicle_id' => $test->vehicle->id, ...$extra];
}

/**
 * Makes the premium of a policy and a payment of it.
 */
function paymentOf(object $test, InsurancePolicy $policy): Expense
{
    return inPolicyFamily($test, function () use ($test, $policy): Expense {
        $planned = PlannedExpense::factory()->forCard($policy)->create(['family_id' => $test->familyId, 'title' => 'Premium', 'amount' => 480, 'interval' => 1, 'interval_unit' => IntervalUnit::Year]);

        return Expense::factory()->create(['family_id' => $test->familyId, 'planned_expense_id' => $planned->id, 'card_type' => $policy->getMorphClass(), 'card_id' => $policy->id]);
    });
}

/**
 * Reads a policy again.
 */
function fresh(object $test, InsurancePolicy $policy): InsurancePolicy
{
    return inPolicyFamily($test, fn (): InsurancePolicy => InsurancePolicy::query()->findOrFail($policy->id));
}

it('cancels the old policy when the new one is saved with it', function (): void {
    $this->actingAs($this->marco)->post(route('cards.store', 'insurance_policy'), policyForm($this, ['replace_policies' => [$this->old->id]]))->assertRedirect();

    expect(fresh($this, $this->old)->status)->toBe(InsurancePolicyStatus::Cancelled)
        ->and(inPolicyFamily($this, fn (): string => InsurancePolicy::query()->where('title', 'RC auto 2027')->sole()->status->value))->toBe('active');
});

it('moves the payment just made to the new policy', function (): void {
    $payment = paymentOf($this, $this->old);

    $this->actingAs($this->marco)->post(route('cards.store', 'insurance_policy'), policyForm($this, ['replace_policies' => [$this->old->id], 'replace_payment' => $payment->id]))->assertRedirect();

    $new = inPolicyFamily($this, fn (): InsurancePolicy => InsurancePolicy::query()->where('title', 'RC auto 2027')->sole());
    expect(inPolicyFamily($this, fn (): int => (int) Expense::query()->findOrFail($payment->id)->card_id))->toBe($new->id);
});

it('ignores a payment of another planned expense', function (): void {
    $other = inPolicyFamily($this, fn (): InsurancePolicy => InsurancePolicy::factory()->create(['family_id' => $this->familyId, 'title' => 'Other']));
    $payment = paymentOf($this, $other);

    $this->actingAs($this->marco)->post(route('cards.store', 'insurance_policy'), policyForm($this, ['replace_policies' => [$this->old->id], 'replace_payment' => $payment->id]))->assertRedirect();

    expect(inPolicyFamily($this, fn (): int => (int) Expense::query()->findOrFail($payment->id)->card_id))->toBe($other->id);
});

it('answers 404 for a policy of another family', function (): void {
    $stranger = registerFamily('Rossi', 'rossi@example.com');
    $foreign = CurrentFamily::using($stranger->family_id, fn (): InsurancePolicy => InsurancePolicy::factory()->create(['family_id' => $stranger->family_id]));

    $this->actingAs($this->marco)->post(route('cards.store', 'insurance_policy'), policyForm($this, ['replace_policies' => [$foreign->id]]))->assertNotFound();

    expect(inPolicyFamily($this, fn (): int => InsurancePolicy::query()->count()))->toBe(1)
        ->and(CurrentFamily::using($stranger->family_id, fn (): string => InsurancePolicy::query()->findOrFail($foreign->id)->status->value))->toBe('active');
});

it('refuses a policy already cancelled', function (): void {
    inPolicyFamily($this, fn (): bool => $this->old->forceFill(['status' => InsurancePolicyStatus::Cancelled])->save());

    $this->actingAs($this->marco)->post(route('cards.store', 'insurance_policy'), policyForm($this, ['replace_policies' => [$this->old->id]]))->assertSessionHasErrors('replace_policies');

    expect(inPolicyFamily($this, fn (): int => InsurancePolicy::query()->count()))->toBe(1);
});

it('cancels nothing when the form sends no policy to replace', function (): void {
    $this->actingAs($this->marco)->post(route('cards.store', 'insurance_policy'), policyForm($this))->assertRedirect();

    expect(fresh($this, $this->old)->status)->toBe(InsurancePolicyStatus::Active);
});

it('forbids a member', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);

    $this->actingAs($member)->post(route('cards.store', 'insurance_policy'), policyForm($this, ['replace_policies' => [$this->old->id]]))->assertForbidden();

    expect(fresh($this, $this->old)->status)->toBe(InsurancePolicyStatus::Active);
});

it('prefills the new policy from the old one', function (): void {
    $this->actingAs($this->marco)->get(PolicyReplacement::createUrl($this->old))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('cards/EditCard')
        ->where('values.title', 'RC auto')
        ->where('values.type', 'car')
        ->where('values.vehicle_id', (string) $this->vehicle->id)
        ->where('values.start_date', '2026-12-01')
        ->where('replaces', [$this->old->id])
        ->where('payment', null)
        ->has('replaceable', 1));
});

it('offers only candidates', function (): void {
    inPolicyFamily($this, fn (): bool => $this->old->forceFill(['status' => InsurancePolicyStatus::Cancelled])->save());

    $this->actingAs($this->marco)->get(route('cards.create', ['card' => 'insurance_policy', 'replaces' => $this->old->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('replaces', [])
        ->has('replaceable', 0));
});

it('ignores the policies to replace when an existing policy is updated', function (): void {
    $other = inPolicyFamily($this, fn (): InsurancePolicy => InsurancePolicy::factory()->create(['family_id' => $this->familyId, 'title' => 'Other', 'vehicle_id' => $this->vehicle->id]));

    $this->actingAs($this->marco)->post(route('cards.update', ['type' => 'insurance_policy', 'id' => $other->id]), policyForm($this, ['_method' => 'put', 'title' => 'Other renamed', 'replace_policies' => [$this->old->id]]))->assertRedirect();

    expect(fresh($this, $this->old)->status)->toBe(InsurancePolicyStatus::Active)
        ->and(fresh($this, $other)->title)->toBe('Other renamed');
});

it('saves the policy when the payment to move was deleted meanwhile', function (): void {
    $this->actingAs($this->marco)->post(route('cards.store', 'insurance_policy'), policyForm($this, ['replace_policies' => [$this->old->id], 'replace_payment' => 999999]))->assertRedirect();

    expect(fresh($this, $this->old)->status)->toBe(InsurancePolicyStatus::Cancelled)
        ->and(inPolicyFamily($this, fn (): int => InsurancePolicy::query()->where('title', 'RC auto 2027')->count()))->toBe(1);
});
