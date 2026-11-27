<?php

declare(strict_types=1);

use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Models\Utility;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->actingAs($this->marco);
});

test('an admin adds an organization from the card form and gets its id and name back', function (): void {
    $this->from('/cards/new')->post(route('organizations.quick'), ['name' => 'Autofficina Rossi', 'types' => ['utility_provider']])
        ->assertRedirect('/cards/new')
        ->assertSessionHasNoErrors();

    $organization = insideBianchi(fn (): Organization => Organization::query()->where('name', 'Autofficina Rossi')->sole());
    expect($organization->family_id)->toBe($this->marco->family_id)
        ->and($organization->types)->toHaveCount(1)
        ->and(session('inertia.flash_data.organization'))->toBe(['id' => $organization->id, 'name' => 'Autofficina Rossi']);
});

test('a member cannot add an organization from the card form', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member, 'email_verified_at' => now()]);
    $this->actingAs($member);

    $this->post(route('organizations.quick'), ['name' => 'Autofficina Rossi'])->assertForbidden();

    expect(Organization::query()->withoutGlobalScopes()->where('name', 'Autofficina Rossi')->exists())->toBeFalse();
});

test('a missing name or a wrong type comes back in the organization error bag', function (): void {
    $this->post(route('organizations.quick'), ['name' => '', 'types' => ['nonsense']])
        ->assertSessionHasErrorsIn('organization', ['name', 'types.0']);

    expect(session('errors')->getBag('default')->isEmpty())->toBeTrue();
});

test('the card form says whether the person can add an organization', function (): void {
    $this->get(route('cards.create', ['card' => 'vehicle']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('cards/EditCard')->where('canCreateOrganization', true));
});

test('the card form, new and edit, gives the types of organization for the quick panel', function (): void {
    $types = array_column(OrganizationType::cases(), 'value');
    $utility = insideBianchi(fn (): Utility => Utility::query()->firstOrFail());

    $this->get(route('cards.create', ['card' => 'utility']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('organizationTypes', fn (Collection $options): bool => $options->pluck('value')->all() === $types));
    $this->get(route('cards.edit', ['type' => 'utility', 'id' => $utility->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('canCreateOrganization', true)
            ->where('organizationTypes', fn (Collection $options): bool => $options->pluck('value')->all() === $types));
});
