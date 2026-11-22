<?php

declare(strict_types=1);
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Enrollment;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo('2026-10-27 10:00:00');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->actingAs($this->marco);
});

/**
 * A person of the Bianchis, by first name.
 */
function bianchi(string $firstName): Person
{
    return insideBianchi(fn (): Person => Person::query()->where('first_name', $firstName)->sole());
}

/**
 * An organization of the Bianchis, by name.
 */
function bianchiOrganization(string $name): Organization
{
    return insideBianchi(fn (): Organization => Organization::query()->where('name', $name)->sole());
}

test('People lists the family with their account and how many documents and records they have', function (): void {
    $this->get(route('people.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('family/People')
        ->has('people', 3)
        ->where('people.0.first_name', 'Marco')
        ->where('people.0.is_me', true)
        ->where('people.0.account_label', 'Administrator')
        ->where('people.2.first_name', 'Giulia')
        ->where('people.2.account_label', null)
        ->where('people.2.age', 10)
        ->where('people.2.documents_count', 4)
        ->where('people.2.records_count', 2)
        ->where('family.people', 3)
        ->where('family.organizations', 22)
        ->where('canEdit', true));
});

test('the page of Giulia shows the health card at the top, her personal documents and her records', function (): void {
    $this->get(route('people.show', bianchi('Giulia')))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('family/Person')
        ->where('person.first_name', 'Giulia')
        ->where('person.birth_date', '2016-05-24')
        ->where('person.can_delete', true)
        ->missing('expiring')
        ->where('personalTotal', 2)
        ->where('personalNext', null)
        ->where('documentsTotal', 2)
        ->where('documentsNext', null)
        ->where('personal', fn ($documents): bool => collect($documents)->pluck('title')->sort()->values()->all() === ["Carta d'identità", 'Tessera sanitaria'])
        ->where('documents', fn ($documents): bool => collect($documents)->pluck('title')->sort()->values()->all() === ['Certificato medico sportivo', 'Iscrizione danza 2026/27'])
        ->where('links', fn ($groups): bool => collect($groups)->pluck('type')->all() === ['health_event', 'enrollment'])
        ->has('links.1.options', 1));
});

test('the page of a person lists their open reminders, the late one first', function (): void {
    $this->get(route('people.show', bianchi('Giulia')))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('reminders', 3)
        ->where('remindersCount', 3)
        ->where('reminders.0.title', 'Renew the document')
        ->where('reminders.0.due_date', '2026-10-20')
        ->where('reminders.0.subject.label', 'Tessera sanitaria')
        ->where('reminders.0.status', 'open'));

    $this->get(route('people.show', bianchi('Laura')))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('reminders', fn ($reminders): bool => collect($reminders)->every(fn (array $reminder): bool => $reminder['status'] === 'open' && $reminder['person']['name'] === 'Laura'))
        ->where('remindersCount', insideBianchi(fn (): int => Reminder::query()->open()->where('person_id', bianchi('Laura')->id)->count())));
});

test('a person with an account is not deleted', function (): void {
    $laura = bianchi('Laura');

    $this->get(route('people.show', $laura))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('person.can_delete', false));
    $this->delete(route('people.destroy', $laura))->assertSessionHasErrors('person');

    expect($laura->fresh())->not->toBeNull();
});

test('deleting a person without an account removes their records and keeps their documents, without the person', function (): void {
    $giulia = bianchi('Giulia');
    $enrollment = insideBianchi(fn (): Enrollment => Enrollment::query()->where('person_id', $giulia->id)->sole());

    $this->delete(route('people.destroy', $giulia))->assertRedirect(route('people.index'));

    expect($giulia->fresh())->toBeNull()
        ->and(DB::table('enrollments')->where('id', $enrollment->id)->exists())->toBeFalse()
        ->and(DB::table('documentables')->where('documentable_type', 'enrollment')->where('documentable_id', $enrollment->id)->exists())->toBeFalse()
        ->and(DB::table('documents')->where('title', 'Iscrizione danza 2026/27')->value('person_id'))->toBeNull();
});

