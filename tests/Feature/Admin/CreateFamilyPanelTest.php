<?php

declare(strict_types=1);

use App\Enums\OwnerActionType;
use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Resources\Families\Pages\ListFamilies;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\UsersRelationManager;
use App\Mail\WelcomeMail;
use App\Models\Family;
use App\Models\Owner;
use App\Models\OwnerAction;
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

test('when the welcome mail does not go out, the family stays and the owner is told how to send it again', function (): void {
    createFamilyPanelAsOwner();
    mailServerDown();

    $component = Livewire::test(ListFamilies::class)
        ->callAction('createFamily', createFamilyPanelRossi())
        ->assertHasNoActionErrors()
        ->assertNotified('Family created, but the email did not go out. Send it again from the Accounts tab of the family.');

    $component->assertRedirect(FamilyResource::getUrl('view', ['record' => Family::query()->where('name', 'Rossi')->sole()]));
});

/**
 * A family created from the panel, with its first account still to confirm.
 *
 * @param  string  $locale  the language of the account
 * @return array{0: Family, 1: User}
 */
function createFamilyPanelCreated(string $locale = 'it'): array
{
    $family = Family::factory()->create(['name' => 'Rossi']);
    $laura = User::factory()->for($family)->create(['email' => 'laura@example.com', 'email_verified_at' => null, 'locale' => $locale]);
    OwnerAction::query()->create(['family_id' => $family->id, 'family_name' => 'Rossi', 'user_id' => $laura->id, 'action' => OwnerActionType::FamilyCreated]);

    return [$family, $laura];
}

test('the welcome mail can be sent again, in the language of the account', function (): void {
    [$family, $laura] = createFamilyPanelCreated('it');
    createFamilyPanelAsOwner();
    app()->setLocale('en');

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->callTableAction('resendWelcome', $laura)
        ->assertNotified('Sent');

    Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail): bool => $mail->hasTo('laura@example.com') && $mail->locale === 'it');
});

test('a welcome mail sent again that does not go out is told', function (): void {
    [$family, $laura] = createFamilyPanelCreated();
    createFamilyPanelAsOwner();
    mailServerDown();

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->callTableAction('resendWelcome', $laura)
        ->assertNotified('The email did not go out. Try again in a few minutes.');
});

test('the welcome mail is not offered to a confirmed account', function (): void {
    [$family, $laura] = createFamilyPanelCreated();
    $laura->forceFill(['email_verified_at' => now()])->save();
    createFamilyPanelAsOwner();

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->assertTableActionHidden('resendWelcome', $laura);
});

test('the welcome mail is offered only to the account the panel created, not to another one to confirm', function (): void {
    [$family] = createFamilyPanelCreated();
    $marco = User::factory()->for($family)->create(['email_verified_at' => null]);
    createFamilyPanelAsOwner();

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->assertTableActionHidden('resendWelcome', $marco);
});
