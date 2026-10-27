<?php

declare(strict_types=1);
use App\Models\Enrollment;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
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
        ->where('family.organizations', 14)
        ->where('canEdit', true));
});

test('the page of Giulia shows the health card at the top, her personal documents and her records', function (): void {
    $this->get(route('people.show', bianchi('Giulia')))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('family/Person')
        ->where('person.first_name', 'Giulia')
        ->where('person.birth_date', '2016-05-24')
        ->where('person.can_delete', true)
        ->where('expiring.0.title', 'Tessera sanitaria')
        ->where('expiring.1.title', 'Certificato medico sportivo')
        ->where('personal', fn ($documents): bool => collect($documents)->pluck('title')->sort()->values()->all() === ["Carta d'identità", 'Tessera sanitaria'])
        ->where('documents', fn ($documents): bool => collect($documents)->pluck('title')->sort()->values()->all() === ['Certificato medico sportivo', 'Iscrizione danza 2026/27'])
        ->where('links', fn ($groups): bool => collect($groups)->pluck('type')->all() === ['health_event', 'enrollment'])
        ->has('links.1.options', 1));
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

test('a person of another family answers 404', function (): void {
    $rossi = registerFamily('Rossi', 'anna@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): Person => Person::query()->sole());

    $this->get(route('people.show', $theirs))->assertNotFound();
    $this->get(route('organizations.show', 999_999))->assertNotFound();
});

test('Organizations lists them by name, with their types and their records', function (): void {
    $this->withHeader('Accept-Language', 'it')->get(route('organizations.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('family/Organizations')
        ->has('organizations', 14)
        ->where('organizations.0.name', 'BPER Banca')
        ->where('organizations.4.name', 'Edison')
        ->where('organizations.4.types', ['utility_provider'])
        ->where('organizations.4.type_labels', ['Fornitore utenze'])
        ->has('organizations.4.records', 1)
        ->has('types', 9)
        ->where('family.organizations', 14));
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
