<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The filters prop of the Agenda as an account sees it.
 *
 * @param  User  $user  the account
 * @param  string  $query  the query string, for example `?months=3`
 * @return array<string, mixed>
 */
function savedFiltersOf(User $user, string $query = ''): array
{
    return test()->actingAs($user)->get('/agenda'.$query)->assertOk()->viewData('page')['props']['agenda']['filters'];
}

test('the filters are saved in the account', function (): void {
    $marco = registerFamily();
    $laura = Person::factory()->create(['family_id' => $marco->family_id]);

    $this->actingAs($marco)->from('/agenda')
        ->put('/agenda/filters', ['months' => 6, 'person_id' => $laura->id, 'hidden_areas' => ['work', 'general', 'work']])
        ->assertRedirect('/agenda');

    expect($marco->fresh()->agenda_filters)->toBe(['months' => 6, 'person_id' => $laura->id, 'hidden_areas' => ['work', 'general']])
        ->and(savedFiltersOf($marco->fresh()))->toBe(['months' => 6, 'person_id' => $laura->id, 'hidden_areas' => ['work', 'general']]);
});

test('an account without saved filters gets the defaults', function (): void {
    expect(savedFiltersOf(registerFamily()))->toBe(['months' => 1, 'person_id' => null, 'hidden_areas' => []]);
});

test('they come back on another device', function (): void {
    $marco = registerFamily();

    $this->actingAs($marco)->put('/agenda/filters', ['months' => 3, 'person_id' => null, 'hidden_areas' => ['school']])->assertRedirect();

    $otherDevice = User::query()->findOrFail($marco->id);
    $agenda = $this->actingAs($otherDevice)->get('/agenda')->assertOk()->viewData('page')['props']['agenda'];

    expect($agenda['filters'])->toBe(['months' => 3, 'person_id' => null, 'hidden_areas' => ['school']])
        ->and($agenda['months'])->toBe(3);
});

test('a person of another family is refused', function (): void {
    $marco = registerFamily();
    $rossi = registerFamily('Rossi', 'mario@example.com');

    $this->actingAs($marco)->putJson('/agenda/filters', ['months' => 1, 'person_id' => Person::factory()->create(['family_id' => $rossi->family_id])->id, 'hidden_areas' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('person_id');

    expect($marco->fresh()->agenda_filters)->toBeNull();
});

test('an unknown area is refused', function (): void {
    $marco = registerFamily();

    $this->actingAs($marco)->putJson('/agenda/filters', ['months' => 1, 'person_id' => null, 'hidden_areas' => ['work', 'garden']])
        ->assertUnprocessable()->assertJsonValidationErrors('hidden_areas.1');
});

test('a period the Agenda does not offer is refused', function (): void {
    $this->actingAs(registerFamily())->putJson('/agenda/filters', ['months' => 2, 'person_id' => null, 'hidden_areas' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('months');
});

test('a member saves their own filters', function (): void {
    $marco = registerFamily();
    $member = User::factory()->create(['family_id' => $marco->family_id, 'role' => Role::Member]);

    $this->actingAs($member)->put('/agenda/filters', ['months' => 12, 'person_id' => null, 'hidden_areas' => ['banks']])->assertRedirect();

    expect($member->fresh()->agenda_filters['months'])->toBe(12)
        ->and($marco->fresh()->agenda_filters)->toBeNull();
});

test('the address period is used and saved, an invalid one is not saved', function (): void {
    $marco = registerFamily();

    expect(savedFiltersOf($marco, '?months=3')['months'])->toBe(3)
        ->and($marco->fresh()->agenda_filters['months'])->toBe(3)
        ->and(savedFiltersOf($marco->fresh())['months'])->toBe(3)
        ->and(savedFiltersOf($marco->fresh(), '?months=2')['months'])->toBe(1)
        ->and($marco->fresh()->agenda_filters['months'])->toBe(3);
});

test('a saved person who no longer exists is dropped', function (): void {
    $marco = registerFamily();
    $laura = Person::factory()->create(['family_id' => $marco->family_id]);
    $marco->forceFill(['agenda_filters' => ['months' => 1, 'person_id' => $laura->id, 'hidden_areas' => []]])->save();
    $laura->delete();

    expect(savedFiltersOf($marco->fresh())['person_id'])->toBeNull();
});
