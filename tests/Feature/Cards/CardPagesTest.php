<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Document;
use App\Models\Family;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    // The pages of the cards (Task 8) do not exist yet; the component names are what is checked.
    config(['inertia.testing.ensure_pages_exist' => false]);
    Storage::fake('local');
    $this->travelTo('2026-10-26 10:00:00');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);
    $this->panda = insideBianchi(fn (): Vehicle => Vehicle::query()->where('name', 'Fiat Panda')->sole());
    $this->booklet = insideBianchi(fn (): Document => Document::query()->where('title', 'Scontrino TV Samsung')->sole());
    $this->pandaUrl = ['type' => 'vehicle', 'id' => $this->panda->id];
});

test('it shows the areas, an area and a card to every account', function (User $user): void {
    $this->actingAs($user);

    $this->get(route('areas'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Areas')->has('areas', 9));
    $this->get(route('areas.show', 'home'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Area')->where('area.area', 'home'));
    $this->get(route('cards.show', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('cards/Card')
        ->where('card.card.label', 'Fiat Panda'));
})->with([
    'admin' => fn (): User => User::query()->where('email', 'marco.bianchi@example.com')->sole(),
    'member' => fn (): User => User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]),
]);

test('it lets only an admin create, edit and delete', function (): void {
    $this->actingAs($this->rosa);

    $this->get(route('cards.create', ['type' => 'vehicle']))->assertForbidden();
    $this->post(route('cards.store', 'vehicle'), ['name' => 'Vespa'])->assertForbidden();
    $this->get(route('cards.edit', $this->pandaUrl))->assertForbidden();
    $this->put(route('cards.update', $this->pandaUrl), ['name' => 'Panda'])->assertForbidden();
    $this->delete(route('cards.destroy', $this->pandaUrl))->assertForbidden();
});

test('it lets a member link and unlink a document', function (): void {
    $this->actingAs($this->rosa);
    $linked = fn (): bool => DB::table('documentables')->where([
        'document_id' => $this->booklet->id, 'documentable_type' => 'vehicle', 'documentable_id' => $this->panda->id,
    ])->exists();

    $this->post(route('cards.documents.store', $this->pandaUrl), ['document_id' => $this->booklet->id])->assertRedirect();
    expect($linked())->toBeTrue();

    $this->delete(route('cards.documents.destroy', [...$this->pandaUrl, 'document' => $this->booklet->id]))->assertRedirect();
    expect($linked())->toBeFalse();
});

test('it answers 404 for another family', function (): void {
    $this->actingAs(registerFamily('Rossi', 'mario@rossi.example'));

    $this->get(route('cards.show', $this->pandaUrl))->assertNotFound();
    $this->get(route('cards.edit', $this->pandaUrl))->assertNotFound();
    $this->put(route('cards.update', $this->pandaUrl), ['name' => 'Mia'])->assertNotFound();
    $this->delete(route('cards.destroy', $this->pandaUrl))->assertNotFound();
    $this->post(route('cards.documents.store', $this->pandaUrl), ['document_id' => $this->booklet->id])->assertNotFound();
    $this->delete(route('cards.documents.destroy', [...$this->pandaUrl, 'document' => $this->booklet->id]))->assertNotFound();
});

test('it answers 404 for an unknown type or area', function (): void {
    $this->actingAs($this->marco);

    $this->get('/cards/spaceship/1')->assertNotFound();
    $this->get('/cards/create?type=spaceship')->assertNotFound();
    $this->get('/areas/garden')->assertNotFound();
});

test('it creates a vehicle and opens it', function (): void {
    $this->actingAs($this->marco);

    $response = $this->post(route('cards.store', 'vehicle'), [
        'name' => 'Vespa', 'status' => 'active', 'is_owned' => true, 'inspection_due_date' => '2027-03-01',
    ]);

    $vespa = insideBianchi(fn (): Vehicle => Vehicle::query()->where('name', 'Vespa')->sole());
    $response->assertRedirect(route('cards.show', ['type' => 'vehicle', 'id' => $vespa->id]));
    expect($vespa->inspection_due_date?->toDateString())->toBe('2027-03-01');
});

test('it deletes a card and goes back to its area', function (): void {
    $this->actingAs($this->marco);

    $this->delete(route('cards.destroy', $this->pandaUrl))->assertRedirect(route('areas.show', 'vehicles'));
    expect(insideBianchi(fn (): bool => Vehicle::query()->whereKey($this->panda->id)->exists()))->toBeFalse();
});
