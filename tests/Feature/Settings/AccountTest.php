<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\File;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
});

/**
 * Laura, who joins Marco's family with an account and her person.
 */
function laura(User $marco, Role $role = Role::Member): User
{
    $laura = User::factory()->for($marco->family)->create(['name' => 'Laura Bianchi', 'role' => $role]);
    Person::factory()->for($marco->family)->create(['first_name' => 'Laura', 'last_name' => 'Bianchi', 'user_id' => $laura->id]);

    return $laura;
}

test('the settings index shows my account and the categories', function (): void {
    $this->get(route('settings'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/Settings')
        ->where('account.first_name', 'Marco')
        ->where('account.role', 'Administrator')
        ->where('mainCategories', 10));
});

test('my account shows first name, last name, email and whether I am the last admin', function (): void {
    $this->get(route('account.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/Account')
        ->where('account.first_name', 'Marco')
        ->where('account.last_name', 'Bianchi')
        ->where('account.email', 'marco@example.com')
        ->where('account.initials', 'MB')
        ->where('account.photo', null)
        ->where('account.is_last_admin', true));
});

test('first name, last name and email change on the person too', function (): void {
    $this->patch(route('account.update'), ['first_name' => 'Marcello', 'last_name' => 'Bianchi', 'email' => 'marcello@example.com'])
        ->assertSessionHasNoErrors()->assertRedirect(route('account.edit'));

    $marco = $this->marco->fresh();
    expect($marco?->name)->toBe('Marcello Bianchi')
        ->and($marco?->email)->toBe('marcello@example.com')
        ->and($marco?->email_verified_at)->toBeNull()
        ->and($marco?->person?->first_name)->toBe('Marcello')
        ->and($marco?->person?->initials)->toBe('MB');
});

test('the password changes only with the current one', function (): void {
    $this->put(route('account.password'), ['current_password' => 'wrong', 'password' => 'a-new-password'])
        ->assertSessionHasErrors('current_password');
    $this->put(route('account.password'), ['current_password' => 'a-long-password', 'password' => 'a-new-password'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('a-new-password', (string) $this->marco->fresh()?->password))->toBeTrue();
});

test('the photo is uploaded and takes the place of the previous one', function (): void {
    $this->post(route('account.photo'), ['photo' => UploadedFile::fake()->image('marco.jpg')])->assertSessionHasNoErrors();
    $previous = File::query()->sole();
    $this->post(route('account.photo'), ['photo' => UploadedFile::fake()->image('marco-seaside.png')])->assertSessionHasNoErrors();

    $photo = $this->marco->fresh()?->person?->photo;
    expect($photo?->original_name)->toBe('marco-seaside.png')
        ->and(File::query()->count())->toBe(1);
    Storage::disk('local')->assertMissing($previous->path);
    Storage::disk('local')->assertExists((string) $photo?->path);
    $this->get(route('account.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('account.photo', route('files.show', $photo)));
});

test('the photo is an image', function (): void {
    $this->post(route('account.photo'), ['photo' => UploadedFile::fake()->create('marco.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('photo');
});

test('the last admin cannot be deleted, until there is another admin', function (): void {
    $this->delete(route('account.destroy'), ['password' => 'a-long-password'])
        ->assertSessionHasErrors(['password' => 'You are the only administrator of the family: first give the level "Everything, like you" to another person.']);
    expect($this->marco->fresh())->not->toBeNull();

    laura($this->marco, Role::Admin);
    $this->delete(route('account.destroy'), ['password' => 'a-long-password'])->assertRedirect(route('login'));
    expect($this->marco->fresh())->toBeNull();
});

test('Laura deletes her account with the password, and her person stays', function (): void {
    $laura = laura($this->marco);
    $this->actingAs($laura);

    $this->get(route('account.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('account.is_last_admin', false));
    $this->delete(route('account.destroy'), ['password' => 'wrong'])->assertSessionHasErrors('password');
    $this->delete(route('account.destroy'), ['password' => 'password'])->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::query()->whereKey($laura->id)->exists())->toBeFalse()
        ->and(Person::withoutGlobalScopes()->where('first_name', 'Laura')->sole()->user_id)->toBeNull();
});
