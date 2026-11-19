<?php

declare(strict_types=1);

use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Resources\Families\Pages\ListFamilies;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\UsersRelationManager;
use App\Mail\WelcomeMail;
use App\Models\Family;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Mail::fake();
});

/**
 * Signs in as an owner, after the data of the test is made.
 */
function createFamilyPanelAsOwner(): void
{
    actingAs(Owner::factory()->withAuthenticator()->create(), 'owner');
}

/**
 * The fields of the form for the Rossi family.
 *
 * @return array<string, string>
 */
function createFamilyPanelRossi(): array
{
    return ['first_name' => 'Laura', 'last_name' => 'Rossi', 'family_name' => 'Rossi', 'email' => 'laura@example.com', 'locale' => 'it'];
}

test('the owner creates a family from the list and lands on its page', function (): void {
    createFamilyPanelAsOwner();

    $component = Livewire::test(ListFamilies::class)
        ->callAction('createFamily', createFamilyPanelRossi())
        ->assertHasNoActionErrors();

    $family = Family::query()->where('name', 'Rossi')->sole();
    $component->assertRedirect(FamilyResource::getUrl('view', ['record' => $family]));
    Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail): bool => $mail->hasTo('laura@example.com') && $mail->locale === 'it');
});

test('the family name starts as the last name', function (): void {
    createFamilyPanelAsOwner();

    Livewire::test(ListFamilies::class)
        ->mountAction('createFamily')
        ->fillForm(['last_name' => 'Rossi'])
        ->assertSchemaStateSet(['family_name' => 'Rossi']);
});

test('a family name written by hand stays when the last name changes', function (): void {
    createFamilyPanelAsOwner();

    Livewire::test(ListFamilies::class)
        ->mountAction('createFamily')
        ->fillForm(['family_name' => 'Rossi-Verdi'])
        ->fillForm(['last_name' => 'Rossi'])
        ->assertSchemaStateSet(['family_name' => 'Rossi-Verdi']);
});

test('the language starts as Italian', function (): void {
    createFamilyPanelAsOwner();

    Livewire::test(ListFamilies::class)
        ->mountAction('createFamily')
        ->assertSchemaStateSet(['locale' => 'it']);
});

test('an email already used is refused, also with capitals', function (): void {
    User::factory()->for(Family::factory())->create(['email' => 'laura@example.com']);
    createFamilyPanelAsOwner();

    Livewire::test(ListFamilies::class)
        ->callAction('createFamily', [...createFamilyPanelRossi(), 'email' => 'LAURA@example.com'])
        ->assertHasActionErrors(['email']);

    expect(Family::query()->where('name', 'Rossi')->exists())->toBeFalse();
});

test('every field is required and the email must be an email', function (): void {
    createFamilyPanelAsOwner();

    Livewire::test(ListFamilies::class)
        ->callAction('createFamily', ['first_name' => '', 'last_name' => '', 'family_name' => '', 'email' => 'not-an-email', 'locale' => 'fr'])
        ->assertHasActionErrors(['first_name' => 'required', 'last_name' => 'required', 'family_name' => 'required', 'email', 'locale']);

    expect(Family::query()->count())->toBe(0);
});

test('the welcome mail can be sent again, in the language of the panel', function (): void {
    $family = Family::factory()->create(['name' => 'Rossi']);
    $laura = User::factory()->for($family)->create(['email' => 'laura@example.com', 'email_verified_at' => null]);
    createFamilyPanelAsOwner();
    app()->setLocale('en');

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->callTableAction('resendWelcome', $laura);

    Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail): bool => $mail->hasTo('laura@example.com') && $mail->locale === 'en');
});

test('the welcome mail is not offered to a confirmed account', function (): void {
    $family = Family::factory()->create(['name' => 'Rossi']);
    $laura = User::factory()->for($family)->create(['email_verified_at' => now()]);
    createFamilyPanelAsOwner();

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->assertTableActionHidden('resendWelcome', $laura);
});
