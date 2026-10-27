<?php

declare(strict_types=1);

use App\Enums\Ruolo;
use App\Models\File;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');
    $this->marco = iscriviFamiglia();
    $this->actingAs($this->marco);
});

/**
 * Laura, che entra nella famiglia di Marco con un account e la sua persona.
 */
function laura(User $marco, Ruolo $ruolo = Ruolo::Member): User
{
    $laura = User::factory()->for($marco->family)->create(['name' => 'Laura Bianchi', 'role' => $ruolo]);
    Person::factory()->for($marco->family)->create(['first_name' => 'Laura', 'last_name' => 'Bianchi', 'user_id' => $laura->id]);

    return $laura;
}

test('l\'indice delle impostazioni mostra il mio account e le categorie', function (): void {
    $this->get(route('impostazioni'))->assertInertia(fn (AssertableInertia $pagina) => $pagina
        ->component('impostazioni/Impostazioni')
        ->where('account.nome', 'Marco')
        ->where('account.ruolo', 'Amministratore')
        ->where('categoriePrincipali', 10));
});

test('il mio account mostra nome, cognome, email e se sono l\'ultimo admin', function (): void {
    $this->get(route('account.edit'))->assertInertia(fn (AssertableInertia $pagina) => $pagina
        ->component('impostazioni/Account')
        ->where('account.nome', 'Marco')
        ->where('account.cognome', 'Bianchi')
        ->where('account.email', 'marco@example.com')
        ->where('account.iniziali', 'MB')
        ->where('account.foto', null)
        ->where('account.ultimoAdmin', true));
});

test('nome, cognome ed email si cambiano anche nella persona', function (): void {
    $this->patch(route('account.update'), ['nome' => 'Marcello', 'cognome' => 'Bianchi', 'email' => 'marcello@example.com'])
        ->assertSessionHasNoErrors()->assertRedirect(route('account.edit'));

    $marco = $this->marco->fresh();
    expect($marco?->name)->toBe('Marcello Bianchi')
        ->and($marco?->email)->toBe('marcello@example.com')
        ->and($marco?->email_verified_at)->toBeNull()
        ->and($marco?->person?->first_name)->toBe('Marcello')
        ->and($marco?->person?->initials)->toBe('MB');
});

test('la password si cambia solo con quella di adesso', function (): void {
    $this->put(route('account.password'), ['password_attuale' => 'sbagliata', 'password' => 'una-password-nuova'])
        ->assertSessionHasErrors('password_attuale');
    $this->put(route('account.password'), ['password_attuale' => 'una-password-lunga', 'password' => 'una-password-nuova'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('una-password-nuova', (string) $this->marco->fresh()?->password))->toBeTrue();
});

test('la foto si carica e prende il posto di quella di prima', function (): void {
    $this->post(route('account.foto'), ['foto' => UploadedFile::fake()->image('marco.jpg')])->assertSessionHasNoErrors();
    $prima = File::query()->sole();
    $this->post(route('account.foto'), ['foto' => UploadedFile::fake()->image('marco-mare.png')])->assertSessionHasNoErrors();

    $foto = $this->marco->fresh()?->person?->photo;
    expect($foto?->original_name)->toBe('marco-mare.png')
        ->and(File::query()->count())->toBe(1);
    Storage::disk('local')->assertMissing($prima->path);
    Storage::disk('local')->assertExists((string) $foto?->path);
    $this->get(route('account.edit'))->assertInertia(fn (AssertableInertia $pagina) => $pagina
        ->where('account.foto', route('file.show', $foto)));
});

test('la foto è un\'immagine', function (): void {
    $this->post(route('account.foto'), ['foto' => UploadedFile::fake()->create('marco.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('foto');
});

test('l\'ultimo admin non si elimina, finché non c\'è un altro admin', function (): void {
    $this->delete(route('account.destroy'), ['password' => 'una-password-lunga'])->assertSessionHasErrors(['password' => 'Sei l\'unico amministratore della famiglia: prima dai il livello «Tutto, come te» a un\'altra persona.']);
    expect($this->marco->fresh())->not->toBeNull();

    laura($this->marco, Ruolo::Admin);
    $this->delete(route('account.destroy'), ['password' => 'una-password-lunga'])->assertRedirect(route('login'));
    expect($this->marco->fresh())->toBeNull();
});

test('Laura elimina il suo account con la password, e la sua persona resta', function (): void {
    $laura = laura($this->marco);
    $this->actingAs($laura);

    $this->get(route('account.edit'))->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('account.ultimoAdmin', false));
    $this->delete(route('account.destroy'), ['password' => 'sbagliata'])->assertSessionHasErrors('password');
    $this->delete(route('account.destroy'), ['password' => 'password'])->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::query()->whereKey($laura->id)->exists())->toBeFalse()
        ->and(Person::withoutGlobalScopes()->where('first_name', 'Laura')->sole()->user_id)->toBeNull();
});