test('an admin adds a person, with the initials taken from the name', function (): void {
    $this->post(route('people.store'), ['first_name' => 'Rosa', 'last_name' => 'Bianchi', 'role' => 'other', 'birth_date' => '1950-03-02'])
        ->assertSessionHasNoErrors();

    $rosa = bianchi('Rosa');
    expect($rosa->initials)->toBe('RB')->and($rosa->user_id)->toBeNull();
    $this->get(route('people.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('people', 4));
});

test('an admin edits a person', function (): void {
    $giulia = bianchi('Giulia');

    $this->put(route('people.update', $giulia), ['first_name' => 'Giulia', 'last_name' => 'Bianchi', 'initials' => 'GiB', 'role' => 'child', 'birth_date' => null, 'tax_code' => 'BNCGLI16E64A944K'])
        ->assertSessionHasNoErrors()->assertRedirect(route('people.show', $giulia));

    expect($giulia->fresh())->initials->toBe('GiB')->tax_code->toBe('BNCGLI16E64A944K')->birth_date->toBeNull();
});

test('a person needs first name, last name and a valid role', function (): void {
    $this->post(route('people.store'), ['first_name' => '', 'last_name' => '', 'role' => 'uncle'])
        ->assertSessionHasErrors(['first_name', 'last_name', 'role']);
});

test('a member gets 403 on new, edit and delete of people and organizations', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['email_verified_at' => now()]);
    $giulia = bianchi('Giulia');
    $edison = bianchiOrganization('Edison');
    $this->actingAs($member);

    $this->get(route('people.create'))->assertForbidden();
    $this->post(route('people.store'), ['first_name' => 'Rosa', 'last_name' => 'Bianchi', 'role' => 'other'])->assertForbidden();
    $this->get(route('people.edit', $giulia))->assertForbidden();
    $this->put(route('people.update', $giulia), ['first_name' => 'G', 'last_name' => 'B', 'role' => 'child'])->assertForbidden();
    $this->delete(route('people.destroy', $giulia))->assertForbidden();
    $this->get(route('organizations.create'))->assertForbidden();
    $this->post(route('organizations.store'), ['name' => 'Coop'])->assertForbidden();
    $this->get(route('organizations.edit', $edison))->assertForbidden();
    $this->put(route('organizations.update', $edison), ['name' => 'Edison spa'])->assertForbidden();
    $this->delete(route('organizations.destroy', $edison))->assertForbidden();

    expect($giulia->fresh())->first_name->toBe('Giulia')->and($edison->fresh())->name->toBe('Edison');
    $this->get(route('people.show', $giulia))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('person.can_edit', false));
});

/**
 * Gives Giulia some documents of her own: personal ones, or linked to the Panda.
 */
function peopleGiveGiuliaDocuments(int $count, bool $linked): void
{
    insideBianchi(function () use ($count, $linked): void {
        $giulia = Person::query()->where('first_name', 'Giulia')->sole();
        $panda = Vehicle::query()->where('name', 'Fiat Panda')->sole();
        Document::factory()->count($count)->create(['family_id' => $giulia->family_id, 'person_id' => $giulia->id])
            ->each(fn (Document $document): bool => ! $linked || DB::table('documentables')->insert(['document_id' => $document->id, 'documentable_type' => 'vehicle', 'documentable_id' => $panda->id]));
    });
}

test('the personal documents of a person come 5 at a time, the next page on request', function (): void {
    peopleGiveGiuliaDocuments(5, linked: false);
    $giulia = bianchi('Giulia');
    $first = [];

    $this->get(route('people.show', $giulia))->assertInertia(function (AssertableInertia $page) use (&$first): AssertableInertia {
        $first = collect($page->toArray()['props']['personal'])->pluck('id')->all();

        return $page->has('personal', 5)->where('personalNext', 2)->where('personalTotal', 7);
    });

    $version = resolve(HandleInertiaRequests::class)->version(request());
    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => 'family/Person',
        'X-Inertia-Partial-Data' => 'personal,personalNext,personalTotal',
    ])->get(route('people.show', ['person' => $giulia, 'personal_page' => 2]));

    $response->assertOk()
        ->assertJsonCount(2, 'props.personal')
        ->assertJsonPath('props.personalNext', null)
        ->assertJsonMissingPath('props.documents')
        // A page asked twice, or shifted by a document added in between, never shows a row twice
        ->assertJsonPath('matchPropsOn', ['personal.id']);
    expect(array_intersect(array_column($response->json('props.personal'), 'id'), $first))->toBe([]);
});

test('a page of documents asked by the page reads only the documents', function (): void {
    peopleGiveGiuliaDocuments(5, linked: false);
    $giulia = bianchi('Giulia');
    $version = resolve(HandleInertiaRequests::class)->version(request());

    DB::enableQueryLog();
    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => 'family/Person',
        'X-Inertia-Partial-Data' => 'personal,personalNext,personalTotal',
    ])->get(route('people.show', ['person' => $giulia, 'personal_page' => 2]))->assertOk();

    $tables = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query): bool => preg_match('/from [`"]?(reminders|vehicles|enrollments|health_events)[`"]?/i', $query) === 1);
    expect($tables)->toBeEmpty();
});

