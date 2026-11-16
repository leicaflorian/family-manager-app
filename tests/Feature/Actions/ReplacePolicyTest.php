<?php

declare(strict_types=1);

use App\Actions\Cards\ReplacePolicy;
use App\Enums\InsurancePolicyStatus;
use App\Enums\IntervalUnit;
use App\Enums\ReminderStatus;
use App\Models\Expense;
use App\Models\Family;
use App\Models\InsurancePolicy;
use App\Models\PlannedExpense;
use App\Models\Reminder;
use App\Services\Deadlines;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runs the action inside the family, like the caller does.
 *
 * @param  Collection<int, InsurancePolicy>  $old  the policies replaced
 */
function replacePolicy(Family $family, InsurancePolicy $new, Collection $old, ?Expense $payment = null): void
{
    CurrentFamily::using($family->id, fn () => DB::transaction(fn () => resolve(ReplacePolicy::class)->handle($new, $old, $payment)));
}

/**
 * Reads a policy again from the database.
 */
function reloaded(InsurancePolicy $policy): InsurancePolicy
{
    return InsurancePolicy::query()->withoutGlobalScopes()->findOrFail($policy->id);
}

/**
 * Makes a policy of a family.
 *
 * @param  array<string, mixed>  $state  the columns
 */
function policyOf(Family $family, array $state = []): InsurancePolicy
{
    return CurrentFamily::using($family->id, fn (): InsurancePolicy => InsurancePolicy::factory()->create(['family_id' => $family->id, 'status' => InsurancePolicyStatus::Active, ...$state]));
}

/**
 * Makes the yearly premium of a policy.
 *
 * @param  array<string, mixed>  $state  the columns
 */
function premiumOf(Family $family, InsurancePolicy $policy, array $state = []): PlannedExpense
{
    return CurrentFamily::using($family->id, fn (): PlannedExpense => PlannedExpense::factory()->forCard($policy)->create(['family_id' => $family->id, 'title' => 'Premium', 'amount' => 480, 'interval' => 1, 'interval_unit' => IntervalUnit::Year, 'start_date' => '2025-12-01', ...$state]));
}

/**
 * Makes the open automatic renewal reminder of a policy.
 */
function renewalReminderOf(Family $family, InsurancePolicy $policy): Reminder
{
    return CurrentFamily::using($family->id, function () use ($family, $policy): Reminder {
        $reminder = Reminder::factory()->create(['family_id' => $family->id, 'is_automatic' => true, 'source_field' => 'insurance_policies.expiry_date', 'due_date' => $policy->expiry_date, 'status' => ReminderStatus::Open]);
        DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => $policy->getMorphClass(), 'remindable_id' => $policy->id]);

        return $reminder;
    });
}

it('cancels the old policy and closes its renewal reminder', function (): void {
    $family = Family::factory()->create();
    $old = policyOf($family, ['expiry_date' => '2026-12-01']);
    $new = policyOf($family);
    $reminder = renewalReminderOf($family, $old);

    replacePolicy($family, $new, collect([$old]));

    expect(reloaded($old)->status)->toBe(InsurancePolicyStatus::Cancelled)
        ->and(Reminder::query()->withoutGlobalScopes()->findOrFail($reminder->id)->status)->toBe(ReminderStatus::Done);
});

it('moves the premium planned expense to the new policy', function (): void {
    $family = Family::factory()->create();
    $old = policyOf($family);
    $new = policyOf($family);
    $premium = premiumOf($family, $old);

    replacePolicy($family, $new, collect([$old]));

    $premium = PlannedExpense::query()->withoutGlobalScopes()->findOrFail($premium->id);
    expect($premium->card_id)->toBe($new->id)->and($premium->card_type)->toBe($new->getMorphClass())
        ->and($premium->title)->toBe('Premium')->and((float) $premium->amount)->toBe(480.0)->and($premium->interval_unit)->toBe(IntervalUnit::Year)
        ->and($premium->end_date)->toBeNull();
});

