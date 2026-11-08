<?php

declare(strict_types=1);

use App\Data\AgendaData;
use App\Enums\Area;
use App\Enums\Role;
use App\Models\Family;
use App\Models\User;
use App\Services\FamilyAreas;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    // Health second, Work hidden
    insideBianchi(fn () => resolve(FamilyAreas::class)->save(
        [Area::Home, Area::Health, Area::Vehicles, Area::Insurance, Area::Banks, Area::School, Area::Work, Area::Subscriptions, Area::Paperwork],
        [Area::Work],
    ));
});

/** The values of the area options the Agenda or Reminders send. */
function optionValues(array $options): array
{
    return array_column($options, 'value');
}

test('the sidebar areas follow the order of the family and skip the hidden ones', function (): void {
    $this->actingAs($this->marco)->get(route('documents.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('areas', 8)
        ->where('areas.0.area', 'home')
        ->where('areas.1.area', 'health')
        ->where('areas', fn ($areas): bool => ! in_array('work', array_column($areas->toArray(), 'area'), true)));
});

test('Areas sends every area in the order of the family, the hidden one marked and with its cards, and the card types without the hidden ones', function (): void {
    $this->actingAs($this->marco)->get(route('areas'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('Areas')
        ->has('areas', 9)
        ->where('areas.0.hidden', false)
        ->where('areas.1.area', 'health')
        ->where('areas.6.area', 'work')
        ->where('areas.6.hidden', true)
        ->where('areas.6.cards', fn ($cards): bool => in_array('Marco - Studio Rinaldi', array_column($cards->toArray(), 'label'), true))
        ->where('types', fn ($types): bool => ! in_array('employment', array_column($types->toArray(), 'type'), true)
            && in_array('vehicle', array_column($types->toArray(), 'type'), true)));
});

test('An area says whether the family hid it in its data too', function (): void {
    $this->actingAs($this->marco)->get(route('areas.show', 'work'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('area.hidden', true));
});

test('New card groups the types in the order of the family and leaves out the hidden area', function (): void {
    $this->actingAs($this->marco)->get(route('cards.create'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('types', function ($types): bool {
            $areas = array_values(array_unique(array_column($types->toArray(), 'area')));

            return ! in_array('work', $areas, true) && array_search('health', $areas, true) < array_search('vehicles', $areas, true);
        }));
});

test('New card still offers the types of a hidden area asked by the address', function (): void {
    $this->actingAs($this->marco)->get(route('cards.create', ['area' => 'work']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('types', fn ($types): bool => in_array('employment', array_column($types->toArray(), 'type'), true)));
});

test('a hidden area still opens and says it is hidden, and whether the person can show it again', function (User $user, bool $canShow): void {
    $this->actingAs($user)->get(route('areas.show', 'work'))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('Area')
        ->where('hidden', true)
        ->where('can_show', $canShow));
})->with([
    'admin' => fn (): array => [User::query()->where('email', 'marco.bianchi@example.com')->sole(), true],
    'member' => fn (): array => [User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]), false],
]);

test('a visible area is not marked as hidden', function (): void {
    $this->actingAs($this->marco)->get(route('areas.show', 'home'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('hidden', false));
});

test('the Agenda and Reminders list the visible areas, then the hidden ones, then People and documents', function (): void {
    $expected = ['home', 'health', 'vehicles', 'insurance', 'banks', 'school', 'subscriptions', 'paperwork', 'work', 'general'];

    $agenda = $this->actingAs($this->marco)->get('/agenda')->assertOk()->viewData('page')['props']['agenda'];
    $reminders = $this->actingAs($this->marco)->get('/reminders')->assertOk()->viewData('page')['props']['reminders'];

    expect(optionValues($agenda['areas']))->toBe($expected)
        ->and($agenda['family_hidden'])->toBe(['work'])
        ->and(optionValues($reminders['areas']))->toBe($expected)
        ->and($reminders['family_hidden'])->toBe(['work']);
});

test('the filters of the Agenda still accept a hidden area', function (): void {
    $this->actingAs($this->marco)->put('/agenda/filters', ['months' => 1, 'person_id' => null, 'hidden_areas' => ['work']])->assertSessionHasNoErrors();

    expect($this->marco->fresh()->agenda_filters['hidden_areas'])->toBe(['work']);
});

test('the Agenda options keep the label of People and documents last', function (): void {
    $last = insideBianchi(fn (): array => AgendaData::areas(resolve(FamilyAreas::class)));

    expect(end($last)->value)->toBe('general');
});
