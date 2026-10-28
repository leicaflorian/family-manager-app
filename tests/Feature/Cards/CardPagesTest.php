<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Document;
use App\Models\Family;
use App\Models\File;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
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

test('the card types carry the name of their area in the current language', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('areas.show', 'vehicles'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('Area')
        ->where('types.0.area', 'vehicles')
        ->where('types.0.area_label', 'Vehicles'));
});

test('it lets only an admin create, edit and delete', function (): void {
    $this->actingAs($this->rosa);

    $this->get(route('cards.create', ['card' => 'vehicle']))->assertForbidden();
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

test('the documents of a card carry the address of their file, and only an admin may delete them', function (User $user, bool $canDelete): void {
    $this->actingAs($user);
    $this->post(route('cards.documents.store', $this->pandaUrl), ['document_id' => $this->booklet->id]);

    $this->get(route('cards.show', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('card.documents', fn ($documents): bool => collect($documents)
            ->contains(fn (array $document): bool => $document['id'] === $this->booklet->id
                && $document['file_url'] === route('files.show', $this->booklet->file_id)))
        ->where('card.can_delete_documents', $canDelete));
})->with([
    'admin' => fn (): array => [User::query()->where('email', 'marco.bianchi@example.com')->sole(), true],
    'member' => fn (): array => [User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]), false],
]);

test('it answers 404 for another family', function (): void {
    $this->actingAs(registerFamily('Rossi', 'mario@rossi.example'));

    $this->get(route('cards.show', $this->pandaUrl))->assertNotFound();
    $this->get(route('cards.edit', $this->pandaUrl))->assertNotFound();
    $this->put(route('cards.update', $this->pandaUrl), ['name' => 'Mia'])->assertNotFound();
    $this->delete(route('cards.destroy', $this->pandaUrl))->assertNotFound();
    $this->post(route('cards.documents.store', $this->pandaUrl), ['document_id' => $this->booklet->id])->assertNotFound();
    $this->delete(route('cards.documents.destroy', [...$this->pandaUrl, 'document' => $this->booklet->id]))->assertNotFound();
});

test('it answers 404 for a document of another family', function (): void {
    $theirs = registerFamily('Rossi', 'mario@rossi.example');
    $document = Document::factory()->for($theirs->family)->create();
    $this->actingAs($this->marco);

    $this->post(route('cards.documents.store', $this->pandaUrl), ['document_id' => $document->id])->assertNotFound();
    $this->delete(route('cards.documents.destroy', [...$this->pandaUrl, 'document' => $document->id]))->assertNotFound();
    expect(DB::table('documentables')->where('document_id', $document->id)->exists())->toBeFalse();
});

test('it answers 404, not 422, for an invalid change to a card of another family', function (): void {
    $this->actingAs(registerFamily('Rossi', 'mario@rossi.example'));

    $this->put(route('cards.update', $this->pandaUrl), ['name' => ''])->assertNotFound();
    $this->post(route('cards.documents.store', $this->pandaUrl), [])->assertNotFound();
});

test('it answers 404 for an unknown type or area', function (): void {
    $this->actingAs($this->marco);

    $this->get('/cards/spaceship/1')->assertNotFound();
    $this->get('/cards/create?card=spaceship')->assertNotFound();
    $this->get('/areas/garden')->assertNotFound();
});

test('it opens the form of a new card with the values of the address, but not the type of the card', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('cards.create', ['card' => 'maintenance_record', 'target' => 'vehicle', 'vehicle_id' => $this->panda->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('cards/EditCard')
            ->where('type.type', 'maintenance_record')
            ->where('values.target', 'vehicle')
            ->where('values.vehicle_id', (string) $this->panda->id)
            ->missing('values.type'));
});

test('it offers an admin the cards that can be added linked to this one', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('cards.show', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('card.addable', 2)
        ->where('card.addable.0.label', 'Maintenance job')
        ->where('card.addable.0.href', route('cards.create', ['card' => 'maintenance_record', 'target' => 'vehicle', 'vehicle_id' => $this->panda->id], false))
        ->where('card.addable.1.label', 'Policy')
        ->where('card.addable.1.href', route('cards.create', ['card' => 'insurance_policy', 'type' => 'car', 'vehicle_id' => $this->panda->id], false)));
});

test('it offers a member no card to add', function (): void {
    $this->actingAs($this->rosa);

    $this->get(route('cards.show', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('card.addable', []));
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
    $reminder = insideBianchi(fn (): Reminder => Reminder::factory()->create());
    DB::table('documentables')->insert(['document_id' => $this->booklet->id, 'documentable_type' => 'vehicle', 'documentable_id' => $this->panda->id]);
    DB::table('remindables')->insert(['reminder_id' => $reminder->id, 'remindable_type' => 'vehicle', 'remindable_id' => $this->panda->id]);

    $this->delete(route('cards.destroy', $this->pandaUrl))->assertRedirect(route('areas.show', 'vehicles'));

    expect(insideBianchi(fn (): bool => Vehicle::query()->whereKey($this->panda->id)->exists()))->toBeFalse();
    foreach (['documentables', 'remindables'] as $table) {
        $link = rtrim($table, 's');
        expect(DB::table($table)->where([$link.'_type' => 'vehicle', $link.'_id' => $this->panda->id])->exists())->toBeFalse();
    }
    $this->get(route('documents.show', $this->booklet->id))->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('document.links', fn ($links): bool => collect($links)->doesntContain(fn (array $link): bool => $link['type'] === 'vehicle' && $link['id'] === $this->panda->id)));
});