test('a page number in the address of a full visit starts from the first page', function (): void {
    peopleGiveGiuliaDocuments(5, linked: false);

    $this->get(route('people.show', ['person' => bianchi('Giulia'), 'personal_page' => 2, 'documents_page' => 9]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('personal', 5)
        ->where('personalNext', 2)
        ->has('documents', 2)
        ->where('pageSizes', ['personal' => 5, 'documents' => 10]));
});

test('the other documents of a person come 10 at a time', function (): void {
    peopleGiveGiuliaDocuments(10, linked: true);

    $this->get(route('people.show', bianchi('Giulia')))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('documents', 10)
        ->where('documentsNext', 2)
        ->where('documentsTotal', 12));
});

test('a person of another family answers 404 also with a page', function (): void {
    $rossi = registerFamily('Rossi', 'anna@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): Person => Person::query()->sole());

    $this->get(route('people.show', ['person' => $theirs, 'personal_page' => 2]))->assertNotFound();
});

test('a person of another family answers 404', function (): void {
    $rossi = registerFamily('Rossi', 'anna@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): Person => Person::query()->sole());

    $this->get(route('people.show', $theirs))->assertNotFound();
    $this->get(route('organizations.show', 999_999))->assertNotFound();
});

test('Organizations lists them by name, with their types and their records', function (): void {
    $this->withHeader('Accept-Language', 'it')->get(route('organizations.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('family/Organizations')
        ->has('organizations', 22)
        ->where('organizations.0.name', 'BPER Banca')
        ->where('organizations.6.name', 'Edison')
        ->where('organizations.6.types', ['utility_provider'])
        ->where('organizations.6.type_labels', ['Fornitore utenze'])
        ->has('organizations.6.records', 1)
        ->has('types', 9)
        ->where('family.organizations', 22));
});

test('the page of an organization shows its contacts, its records and its documents', function (): void {
    $edison = bianchiOrganization('Edison');
    $edison->update(['phone' => '800 123 456', 'website' => 'edison.it']);

    $this->get(route('organizations.show', $edison))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('family/Organization')
        ->where('organization.name', 'Edison')
        ->where('organization.phone', '800 123 456')
        ->where('organization.website', 'edison.it')
        ->where('organization.can_delete', true)
        ->where('links.0.type', 'utility')
        ->has('documents', 3)
        ->where('documents.0.title', 'Bolletta luce di ottobre'));
});

test('deleting an organization leaves its documents, without the organization', function (): void {
    $edison = bianchiOrganization('Edison');

    $this->delete(route('organizations.destroy', $edison))->assertRedirect(route('organizations.index'));

    expect($edison->fresh())->toBeNull()
        ->and(DB::table('documents')->where('title', 'Bolletta luce di ottobre')->exists())->toBeTrue()
        ->and(DB::table('documents')->where('title', 'Bolletta luce di ottobre')->value('organization_id'))->toBeNull();
});

test('an admin adds an organization with its types, and an unknown type is refused', function (): void {
    $this->post(route('organizations.store'), ['name' => 'Coop Alleanza', 'types' => ['shop'], 'email' => 'info@coop.it'])
        ->assertSessionHasNoErrors();

    $coop = bianchiOrganization('Coop Alleanza');
    expect($coop->types?->map->value->all())->toBe(['shop']);

    $this->post(route('organizations.store'), ['name' => 'Altro', 'types' => ['spaceship'], 'email' => 'not an email'])
        ->assertSessionHasErrors(['types.0', 'email']);
});

test('an admin edits an organization', function (): void {
    $edison = bianchiOrganization('Edison');

    $this->put(route('organizations.update', $edison), ['name' => 'Edison Energia', 'types' => ['utility_provider'], 'phone' => '800 031 141'])
        ->assertSessionHasNoErrors()->assertRedirect(route('organizations.show', $edison));

    expect($edison->fresh())->name->toBe('Edison Energia')->phone->toBe('800 031 141');
});

/**
 * A person of the Bianchis with three personal documents, A, B and C, all in the same family.
 *
 * @return array{person: Person, a: Document, b: Document, c: Document}
 */
function peoplePinnedSetup(): array
{
    return insideBianchi(function (): array {
        $family = Person::query()->where('first_name', 'Giulia')->sole()->family_id;
        $person = Person::factory()->create(['family_id' => $family]);
        $make = fn (string $title, ?int $category = null): Document => Document::factory()->create(['family_id' => $family, 'person_id' => $person->id, 'title' => $title, 'category_id' => $category]);
        $withCard = DocumentCategory::query()->whereNotNull('card_type')->orderBy('id')->firstOrFail();
        $without = DocumentCategory::query()->whereNull('card_type')->whereNull('parent_id')->orderBy('id')->firstOrFail();

        return ['person' => $person, 'a' => $make('A', $withCard->id), 'b' => $make('B', $without->id), 'c' => $make('C')];
    });
}

test('it sends the pinned personal documents apart, in the order they were pinned', function (): void {
    ['person' => $person, 'a' => $a, 'b' => $b, 'c' => $c] = peoplePinnedSetup();
    insideBianchi(function () use ($a, $b): void {
        $b->forceFill(['pinned_at' => now()->subMinutes(5), 'pinned_by' => $this->marco->id])->save();
        $a->forceFill(['pinned_at' => now()->subMinute(), 'pinned_by' => $this->marco->id])->save();
    });

    $this->get(route('people.show', $person))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('pinned', 2)
        ->where('pinned.0.document.title', 'B')
        ->where('pinned.1.document.title', 'A')
        ->where('pinned.0.pinned_by', $this->marco->name)
        ->where('pinned.0.area', null)
        ->where('pinned.1.area', fn ($area): bool => is_string($area))
        ->where('personal', fn ($documents): bool => collect($documents)->pluck('id')->all() === [$c->id])
        ->where('personalTotal', 1)
        ->where('canPin', true));
});

test('it leaves a pinned document linked to a record out of both lists', function (): void {
    ['person' => $person, 'a' => $a] = peoplePinnedSetup();
    insideBianchi(function () use ($a): void {
        $a->forceFill(['pinned_at' => now(), 'pinned_by' => $this->marco->id])->save();
        DB::table('documentables')->insert(['document_id' => $a->id, 'documentable_type' => 'vehicle', 'documentable_id' => Vehicle::query()->firstOrFail()->id]);
    });

    $this->get(route('people.show', $person))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('pinned', 0)
        ->where('personal', fn ($documents): bool => ! collect($documents)->pluck('id')->contains($a->id))
        ->where('personalTotal', 2));
});

