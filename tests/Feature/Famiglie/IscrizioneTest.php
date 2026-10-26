<?php

declare(strict_types=1);

use App\Enums\Ruolo;
use App\Enums\RuoloPersona;
use App\Models\DocumentCategory;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\FamilySetting;
use App\Models\Person;
use App\Models\User;

/**
 * I dati del modulo *Iscriviti* per una persona della famiglia Bianchi.
 *
 * @return array{nome: string, cognome: string, email: string, password: string, condizioni: string}
 */
function iscrizione(string $nome = 'Marco', string $cognome = 'Bianchi', string $email = 'marco@example.com'): array
{
    return [
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'password' => 'una-password-lunga',
        'condizioni' => '1',
    ];
}

test('iscriversi crea famiglia, account admin, persona e categorie', function (): void {
    $this->post(route('register.store'), iscrizione())->assertSessionHasNoErrors();

    $this->assertAuthenticated();
    $marco = User::query()->sole();
    $persona = Person::query()->sole();
    $casa = DocumentCategory::query()->where('name', 'Casa e utenze')->sole();

    expect(Family::query()->sole()->name)->toBe('Bianchi')
        ->and($marco->role)->toBe(Ruolo::Admin)
        ->and($marco->name)->toBe('Marco Bianchi')
        ->and($persona->user_id)->toBe($marco->id)
        ->and($persona->first_name)->toBe('Marco')
        ->and($persona->last_name)->toBe('Bianchi')
        ->and($persona->initials)->toBe('MB')
        ->and($persona->role)->toBe(RuoloPersona::Parent)
        ->and(DocumentCategory::query()->whereNull('parent_id')->count())->toBe(10)
        ->and($casa->children()->pluck('name')->all())
        ->toBe(['Luce', 'Gas', 'Acqua', 'Internet', 'Telefonia', 'TARI', 'Canone RAI'])
        ->and(ExpenseCategory::query()->whereNull('parent_id')->count())->toBe(12)
        ->and(FamilySetting::query()->count())->toBe(1);
});

test('le categorie di una famiglia nuova non toccano quelle di un\'altra', function (): void {
    $this->post(route('register.store'), iscrizione())->assertSessionHasNoErrors();
    auth()->logout();
    $this->post(route('register.store'), iscrizione('Anna', 'Rossi', 'anna@example.com'))->assertSessionHasNoErrors();

    $anna = User::query()->where('email', 'anna@example.com')->sole();
    $categorie = DocumentCategory::query()->get();

    expect($categorie)->toHaveCount(DocumentCategory::withoutGlobalScopes()->count() / 2)
        ->and($categorie->pluck('family_id')->unique()->all())->toBe([$anna->family_id])
        ->and($categorie->whereNotNull('parent_id')->every(
            fn (DocumentCategory $c): bool => $categorie->contains('id', $c->parent_id)
        ))->toBeTrue();
});

test('senza accettare le condizioni non ci si iscrive', function (): void {
    $this->post(route('register.store'), [...iscrizione(), 'condizioni' => ''])
        ->assertSessionHasErrors('condizioni');

    $this->assertGuest();
    expect(Family::query()->count())->toBe(0);
});
