<?php

declare(strict_types=1);

use App\Models\File;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Http\UploadedFile;
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
 * The fields of Giulia as the form sends them.
 *
 * @param  array<string, mixed>  $more  the fields to add, like the photo
 * @return array<string, mixed>
 */
function giuliaFields(array $more = []): array
{
    return ['first_name' => 'Giulia', 'last_name' => 'Bianchi', 'role' => 'child', ...$more];
}

test('an admin adds a person with a photo, shown on their page and in People', function (): void {
    $this->post(route('people.store'), [
        'first_name' => 'Rosa', 'last_name' => 'Bianchi', 'role' => 'other',
        'photo' => UploadedFile::fake()->image('rosa.jpg'),
    ])->assertSessionHasNoErrors();

    $rosa = insideBianchi(fn (): Person => Person::query()->where('first_name', 'Rosa')->sole());
    $photo = insideBianchi(fn (): ?File => $rosa->photo);
    expect($photo?->original_name)->toBe('rosa.jpg');
    Storage::disk('local')->assertExists((string) $photo?->path);

    $this->get(route('people.show', $rosa))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('person.photo', route('files.show', $photo)));
    $this->get(route('people.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('people.3.photo', route('files.show', $photo))
        ->where('people.0.photo', null));
});

test('a new photo takes the place of the previous one, which is deleted', function (): void {
    $giulia = insideBianchi(fn (): Person => Person::query()->where('first_name', 'Giulia')->sole());

    $this->put(route('people.update', $giulia), giuliaFields(['photo' => UploadedFile::fake()->image('first.jpg')]))->assertSessionHasNoErrors();
    $first = insideBianchi(fn (): ?File => $giulia->fresh()?->photo);

    $this->put(route('people.update', $giulia), giuliaFields(['photo' => UploadedFile::fake()->image('second.png')]))->assertSessionHasNoErrors();

    expect(insideBianchi(fn (): ?string => $giulia->fresh()?->photo?->original_name))->toBe('second.png')
        ->and(insideBianchi(fn (): ?File => File::query()->find($first?->id)))->toBeNull();
    Storage::disk('local')->assertMissing((string) $first?->path);
});

test('saving without a photo keeps the one there is', function (): void {
    $giulia = insideBianchi(fn (): Person => Person::query()->where('first_name', 'Giulia')->sole());
    $this->put(route('people.update', $giulia), giuliaFields(['photo' => UploadedFile::fake()->image('giulia.jpg')]));

    $this->put(route('people.update', $giulia), giuliaFields(['notes' => 'Danza il martedì']))->assertSessionHasNoErrors();

    expect(insideBianchi(fn (): ?string => $giulia->fresh()?->photo?->original_name))->toBe('giulia.jpg');
});

test('removing the photo goes back to the initials and deletes the file', function (): void {
    $giulia = insideBianchi(fn (): Person => Person::query()->where('first_name', 'Giulia')->sole());
    $this->put(route('people.update', $giulia), giuliaFields(['photo' => UploadedFile::fake()->image('giulia.jpg')]));
    $photo = insideBianchi(fn (): ?File => $giulia->fresh()?->photo);

    $this->put(route('people.update', $giulia), giuliaFields(['remove_photo' => true]))->assertSessionHasNoErrors();

    expect($giulia->fresh()?->photo_id)->toBeNull();
    Storage::disk('local')->assertMissing((string) $photo?->path);
});

test('the photo of a person and the logo of an organization are images', function (): void {
    $this->post(route('people.store'), [
        'first_name' => 'Rosa', 'last_name' => 'Bianchi', 'role' => 'other',
        'photo' => UploadedFile::fake()->create('rosa.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('photo');
    $this->post(route('organizations.store'), [
        'name' => 'Coop', 'logo' => UploadedFile::fake()->create('coop.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('logo');
});

test('an admin adds an organization with a logo, shown on its page and in Organizations', function (): void {
    $this->post(route('organizations.store'), ['name' => 'Coop', 'logo' => UploadedFile::fake()->image('coop.png')])
        ->assertSessionHasNoErrors();

    $coop = insideBianchi(fn (): Organization => Organization::query()->where('name', 'Coop')->sole());
    $logo = insideBianchi(fn (): ?File => $coop->logo);
    expect($logo?->original_name)->toBe('coop.png');

    $this->get(route('organizations.show', $coop))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('organization.logo', route('files.show', $logo)));
    $this->get(route('organizations.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('organizations', fn ($organizations): bool => collect($organizations)->firstWhere('name', 'Coop')['logo'] === route('files.show', $logo)));
});

test('a new logo replaces the previous one, and removing it deletes the file', function (): void {
    $edison = insideBianchi(fn (): Organization => Organization::query()->where('name', 'Edison')->sole());

    $this->put(route('organizations.update', $edison), ['name' => 'Edison', 'logo' => UploadedFile::fake()->image('old.png')]);
    $old = insideBianchi(fn (): ?File => $edison->fresh()?->logo);
    $this->put(route('organizations.update', $edison), ['name' => 'Edison', 'logo' => UploadedFile::fake()->image('new.png')])->assertSessionHasNoErrors();
    $new = insideBianchi(fn (): ?File => $edison->fresh()?->logo);

    expect($new?->original_name)->toBe('new.png');
    Storage::disk('local')->assertMissing((string) $old?->path);

    $this->put(route('organizations.update', $edison), ['name' => 'Edison', 'remove_logo' => true])->assertSessionHasNoErrors();

    expect($edison->fresh()?->logo_id)->toBeNull();
    Storage::disk('local')->assertMissing((string) $new?->path);
});

test('a HEIC photo is shown through its preview, which every browser opens', function (): void {
    $giulia = insideBianchi(fn (): Person => Person::query()->where('first_name', 'Giulia')->sole());

    $this->put(route('people.update', $giulia), giuliaFields(['photo' => UploadedFile::fake()->createWithContent('giulia.heic', 'heic')->mimeType('image/heic')]));
    $photo = insideBianchi(fn (): ?File => $giulia->fresh()?->photo);
    expect($photo)->not->toBeNull();
    insideBianchi(fn (): bool => $photo?->update(['mime_type' => 'image/heic']) ?? false);

    $this->get(route('people.show', $giulia))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('person.photo', route('files.preview', $photo)));
});

test('the person of a document shows their photo', function (): void {
    $giulia = insideBianchi(fn (): Person => Person::query()->where('first_name', 'Giulia')->sole());
    $this->put(route('people.update', $giulia), giuliaFields(['photo' => UploadedFile::fake()->image('giulia.jpg')]));
    $photo = insideBianchi(fn (): ?File => $giulia->fresh()?->photo);
    $document = insideBianchi(fn (): int => $giulia->documents()->value('id'));

    $this->get(route('documents.show', $document))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('document.person_photo', route('files.show', $photo)));
});
