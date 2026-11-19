<?php

declare(strict_types=1);

use App\Actions\Admin\CreateFamilyFromPanel;
use App\Enums\OwnerActionType;
use App\Enums\Role;
use App\Mail\WelcomeMail;
use App\Models\DocumentCategory;
use App\Models\Owner;
use App\Models\OwnerAction;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Mail::fake();
    $this->owner = Owner::factory()->withAuthenticator()->create();
    actingAs($this->owner, 'owner');
});

/**
 * Creates the Rossi family from the panel, with the welcome mail in the given language.
 *
 * @param  string  $locale  the language of the mail
 * @param  string  $email  the email of the account
 */
function createFamilyFromPanelRossi(string $locale = 'it', string $email = 'laura@example.com'): User
{
    return resolve(CreateFamilyFromPanel::class)->handle('Rossi', 'Laura', 'Rossi', $email, $locale);
}

test('it creates the family with its admin account', function (): void {
    $laura = createFamilyFromPanelRossi();

    expect($laura->family->name)->toBe('Rossi')
        ->and($laura->name)->toBe('Laura Rossi')
        ->and($laura->role)->toBe(Role::Admin)
        ->and($laura->email_verified_at)->toBeNull()
        ->and(CurrentFamily::using($laura->family_id, fn (): ?int => Person::query()->where('user_id', $laura->id)->value('id')))->not->toBeNull();
});

test('nobody knows the password', function (): void {
    $laura = createFamilyFromPanelRossi();

    foreach (['', 'Laura', 'Rossi', 'laura@example.com', 'password'] as $guess) {
        expect(Hash::check($guess, (string) $laura->password))->toBeFalse();
    }
});

test('the email is saved in lowercase', function (): void {
    expect(createFamilyFromPanelRossi(email: 'Laura@Example.com')->email)->toBe('laura@example.com');
});

test('the categories are in the language chosen, whatever the language of the panel', function (string $panel, string $chosen, string $name): void {
    app()->setLocale($panel);

    $laura = createFamilyFromPanelRossi($chosen);

    expect(CurrentFamily::using($laura->family_id, fn (): bool => DocumentCategory::query()->where('name', $name)->exists()))->toBeTrue()
        ->and(app()->getLocale())->toBe($panel);
})->with([
    'italian family from an english panel' => ['en', 'it', 'Casa e utenze'],
    'english family from an italian panel' => ['it', 'en', 'Home and utilities'],
]);

test('the account keeps the language chosen, for the mails of the morning', function (): void {
    expect(createFamilyFromPanelRossi('en')->locale)->toBe('en');
});

test('it writes the log', function (): void {
    $laura = createFamilyFromPanelRossi();

    $line = OwnerAction::query()->sole();
    expect($line->action)->toBe(OwnerActionType::FamilyCreated)
        ->and($line->family_id)->toBe($laura->family_id)
        ->and($line->family_name)->toBe('Rossi')
        ->and($line->owner_id)->toBe($this->owner->id);
});

test('it sends the welcome mail in the language chosen', function (): void {
    createFamilyFromPanelRossi('it');

    Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail): bool => $mail->hasTo('laura@example.com') && $mail->locale === 'it');
});

test('the log label exists in both languages', function (): void {
    expect(OwnerActionType::FamilyCreated->label())->toBe('Family created');
    app()->setLocale('it');
    expect(OwnerActionType::FamilyCreated->label())->toBe('Famiglia creata');
});
