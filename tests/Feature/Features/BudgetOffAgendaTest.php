<?php

declare(strict_types=1);

use App\Data\AgendaEntryData;
use App\Models\InsurancePolicy;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Services\FamilyFeatures;
use App\Services\Reminders\WeeklySummaries;
use App\Support\CurrentFamily;
use App\Support\ModelId;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-23 10:00', 'Europe/Rome'));
});

afterEach(fn () => Date::setTestNow());

/**
 * A family with a policy that expires on Wednesday, its instalment the same day, a bill and a salary.
 *
 * @param  bool  $budget  whether the Budget is on
 * @return User the admin
 */
function budgetOffAgendaFamily(bool $budget): User
{
    $marco = registerFamily();

    insideFamilyOf($marco, function (): void {
        $policy = InsurancePolicy::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'RC auto', 'expiry_date' => '2026-11-25']);
        agendaPlanned($policy, 'Rata RC auto', 418.00, '2026-11-25', ['is_recurring' => false]);
        agendaPlanned(null, 'Bolletta luce', 50.00, '2026-11-26', ['is_recurring' => false]);
        PlannedIncome::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Stipendio', 'amount' => 1850, 'start_date' => '2026-11-27', 'is_recurring' => false]);
    });

    $marco->family->forceFill(['budget_enabled' => $budget])->save();
    resolve(FamilyFeatures::class)->forget($marco->family);

    return $marco->fresh();
}

test('with the Budget off the Agenda has the deadline of the policy without an amount and no payment nor income', function (): void {
    $marco = budgetOffAgendaFamily(false);

    $entries = $this->actingAs($marco)->get('/agenda')->assertOk()->viewData('page')['props']['agenda']['entries'];

    expect(collect($entries)->pluck('kind')->unique()->values()->all())->toBe(['deadline'])
        ->and(collect($entries)->pluck('amount')->filter()->all())->toBe([])
        ->and(collect($entries)->pluck('href')->filter(fn (string $href): bool => str_contains($href, '/budget'))->all())->toBe([])
        ->and(collect($entries)->contains(fn (array $entry): bool => str_contains((string) $entry['key'], 'insurance_policies.expiry_date')))->toBeTrue();
});

test('with the Budget on the Agenda keeps its payments', function (): void {
    $marco = budgetOffAgendaFamily(true);

    $kinds = collect($this->actingAs($marco)->get('/agenda')->assertOk()->viewData('page')['props']['agenda']['entries'])->pluck('kind');

    expect($kinds)->toContain('payment');
});

test('with the Budget off the Home has no total and no incoming incomes', function (): void {
    $home = $this->actingAs(budgetOffAgendaFamily(false))->get(route('dashboard'))->assertOk()->viewData('page')['props']['home'];

    expect($home['total'])->toBeNull()
        ->and($home['incoming'])->toBe([])
        ->and($home['payments'])->toBe(0)
        ->and($home['late'])->toBe(0)
        ->and(collect($home['entries'])->pluck('amount')->filter()->all())->toBe([])
        ->and(collect($home['entries'])->pluck('kind')->unique()->all())->each->toBe('deadline');
});

test('with the Budget on the Home has the total', function (): void {
    $home = $this->actingAs(budgetOffAgendaFamily(true))->get(route('dashboard'))->assertOk()->viewData('page')['props']['home'];

    expect($home['total'])->toBe(468.0)->and($home['incoming'])->not->toBe([]);
});

test('the week of a family without the Budget has nothing to pay and no total', function (): void {
    $marco = budgetOffAgendaFamily(false);

    $week = insideFamilyOf($marco, fn () => resolve(WeeklySummaries::class)->week(CarbonImmutable::parse('2026-11-23')));
    $policy = insideFamilyOf($marco, fn () => InsurancePolicy::query()->firstOrFail());

    expect($week->toPay)->toBe([])
        ->and($week->total)->toBe(0.0)
        ->and(array_map(static fn (AgendaEntryData $entry): string => $entry->key, $week->tasks))->toContain('deadline:insurance_policies.expiry_date:'.ModelId::of($policy).':2026-11-25')
        ->and(array_filter($week->tasks, static fn (AgendaEntryData $entry): bool => $entry->amount !== null))->toBe([]);
});

test('the week with the Budget on counts the payments', function (): void {
    $marco = budgetOffAgendaFamily(true);

    $week = insideFamilyOf($marco, fn () => resolve(WeeklySummaries::class)->week(CarbonImmutable::parse('2026-11-23')));

    expect($week->total)->toBe(468.0);
});

test('the subscribed calendar has no payment events with the Budget off', function (): void {
    $marco = budgetOffAgendaFamily(false);
    $this->actingAs($marco)->post(route('calendar.store'));
    $ics = $this->get(route('calendar.feed', ['token' => $marco->family->fresh()->calendar_token]))->assertOk()->getContent();

    expect($ics)->toContain('RC auto')
        ->not->toContain('Rata RC auto')
        ->not->toContain('Bolletta luce')
        ->not->toContain('Stipendio')
        ->not->toContain('/budget');
});

test('the subscribed calendar keeps the payments with the Budget on', function (): void {
    $marco = budgetOffAgendaFamily(true);
    $this->actingAs($marco)->post(route('calendar.store'));
    $ics = $this->get(route('calendar.feed', ['token' => $marco->family->fresh()->calendar_token]))->assertOk()->getContent();

    expect($ics)->toContain('Bolletta luce');
});
