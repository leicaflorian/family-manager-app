<?php

declare(strict_types=1);

use App\Data\ReplaceablePolicyData;
use App\Enums\InsurancePolicyStatus;
use App\Enums\InsurancePolicyType;
use App\Models\Family;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ReplaceablePolicies;
use App\Support\CurrentFamily;

/**
 * Makes a policy of a family.
 *
 * @param  array<string, mixed>  $state  the columns
 */
function replaceablePolicy(Family $family, array $state = []): InsurancePolicy
{
    return CurrentFamily::using($family->id, fn (): InsurancePolicy => InsurancePolicy::factory()->create(['family_id' => $family->id, ...$state]));
}

/**
 * The values of a form for the rule.
 *
 * @param  array<string, mixed>  $values  what differs from an empty form
 * @return array{type: ?string, vehicle_id: ?int, home_id: ?int, employment_id: ?int}
 */
function replaceableValues(array $values): array
{
    return [...['type' => null, 'vehicle_id' => null, 'home_id' => null, 'employment_id' => null], ...$values];
}

it('offers the active and expired car policies of the same vehicle', function (): void {
    $admin = User::factory()->admin()->create();
    $family = $admin->family;
    $vehicle = CurrentFamily::using($family->id, fn (): Vehicle => Vehicle::factory()->create(['family_id' => $family->id]));
    $other = CurrentFamily::using($family->id, fn (): Vehicle => Vehicle::factory()->create(['family_id' => $family->id]));
    $a = replaceablePolicy($family, ['vehicle_id' => $vehicle->id, 'status' => InsurancePolicyStatus::Active]);
    $b = replaceablePolicy($family, ['vehicle_id' => $vehicle->id, 'status' => InsurancePolicyStatus::Expired]);
    replaceablePolicy($family, ['vehicle_id' => $vehicle->id, 'status' => InsurancePolicyStatus::Cancelled]);
    replaceablePolicy($family, ['vehicle_id' => $other->id]);
    replaceablePolicy($family, ['vehicle_id' => $vehicle->id, 'type' => InsurancePolicyType::Life]);

    test()->actingAs($admin);

    $ids = CurrentFamily::using($family->id, fn () => resolve(ReplaceablePolicies::class)->matching(replaceableValues(['type' => 'car', 'vehicle_id' => $vehicle->id]))->pluck('id')->all());

    expect($ids)->toEqualCanonicalizing([$a->id, $b->id]);
});

it('offers nothing for a type without a covered card', function (): void {
    $admin = User::factory()->admin()->create();
    replaceablePolicy($admin->family, ['type' => InsurancePolicyType::Life]);
    test()->actingAs($admin);

    $found = CurrentFamily::using($admin->family_id, fn () => resolve(ReplaceablePolicies::class)->matching(replaceableValues(['type' => 'life', 'vehicle_id' => 1])));

    expect($found)->toBeEmpty();
});

it('offers nothing when the covered card is empty', function (): void {
    $admin = User::factory()->admin()->create();
    replaceablePolicy($admin->family, ['vehicle_id' => null]);
    test()->actingAs($admin);

    $found = CurrentFamily::using($admin->family_id, fn () => resolve(ReplaceablePolicies::class)->matching(replaceableValues(['type' => 'car'])));

    expect($found)->toBeEmpty();
});

it('leaves out the policy being saved', function (): void {
    $admin = User::factory()->admin()->create();
    $family = $admin->family;
    $vehicle = CurrentFamily::using($family->id, fn (): Vehicle => Vehicle::factory()->create(['family_id' => $family->id]));
    $a = replaceablePolicy($family, ['vehicle_id' => $vehicle->id]);
    $b = replaceablePolicy($family, ['vehicle_id' => $vehicle->id]);
    test()->actingAs($admin);

    $ids = CurrentFamily::using($family->id, fn () => resolve(ReplaceablePolicies::class)->matching(replaceableValues(['type' => 'car', 'vehicle_id' => $vehicle->id]), except: $a->id)->pluck('id')->all());

    expect($ids)->toBe([$b->id]);
});

it('offers nothing to a member', function (): void {
    $member = User::factory()->create();
    $family = $member->family;
    $vehicle = CurrentFamily::using($family->id, fn (): Vehicle => Vehicle::factory()->create(['family_id' => $family->id]));
    replaceablePolicy($family, ['vehicle_id' => $vehicle->id]);
    test()->actingAs($member);

    $service = resolve(ReplaceablePolicies::class);

    expect(CurrentFamily::using($family->id, fn () => $service->candidates()))->toBeEmpty()
        ->and(CurrentFamily::using($family->id, fn () => $service->matching(replaceableValues(['type' => 'car', 'vehicle_id' => $vehicle->id]))))->toBeEmpty();
});

it('never offers a policy of another family', function (): void {
    $admin = User::factory()->admin()->create();
    $stranger = User::factory()->admin()->create();
    $vehicle = CurrentFamily::using($admin->family_id, fn (): Vehicle => Vehicle::factory()->create(['family_id' => $admin->family_id]));
    replaceablePolicy($stranger->family, ['vehicle_id' => $vehicle->id]);
    test()->actingAs($admin);

    $found = CurrentFamily::using($admin->family_id, fn () => resolve(ReplaceablePolicies::class)->matching(replaceableValues(['type' => 'car', 'vehicle_id' => $vehicle->id])));

    expect($found)->toBeEmpty();
});

it('orders the candidates by expiry', function (): void {
    $admin = User::factory()->admin()->create();
    $late = replaceablePolicy($admin->family, ['expiry_date' => '2027-06-01']);
    $early = replaceablePolicy($admin->family, ['expiry_date' => '2027-01-01']);
    test()->actingAs($admin);

    $ids = CurrentFamily::using($admin->family_id, fn () => resolve(ReplaceablePolicies::class)->candidates()->pluck('id')->all());

    expect($ids)->toBe([$early->id, $late->id]);
});

it('labels a policy with its number and insurer', function (): void {
    $admin = User::factory()->admin()->create();
    $family = $admin->family;
    $insurer = CurrentFamily::using($family->id, fn (): Organization => Organization::factory()->create(['family_id' => $family->id, 'name' => 'Allianz']));
    $policy = replaceablePolicy($family, ['title' => 'RC Auto', 'policy_number' => '40/123', 'organization_id' => $insurer->id, 'expiry_date' => '2027-03-05']);

    $data = CurrentFamily::using($family->id, fn (): ReplaceablePolicyData => ReplaceablePolicyData::fromPolicy($policy->refresh()));

    expect($data->label)->toBe('RC Auto · 40/123 · Allianz')
        ->and($data->expiry_date)->toBe('2027-03-05')
        ->and($data->type)->toBe('car');
});
