<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\FamilySetting;
use App\Models\User;
use App\Services\FamilyFeatures;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['services.mailgun.inbox_domain' => 'bollette.familymanager.test']);
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
});

/**
 * The address for the bills of the family of an account, or null.
 */
function featureSettingsAddress(User $user): ?string
{
    return insideFamilyOf($user, fn (): ?string => FamilySetting::query()->first()?->mail_inbox_recipient);
}

test('the page tells an admin the switches and that they can change them', function (): void {
    $this->get(route('settings.features.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/Features')
        ->where('settings.budget', true)
        ->where('settings.mail', true)
        ->where('settings.can_change', true));
});

test('a member sees the page with the switches locked', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);
    $this->actingAs($member);

    $this->get(route('settings.features.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('settings.can_change', false));
});

test('a member cannot change them', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);
    $this->actingAs($member);

    $this->put(route('settings.features.update'), ['budget' => false, 'mail' => false])->assertForbidden();

    expect($this->marco->family->fresh()?->budget_enabled)->toBeTrue();
});

test('an admin turns the budget and the mail off and on', function (): void {
    $this->put(route('settings.features.update'), ['budget' => false, 'mail' => false])
        ->assertRedirect(route('settings.features.edit'));

    $family = $this->marco->family->fresh();
    expect($family?->budget_enabled)->toBeFalse()
        ->and($family?->mail_enabled)->toBeFalse()
        ->and(resolve(FamilyFeatures::class)->on(Feature::Budget))->toBeFalse();

    $this->put(route('settings.features.update'), ['budget' => true, 'mail' => true]);

    expect($this->marco->family->fresh()?->budget_enabled)->toBeTrue()
        ->and(resolve(FamilyFeatures::class)->on(Feature::Mail))->toBeTrue();
});

test('both values are required booleans', function (): void {
    $this->put(route('settings.features.update'), ['budget' => 'maybe'])->assertSessionHasErrors(['budget', 'mail']);
});

test('turning the mail on gives the family its address, and the same one back after off and on', function (): void {
    // A family made before the domain was set has no address
    insideFamilyOf($this->marco, fn () => FamilySetting::query()->update(['mail_inbox_recipient' => null]));
    $this->put(route('settings.features.update'), ['budget' => true, 'mail' => false]);
    expect(featureSettingsAddress($this->marco))->toBeNull();

    $this->put(route('settings.features.update'), ['budget' => true, 'mail' => true]);
    $address = featureSettingsAddress($this->marco);

    expect($address)->toEndWith('@bollette.familymanager.test');

    $this->put(route('settings.features.update'), ['budget' => true, 'mail' => false]);
    $this->put(route('settings.features.update'), ['budget' => true, 'mail' => true]);

    expect(featureSettingsAddress($this->marco))->toBe($address);
});

test('turning the mail off keeps the address', function (): void {
    $this->put(route('settings.features.update'), ['budget' => true, 'mail' => true]);
    $address = featureSettingsAddress($this->marco);

    $this->put(route('settings.features.update'), ['budget' => true, 'mail' => false]);

    expect(featureSettingsAddress($this->marco))->toBe($address);
});

test('turning the mail on without a domain saves the switch but gives no address and the mail stays off', function (): void {
    config(['services.mailgun.inbox_domain' => '']);
    insideFamilyOf($this->marco, fn () => FamilySetting::query()->update(['mail_inbox_recipient' => null]));

    $this->put(route('settings.features.update'), ['budget' => true, 'mail' => true]);

    expect($this->marco->family->fresh()?->mail_enabled)->toBeTrue()
        ->and(featureSettingsAddress($this->marco))->toBeNull()
        ->and(resolve(FamilyFeatures::class)->on(Feature::Mail))->toBeFalse();
});