test('it shows it pinned again once unlinked', function (): void {
    ['person' => $person, 'a' => $a] = peoplePinnedSetup();
    insideBianchi(function () use ($a): void {
        $a->forceFill(['pinned_at' => now(), 'pinned_by' => $this->marco->id])->save();
        DB::table('documentables')->insert(['document_id' => $a->id, 'documentable_type' => 'vehicle', 'documentable_id' => Vehicle::query()->firstOrFail()->id]);
        DB::table('documentables')->where('document_id', $a->id)->delete();
    });

    $this->get(route('people.show', $person))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('pinned', 1)
        ->where('personalTotal', 2));
});

test('the page of a person tells whether the account may pin documents', function (): void {
    ['person' => $person] = peoplePinnedSetup();

    $this->get(route('people.show', $person))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('canPin', true));
});

/**
 * Gives Edison enough documents for 12 in all (it has 3).
 */
function peopleGiveEdisonDocuments(): Organization
{
    $edison = bianchiOrganization('Edison');
    insideBianchi(fn () => Document::factory()->count(9)->create(['family_id' => $edison->family_id, 'organization_id' => $edison->id]));

    return $edison;
}

test('it sends the documents of an organization ten at a time', function (): void {
    $edison = peopleGiveEdisonDocuments();

    $this->get(route('organizations.show', $edison))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('documents', 10)
        ->where('documentsNext', 2)
        ->where('documentsTotal', 12)
        ->where('pageSizes', ['documents' => 10]));
});

test('it adds the second page on the partial reload', function (): void {
    $edison = peopleGiveEdisonDocuments();
    $version = resolve(HandleInertiaRequests::class)->version(request());

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => 'family/Organization',
        'X-Inertia-Partial-Data' => 'documents,documentsNext,documentsTotal',
    ])->get(route('organizations.show', ['organization' => $edison, 'documents_page' => 2]))->assertOk()
        ->assertJsonCount(2, 'props.documents')
        ->assertJsonPath('props.documentsNext', null)
        ->assertJsonPath('matchPropsOn', ['documents.id']);
});

test('it starts from the first page on a full visit even with documents_page in the address', function (): void {
    $edison = peopleGiveEdisonDocuments();

    $this->get(route('organizations.show', ['organization' => $edison, 'documents_page' => 2]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('documents', 10)
        ->where('documentsNext', 2));
});
