<?php

declare(strict_types=1);

use App\Enums\InsurancePolicyStatus;
use App\Enums\Role;
use App\Models\Expense;
use App\Models\Family;
use App\Models\InsurancePolicy;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use App\Models\User;
use App\Support\PolicyReplacement;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    App::setLocale('en');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);
});

afterEach(fn () => Date::setTestNow());

/** The car policy of the Bianchi, expiring on 21/11/2026, whose premium is the planned expense «Assicurazione auto». */
function pandaPolicy(): InsurancePolicy
{
    return insideBianchi(fn (): InsurancePolicy => InsurancePolicy::query()->where('title', 'RC auto Panda')->sole());
}

/** The address of a due date of a planned expense of the Bianchi, found by its title. */
function renewalAddress(string $title, string $day, string $suffix = ''): string
{
    $planned = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', $title)->sole());

    return "/budget/planned/expense/{$planned->id}/occurrences/{$day}{$suffix}";
}

/** The payment of the car premium of 21/11/2026, if there is one. */
function premiumPayment(): ?Expense
{
    return insideBianchi(fn (): ?Expense => Expense::query()->whereDate('occurrence_date', '2026-11-21')->first());
}

/**
 * The automatic reminders of the expiry of a policy.
 *
 * @return list<array{due_date: string, status: string}>
 */
function policyReminders(InsurancePolicy $policy): array
{
    return insideBianchi(fn (): array => Reminder::query()->automaticFor('insurance_policies.expiry_date', $policy->getMorphClass(), [$policy->id])->orderBy('due_date')->get()
        ->map(static fn (Reminder $reminder): array => ['due_date' => $reminder->due_date->toDateString(), 'status' => $reminder->status->value])->all());
}

it('offers the renewal only near the expiry of the policy', function (?string $expiry, InsurancePolicyStatus $status, bool $offered): void {
    insideBianchi(fn (): bool => pandaPolicy()->forceFill(['expiry_date' => $expiry, 'status' => $status])->save());

    $this->actingAs($this->marco)
        ->get(renewalAddress('Assicurazione auto', '2026-11-21'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $offered
            ? $page->where('deadline.renewal.policy_id', pandaPolicy()->id)
                ->where('deadline.renewal.policy', 'RC auto Panda · UnipolSai')
                ->where('deadline.renewal.expiry_date', $expiry)
                ->where('deadline.renewal.proposed_expiry', CarbonImmutable::parse((string) $expiry)->addYear()->toDateString())
            : $page->where('deadline.renewal', null));
})->with([
    'in 30 days' => ['2026-11-25', InsurancePolicyStatus::Active, true],
    'in 31 days' => ['2026-11-26', InsurancePolicyStatus::Active, false],
    'expired last year' => ['2025-11-21', InsurancePolicyStatus::Expired, true],
    'cancelled' => ['2026-11-21', InsurancePolicyStatus::Cancelled, false],
    'without an expiry' => [null, InsurancePolicyStatus::Active, false],
]);

it('offers the renewal in the Agenda panel of the payment too', function (): void {
    $planned = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', 'Assicurazione auto')->sole());
    $key = urlencode("payment:{$planned->id}:2026-11-21");

    $this->actingAs($this->marco)
        ->get("/agenda?payment={$key}")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->reloadOnly(['payment'], fn (AssertableInertia $reload): AssertableInertia => $reload
                ->where('payment.renewal.policy_id', pandaPolicy()->id)));
});

