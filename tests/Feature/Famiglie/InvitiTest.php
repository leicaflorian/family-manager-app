<?php

declare(strict_types=1);

use App\Enums\Ruolo;
use App\Enums\RuoloPersona;
use App\Mail\Invito;
use App\Models\Family;
use App\Models\Invitation;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Mail::fake();
    $this->famiglia = Family::factory()->create(['name' => 'Bianchi']);
    $this->marco = User::factory()->for($this->famiglia)->admin()->create(['name' => 'Marco Bianchi']);
    Person::factory()->for($this->famiglia)->create(['first_name' => 'Marco', 'last_name' => 'Bianchi', 'user_id' => $this->marco->id]);
    $this->actingAs($this->marco);
    $this->laura = Person::factory()->for($this->famiglia)->create(['first_name' => 'Laura', 'last_name' => 'Bianchi']);
});

/**
 * Marco manda un invito e poi esce, come se il link lo aprisse un altro.
 *
 * @param  array<string, mixed>  $dati
 */
function invita(array $dati): Invitation
{
    test()->post(route('famiglia.inviti.store'), $dati)->assertSessionHasNoErrors()->assertRedirect();
    $invito = Invitation::query()->latest('id')->firstOrFail();
    auth()->logout();

    return $invito;
}

test('Marco invita Laura, che c\'è già come persona, e le arriva l\'email', function (): void {
    $invito = invita(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'admin']);

    expect($invito->token)->toHaveLength(64)
        ->and($invito->expires_at->toDateString())->toBe(now()->addDays(7)->toDateString());
    Mail::assertQueued(Invito::class, fn (Invito $mail): bool => $mail->hasTo('laura.bianchi@example.com'));
});

test('la pagina dopo l\'invio mostra il link da copiare', function (): void {
    $this->post(route('famiglia.inviti.store'), ['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);
    $invito = Invitation::query()->sole();

    $this->get(route('famiglia.inviti.show', $invito))->assertInertia(fn (AssertableInertia $pagina): AssertableInertia => $pagina
        ->component('famiglia/InvitoMandato')
        ->where('invito.link', route('invito.show', $invito->token))
        ->where('invito.email', 'laura.bianchi@example.com'));
});

test('un invito con la persona Laura crea l\'account legato a Laura, senza una persona nuova', function (): void {
    $invito = invita(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'admin']);

    $this->post(route('invito.store', $invito->token), ['password' => 'una-password-lunga'])->assertRedirect(route('dashboard'));

    $laura = User::query()->where('email', 'laura.bianchi@example.com')->sole();
    $this->assertAuthenticatedAs($laura);
    expect($laura->family_id)->toBe($this->famiglia->id)
        ->and($laura->role)->toBe(Ruolo::Admin)
        ->and($laura->name)->toBe('Laura Bianchi')
        ->and($laura->email_verified_at)->not->toBeNull()
        ->and(Person::query()->count())->toBe(2)
        ->and($this->laura->fresh()?->user_id)->toBe($laura->id);
});

test('un invito senza persona crea la persona nonna Rosa, con il livello dell\'invito', function (): void {
    $invito = invita([
        'email' => 'rosa.bianchi@example.com', 'role' => 'member',
        'first_name' => 'Rosa', 'last_name' => 'Bianchi', 'person_role' => 'other',
    ]);

    $this->post(route('invito.store', $invito->token), ['password' => 'una-password-lunga']);

    $rosa = User::query()->where('email', 'rosa.bianchi@example.com')->sole();
    $persona = CurrentFamily::per($this->famiglia->id, fn () => Person::query()->where('user_id', $rosa->id)->sole());
    expect($rosa->role)->toBe(Ruolo::Member)
        ->and($persona->first_name)->toBe('Rosa')
        ->and($persona->role)->toBe(RuoloPersona::Other)
        ->and($persona->initials)->toBe('RB');
});

test('la pagina dell\'invito saluta per nome e mostra la famiglia', function (): void {
    $invito = invita(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);

    $this->get(route('invito.show', $invito->token))->assertInertia(fn (AssertableInertia $pagina): AssertableInertia => $pagina
        ->component('auth/InvitoAccettato')
        ->where('invito.nome', 'Laura')
        ->where('invito.famiglia', 'Bianchi')
        ->where('invito.chiInvita', 'Marco')
        ->where('invito.email', 'laura.bianchi@example.com')
        ->has('invito.persone', 1));
});

test('l\'invito vale una volta: la seconda apertura dice che non vale più', function (): void {
    $invito = invita(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);
    $this->post(route('invito.store', $invito->token), ['password' => 'una-password-lunga']);
    auth()->logout();

    $this->get(route('invito.show', $invito->token))->assertInertia(fn (AssertableInertia $pagina): AssertableInertia => $pagina
        ->component('auth/InvitoNonValido'));
    $this->post(route('invito.store', $invito->token), ['password' => 'un-altra-password'])->assertNotFound();
});

test('un invito scaduto e uno con un\'email che ha già un account non creano niente', function (string $caso): void {
    $invito = invita(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);
    match ($caso) {
        'scaduto' => $this->travel(8)->days(),
        'email già usata' => User::factory()->create(['email' => 'laura.bianchi@example.com']),
    };
    $account = User::query()->count();

    $this->get(route('invito.show', $invito->token))->assertInertia(fn (AssertableInertia $pagina): AssertableInertia => $pagina
        ->component('auth/InvitoNonValido'));
    $this->post(route('invito.store', $invito->token), ['password' => 'una-password-lunga'])->assertNotFound();

    expect(User::query()->count())->toBe($account);
    $this->assertGuest();
})->with(['scaduto', 'email già usata']);

test('un link inventato dice che l\'invito non vale', function (): void {
    auth()->logout();

    $this->get(route('invito.show', str_repeat('a', 64)))->assertInertia(fn (AssertableInertia $pagina): AssertableInertia => $pagina
        ->component('auth/InvitoNonValido'));
});

test('non si invita una persona di un\'altra famiglia né un\'email che ha già un account', function (): void {
    $altra = CurrentFamily::per(Family::factory()->create()->id, fn () => Person::factory()->create());

    $this->post(route('famiglia.inviti.store'), ['email' => 'x@example.com', 'person_id' => $altra->id, 'role' => 'member'])
        ->assertSessionHasErrors('person_id');
    $this->post(route('famiglia.inviti.store'), ['email' => $this->marco->email, 'person_id' => $this->laura->id, 'role' => 'member'])
        ->assertSessionHasErrors('email');
});

test('l\'email dell\'invito ha il link e la scadenza', function (): void {
    $invito = invita(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);

    $testo = (new Invito($invito))->render();

    expect($testo)->toContain(route('invito.show', $invito->token))
        ->toContain($invito->expires_at->format('d/m/Y'))
        ->toContain('Marco Bianchi');
});
