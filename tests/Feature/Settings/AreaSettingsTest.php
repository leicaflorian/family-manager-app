<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Enums\Role;
use App\Models\FamilySetting;
use App\Models\User;
use App\Services\FamilyAreas;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
});

/**
 * The values of all the areas, in the default order.
 *
 * @return list<string>
 */
function allAreaValues(): array
{
    return array_map(static fn (Area $area): string => $area->value, Area::cases());
}

/**
 * Signs in a member of Marco's family.
 */
function signInAreaMember(User $marco): void
{
    test()->actingAs(User::factory()->for($marco->family)->create(['role' => Role::Member]));
}

test('a member sees the areas in the order of the family with the cards they hold', function (): void {
    insideFamilyOf($this->marco, fn () => resolve(FamilyAreas::class)->save(
        [Area::Banks, ...array_values(array_filter(Area::cases(), static fn (Area $area): bool => $area !== Area::Banks))],
        [Area::School],
    ));
    signInAreaMember($this->marco);

    $this->get(route('settings.areas.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/Areas')
        ->where('settings.can_change', false)
        ->has('settings.areas', 9)
        ->where('settings.areas.0.area', 'banks')
        ->where('settings.areas.0.label', Area::Banks->label())
        ->where('settings.areas.0.hidden', false)
        ->has('settings.areas.0.count')
        ->where('settings.areas.5.area', 'school')
        ->where('settings.areas.5.hidden', true));
});

test('an admin saves the order and the hidden areas', function (): void {
    $order = array_reverse(allAreaValues());

    $this->put(route('settings.areas.update'), ['order' => $order, 'hidden' => ['home']])
        ->assertRedirect(route('settings.areas.edit'));

    insideFamilyOf($this->marco, function (): void {
        $areas = resolve(FamilyAreas::class);
        expect($areas->ordered()[0])->toBe(Area::Paperwork)
            ->and($areas->hidden())->toBe([Area::Home]);
    });
});

test('a member cannot save the areas', function (): void {
    signInAreaMember($this->marco);

    $this->put(route('settings.areas.update'), ['order' => allAreaValues(), 'hidden' => []])->assertForbidden();
});

test('the order must hold every area once and only real areas', function (array $order): void {
    $this->put(route('settings.areas.update'), ['order' => $order, 'hidden' => []])->assertSessionHasErrors();
})->with([
    'a repeated area' => fn () => [...array_slice(allAreaValues(), 0, 8), 'home'],
    'a missing area' => fn () => array_slice(allAreaValues(), 0, 8),
    'an unknown area' => fn () => [...array_slice(allAreaValues(), 0, 8), 'garden'],
]);

test('an unknown hidden area is refused', function (): void {
    $this->put(route('settings.areas.update'), ['order' => allAreaValues(), 'hidden' => ['garden']])
        ->assertSessionHasErrors('hidden.0');
});

test('every area can be hidden', function (): void {
    $this->put(route('settings.areas.update'), ['order' => allAreaValues(), 'hidden' => allAreaValues()])
        ->assertSessionHasNoErrors();

    insideFamilyOf($this->marco, fn () => expect(resolve(FamilyAreas::class)->visible())->toBe([]));
});

test('an admin resets the areas and a member cannot', function (): void {
    insideFamilyOf($this->marco, fn () => resolve(FamilyAreas::class)->save(array_reverse(Area::cases()), [Area::Home]));

    signInAreaMember($this->marco);
    $this->post(route('settings.areas.reset'))->assertForbidden();

    $this->actingAs($this->marco)->post(route('settings.areas.reset'))->assertRedirect(route('settings.areas.edit'));

    expect(insideFamilyOf($this->marco, fn () => FamilySetting::query()->first()?->areas))->toBeNull();
});

test('the settings index tells how many areas there are and how many are hidden', function (): void {
    insideFamilyOf($this->marco, fn () => resolve(FamilyAreas::class)->save(Area::cases(), [Area::Home, Area::Work]));

    $this->get(route('settings'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('areaCount', 9)
        ->where('hiddenAreas', 2));
});