it('offers nothing to a member', function (): void {
    $this->actingAs($this->rosa)
        ->get(renewalAddress('Assicurazione auto', '2026-11-21'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('deadline.renewal', null));

    $this->post(renewalAddress('Assicurazione auto', '2026-11-21', '/payment'), [
        'date' => '2026-10-26', 'amount' => 418, 'renewal' => 'same', 'new_expiry' => '2027-11-21', 'policy_expiry' => '2026-11-21',
    ])->assertSessionHasErrors('renewal');

    expect(premiumPayment())->toBeNull();
});

it('records only the payment by default', function (): void {
    $this->actingAs($this->marco)
        ->post(renewalAddress('Assicurazione auto', '2026-11-21', '/payment'), ['date' => '2026-10-26', 'amount' => 418])
        ->assertSessionHasNoErrors()
        ->assertRedirect(renewalAddress('Assicurazione auto', '2026-11-21'));

    expect(premiumPayment())->not->toBeNull()
        ->and(pandaPolicy()->expiry_date?->toDateString())->toBe('2026-11-21');
});

it('renews the policy with the same company', function (): void {
    insideBianchi(fn (): bool => pandaPolicy()->forceFill(['status' => InsurancePolicyStatus::Expired])->save());
    $policy = pandaPolicy();

    $this->actingAs($this->marco)
        ->post(renewalAddress('Assicurazione auto', '2026-11-21', '/payment'), [
            'date' => '2026-10-26', 'amount' => 418, 'renewal' => 'same', 'new_expiry' => '2027-11-21', 'policy_expiry' => '2026-11-21',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(renewalAddress('Assicurazione auto', '2026-11-21'));

    $policy = pandaPolicy();
    expect(premiumPayment())->not->toBeNull()
        ->and($policy->expiry_date?->toDateString())->toBe('2027-11-21')
        ->and($policy->status)->toBe(InsurancePolicyStatus::Active)
        ->and(policyReminders($policy))->toContain(['due_date' => '2026-11-21', 'status' => 'done']);
});

it('asks the new expiry with the same company', function (): void {
    $this->actingAs($this->marco)
        ->post(renewalAddress('Assicurazione auto', '2026-11-21', '/payment'), [
            'date' => '2026-10-26', 'amount' => 418, 'renewal' => 'same', 'policy_expiry' => '2026-11-21',
        ])
        ->assertSessionHasErrors('new_expiry');

    expect(premiumPayment())->toBeNull();
});

it('does not renew twice', function (): void {
    insideBianchi(fn (): bool => pandaPolicy()->forceFill(['expiry_date' => '2026-11-20'])->save());

    $this->actingAs($this->marco)
        ->post(renewalAddress('Assicurazione auto', '2026-11-21', '/payment'), [
            'date' => '2026-10-26', 'amount' => 418, 'renewal' => 'same', 'new_expiry' => '2027-11-21', 'policy_expiry' => '2026-11-21',
        ])
        ->assertSessionHasNoErrors();

    expect(premiumPayment())->not->toBeNull()
        ->and(pandaPolicy()->expiry_date?->toDateString())->toBe('2026-11-20');
});

it('opens the new policy form when the company changed', function (bool $fromAgenda): void {
    $response = $this->actingAs($this->marco)
        ->post(renewalAddress('Assicurazione auto', '2026-11-21', '/payment'), [
            'date' => '2026-10-26', 'amount' => 418, 'renewal' => 'changed', 'policy_expiry' => '2026-11-21', 'from_agenda' => $fromAgenda,
        ])
        ->assertSessionHasNoErrors();

    $payment = premiumPayment();
    $policy = pandaPolicy();
    expect($payment)->not->toBeNull();
    $response->assertRedirect(PolicyReplacement::createUrl($policy, $payment));
    expect($policy->status)->toBe(InsurancePolicyStatus::Active)
        ->and($policy->expiry_date?->toDateString())->toBe('2026-11-21');
})->with(['from the due date' => false, 'from the agenda' => true]);

it('refuses a renewal for a due date without one', function (): void {
    $this->actingAs($this->marco)
        ->post(renewalAddress('Retta danza', '2026-12-05', '/payment'), [
            'date' => '2026-10-26', 'amount' => 45, 'renewal' => 'same', 'new_expiry' => '2027-11-21', 'policy_expiry' => '2026-11-21',
        ])
        ->assertSessionHasErrors('renewal');

    expect(insideBianchi(fn (): int => Expense::query()->whereDate('occurrence_date', '2026-12-05')->count()))->toBe(0);
});

it('refuses a choice it does not know', function (): void {
    $this->actingAs($this->marco)
        ->post(renewalAddress('Assicurazione auto', '2026-11-21', '/payment'), ['date' => '2026-10-26', 'amount' => 418, 'renewal' => 'maybe'])
        ->assertSessionHasErrors('renewal');

    expect(premiumPayment())->toBeNull();
});

it('records only the payment when the policy was renewed elsewhere since the page opened', function (): void {
    insideBianchi(fn (): bool => pandaPolicy()->forceFill(['expiry_date' => '2027-11-21'])->save());

    $this->actingAs($this->marco)
        ->post(renewalAddress('Assicurazione auto', '2026-11-21', '/payment'), [
            'date' => '2026-10-26', 'amount' => 418, 'renewal' => 'same', 'new_expiry' => '2027-11-21', 'policy_expiry' => '2026-11-21',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(renewalAddress('Assicurazione auto', '2026-11-21'));

    expect(premiumPayment())->not->toBeNull()
        ->and(pandaPolicy()->expiry_date?->toDateString())->toBe('2027-11-21');
});

it('records only the payment when the policy was cancelled since the page opened', function (): void {
    insideBianchi(fn (): bool => pandaPolicy()->forceFill(['status' => InsurancePolicyStatus::Cancelled])->save());

    $this->actingAs($this->marco)
        ->post(renewalAddress('Assicurazione auto', '2026-11-21', '/payment'), [
            'date' => '2026-10-26', 'amount' => 418, 'renewal' => 'changed', 'policy_expiry' => '2026-11-21',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(renewalAddress('Assicurazione auto', '2026-11-21'));

    $policy = pandaPolicy();
    expect(premiumPayment())->not->toBeNull()
        ->and($policy->status)->toBe(InsurancePolicyStatus::Cancelled)
        ->and($policy->expiry_date?->toDateString())->toBe('2026-11-21');
});

it('records only the payment when the company changed but the expiry moved since the page opened', function (): void {
    insideBianchi(fn (): bool => pandaPolicy()->forceFill(['expiry_date' => '2026-11-20'])->save());

    $this->actingAs($this->marco)
        ->post(renewalAddress('Assicurazione auto', '2026-11-21', '/payment'), [
            'date' => '2026-10-26', 'amount' => 418, 'renewal' => 'changed', 'policy_expiry' => '2026-11-21',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(renewalAddress('Assicurazione auto', '2026-11-21'));

    expect(premiumPayment())->not->toBeNull();
});