test('it answers 404 for an id that is not a number', function (): void {
    $this->actingAs($this->marco);

    $this->get('/cards/vehicle/'.$this->panda->id.'abc')->assertNotFound();
    $this->get('/cards/vehicle/'.$this->panda->id.'abc/edit')->assertNotFound();
});

test('it saves an untouched switch as off, whatever the card type', function (string $type, array $input, string $table, string $column): void {
    $this->actingAs($this->marco);

    $this->post(route('cards.store', $type), [...$input, $column => null])->assertRedirect();

    expect(insideBianchi(fn (): int => DB::table($table)->where($column, false)->where('name', $input['name'])->count()))->toBe(1);
})->with([
    'home' => ['home', ['name' => 'Casa al mare'], 'homes', 'is_owned'],
    'vehicle' => ['vehicle', ['name' => 'Vespa'], 'vehicles', 'is_owned'],
    'account' => ['financial_account', ['name' => 'Conto nuovo', 'type' => 'deposit'], 'financial_accounts', 'is_default'],
    'subscription' => ['subscription', ['name' => 'Spotify', 'type' => 'streaming'], 'subscriptions', 'is_auto_renew'],
]);

test('it creates a home without the switch in the form', function (): void {
    $this->actingAs($this->marco);

    $this->post(route('cards.store', 'home'), ['name' => 'Casa al mare'])->assertRedirect();

    expect(insideBianchi(fn (): bool => DB::table('homes')->where('name', 'Casa al mare')->sole()->is_owned === 0))->toBeTrue();
});

test('it creates a vehicle with its documents and opens the upload linked to it', function (): void {
    $this->actingAs($this->marco);

    // As the page sends it with files: form data, so the switch is "1" and an empty date "".
    $response = $this->post(route('cards.store', 'vehicle'), [
        'name' => 'Vespa', 'status' => 'active', 'is_owned' => '1', 'inspection_due_date' => '',
        'files' => [UploadedFile::fake()->create('Libretto.pdf', 120, 'application/pdf'), UploadedFile::fake()->create('Bollo.pdf', 120, 'application/pdf')],
    ]);

    $vespa = insideBianchi(fn (): Vehicle => Vehicle::query()->where('name', 'Vespa')->sole());
    $response->assertSessionHasNoErrors()->assertRedirect(route('upload', ['link' => 'vehicle:'.$vespa->id]));
    expect($vespa->is_owned)->toBeTrue()
        ->and(insideBianchi(fn (): array => File::query()->toSort()->oldest('id')->pluck('original_name')->all()))->toBe(['Libretto.pdf', 'Bollo.pdf']);
});

test('it creates nothing when a document is not a file it takes', function (): void {
    $this->actingAs($this->marco);

    $this->post(route('cards.store', 'vehicle'), [
        'name' => 'Vespa', 'files' => [UploadedFile::fake()->create('Virus.exe', 120, 'application/x-msdownload')],
    ])->assertSessionHasErrors(['files.0' => 'Only PDF, JPG, PNG and HEIC files can be uploaded.']);

    expect(insideBianchi(fn (): bool => Vehicle::query()->where('name', 'Vespa')->exists()))->toBeFalse();
});

test('it saves a change with documents and opens the upload linked to the card', function (): void {
    $this->actingAs($this->marco);

    // As the page sends it with files: a POST with the real method in _method.
    $this->post(route('cards.update', $this->pandaUrl), [
        '_method' => 'put', 'name' => 'Fiat Panda bianca', 'files' => [UploadedFile::fake()->create('Libretto.pdf', 120, 'application/pdf')],
    ])->assertSessionHasNoErrors()->assertRedirect(route('upload', ['link' => 'vehicle:'.$this->panda->id]));

    expect(insideBianchi(fn (): array => File::query()->toSort()->pluck('original_name')->all()))->toBe(['Libretto.pdf'])
        ->and(insideBianchi(fn (): string => Vehicle::query()->sole()->name))->toBe('Fiat Panda bianca');
});

test('it changes nothing when a document of a change is not a file it takes', function (): void {
    $this->actingAs($this->marco);

    $this->post(route('cards.update', $this->pandaUrl), [
        '_method' => 'put', 'name' => 'Fiat Panda bianca', 'files' => [UploadedFile::fake()->create('Virus.exe', 120, 'application/x-msdownload')],
    ])->assertSessionHasErrors(['files.0' => 'Only PDF, JPG, PNG and HEIC files can be uploaded.']);

    expect(insideBianchi(fn (): string => Vehicle::query()->sole()->name))->toBe('Fiat Panda')
        ->and(insideBianchi(fn (): bool => File::query()->toSort()->exists()))->toBeFalse();
});

test('it unlinks a document and goes back to the page it came from, the card when there is none', function (): void {
    $this->actingAs($this->marco);
    $link = fn () => $this->post(route('cards.documents.store', $this->pandaUrl), ['document_id' => $this->booklet->id]);
    $unlink = route('cards.documents.destroy', [...$this->pandaUrl, 'document' => $this->booklet->id]);

    $link();
    $this->from(route('cards.edit', $this->pandaUrl))->delete($unlink)->assertRedirect(route('cards.edit', $this->pandaUrl));

    $link();
    $this->flushSession()->withoutHeader('referer')->delete($unlink)->assertRedirect(route('cards.show', $this->pandaUrl));
});

test('the form of a card says whether its documents can be deleted', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('cards.edit', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('canDeleteDocuments', true));
});

test('it opens the form of a card with the documents linked to it', function (): void {
    $this->actingAs($this->marco);

    $this->get(route('cards.edit', $this->pandaUrl))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('cards/EditCard')
        ->has('documents', 1)
        ->where('documents.0.title', 'Polizza RC auto 2026'));
});