it('moves only the payment given', function (): void {
    $family = Family::factory()->create();
    $old = policyOf($family);
    $new = policyOf($family);
    [$older, $payment] = CurrentFamily::using($family->id, fn (): array => [
        Expense::factory()->create(['family_id' => $family->id, 'card_type' => $old->getMorphClass(), 'card_id' => $old->id]),
        Expense::factory()->create(['family_id' => $family->id, 'card_type' => $old->getMorphClass(), 'card_id' => $old->id]),
    ]);

    replacePolicy($family, $new, collect([$old]), $payment);

    expect(Expense::query()->withoutGlobalScopes()->findOrFail($payment->id)->card_id)->toBe($new->id)
        ->and(Expense::query()->withoutGlobalScopes()->findOrFail($older->id)->card_id)->toBe($old->id);
});

it('ends the old premium when the new policy already has one', function (): void {
    $family = Family::factory()->create();
    $old = policyOf($family, ['expiry_date' => '2026-12-01']);
    $new = policyOf($family);
    $oldPremium = premiumOf($family, $old);
    $newPremium = premiumOf($family, $new);

    replacePolicy($family, $new, collect([$old]));

    $oldPremium = PlannedExpense::query()->withoutGlobalScopes()->findOrFail($oldPremium->id);
    expect($oldPremium->end_date?->toDateString())->toBe('2026-11-30')->and($oldPremium->card_id)->toBe($old->id)
        ->and(PlannedExpense::query()->withoutGlobalScopes()->findOrFail($newPremium->id)->end_date)->toBeNull();
});

it('replaces two old policies at once', function (): void {
    $family = Family::factory()->create();
    $a = policyOf($family, ['expiry_date' => '2026-12-01']);
    $b = policyOf($family, ['expiry_date' => '2026-12-05']);
    $new = policyOf($family);
    $premiumA = premiumOf($family, $a);
    $premiumB = premiumOf($family, $b);

    replacePolicy($family, $new, collect([$a, $b]));

    expect(reloaded($a)->status)->toBe(InsurancePolicyStatus::Cancelled)->and(reloaded($b)->status)->toBe(InsurancePolicyStatus::Cancelled)
        ->and(PlannedExpense::query()->withoutGlobalScopes()->findOrFail($premiumA->id)->card_id)->toBe($new->id)
        ->and(PlannedExpense::query()->withoutGlobalScopes()->findOrFail($premiumB->id)->end_date?->toDateString())->toBe('2026-12-04');
});

it('leaves no open due date on the expiry day of the old premium', function (): void {
    $family = Family::factory()->create();
    $old = policyOf($family, ['expiry_date' => '2026-11-21']);
    $new = policyOf($family);
    $oldPremium = premiumOf($family, $old, ['start_date' => '2025-11-21']);
    premiumOf($family, $new, ['start_date' => '2026-11-21']);

    replacePolicy($family, $new, collect([$old]));

    $oldPremium = PlannedExpense::query()->withoutGlobalScopes()->findOrFail($oldPremium->id);
    $open = CurrentFamily::using($family->id, fn (): array => resolve(Deadlines::class)->between([$oldPremium], CarbonImmutable::parse('2026-11-21'), CarbonImmutable::parse('2026-11-21')));
    expect($oldPremium->end_date?->toDateString())->toBe('2026-11-20')->and($open)->toBe([]);
});

it('keeps the expiry day of the old premium when that due date is already paid', function (): void {
    $family = Family::factory()->create();
    $old = policyOf($family, ['expiry_date' => '2026-11-21']);
    $new = policyOf($family);
    $oldPremium = premiumOf($family, $old, ['start_date' => '2025-11-21']);
    premiumOf($family, $new, ['start_date' => '2026-11-21']);
    CurrentFamily::using($family->id, fn (): Expense => Expense::factory()->create(['family_id' => $family->id, 'planned_expense_id' => $oldPremium->id, 'date' => '2026-11-21', 'occurrence_date' => '2026-11-21']));

    replacePolicy($family, $new, collect([$old]));

    expect(PlannedExpense::query()->withoutGlobalScopes()->findOrFail($oldPremium->id)->end_date?->toDateString())->toBe('2026-11-21');
});

it('never moves the end of the old premium later', function (): void {
    $family = Family::factory()->create();
    $old = policyOf($family, ['expiry_date' => '2026-11-21']);
    $new = policyOf($family);
    $oldPremium = premiumOf($family, $old, ['end_date' => '2026-06-01']);
    premiumOf($family, $new);

    replacePolicy($family, $new, collect([$old]));

    expect(PlannedExpense::query()->withoutGlobalScopes()->findOrFail($oldPremium->id)->end_date?->toDateString())->toBe('2026-06-01');
});
