<?php

declare(strict_types=1);

use App\Enums\PersonRole;
use App\Enums\Role;
use App\Mail\InvitationMail;
use App\Models\Family;
use App\Models\Invitation;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Mail::fake();
    $this->family = Family::factory()->create(['name' => 'Bianchi']);
    $this->marco = User::factory()->for($this->family)->admin()->create(['name' => 'Marco Bianchi']);
    Person::factory()->for($this->family)->create(['first_name' => 'Marco', 'last_name' => 'Bianchi', 'user_id' => $this->marco->id]);
    $this->actingAs($this->marco);
    $this->laura = Person::factory()->for($this->family)->create(['first_name' => 'Laura', 'last_name' => 'Bianchi']);
});

/**
 * Marco sends an invitation and then signs out, as if someone else opened the link.
 *
 * @param  array<string, mixed>  $data
 */
function invite(array $data): Invitation
{
    test()->post(route('family.invitations.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $invitation = Invitation::query()->latest('id')->firstOrFail();
    auth()->logout();

    return $invitation;
}

test('Marco invites Laura, who is already there as a person, and she gets the email', function (): void {
    $invitation = invite(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'admin']);

    expect($invitation->token)->toHaveLength(64)
        ->and($invitation->expires_at->toDateString())->toBe(now()->addDays(7)->toDateString());
    Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail): bool => $mail->hasTo('laura.bianchi@example.com'));
});

test('the page after sending shows the link to copy', function (): void {
    $this->post(route('family.invitations.store'), ['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);
    $invitation = Invitation::query()->sole();

    $this->get(route('family.invitations.show', $invitation))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('family/InvitationSent')
        ->where('invitation.link', route('invitation.show', $invitation->token))
        ->where('invitation.email', 'laura.bianchi@example.com')
        ->where('invitation.expires_on', $invitation->expires_at->toDateString()));
});

test('an invitation for the person Laura creates the account tied to Laura, without a new person', function (): void {
    $invitation = invite(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'admin']);

    $this->post(route('invitation.store', $invitation->token), ['password' => 'a-long-password'])->assertRedirect(route('dashboard'));

    $laura = User::query()->where('email', 'laura.bianchi@example.com')->sole();
    $this->assertAuthenticatedAs($laura);
    expect($laura->family_id)->toBe($this->family->id)
        ->and($laura->role)->toBe(Role::Admin)
        ->and($laura->name)->toBe('Laura Bianchi')
        ->and($laura->email_verified_at)->not->toBeNull()
        ->and(Person::query()->count())->toBe(2)
        ->and($this->laura->fresh()?->user_id)->toBe($laura->id);
});

test('an invitation without a person creates grandma Rosa, with the level of the invitation', function (): void {
    $invitation = invite([
        'email' => 'rosa.bianchi@example.com', 'role' => 'member',
        'first_name' => 'Rosa', 'last_name' => 'Bianchi', 'person_role' => 'other',
    ]);

    $this->post(route('invitation.store', $invitation->token), ['password' => 'a-long-password']);

    $rosa = User::query()->where('email', 'rosa.bianchi@example.com')->sole();
    $person = CurrentFamily::using($this->family->id, fn () => Person::query()->where('user_id', $rosa->id)->sole());
    expect($rosa->role)->toBe(Role::Member)
        ->and($person->first_name)->toBe('Rosa')
        ->and($person->role)->toBe(PersonRole::Other)
        ->and($person->initials)->toBe('RB');
});

test('the invitation page greets by name and shows the family', function (): void {
    $invitation = invite(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);

    $this->get(route('invitation.show', $invitation->token))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('auth/AcceptInvitation')
        ->where('invitation.first_name', 'Laura')
        ->where('invitation.family', 'Bianchi')
        ->where('invitation.inviter', 'Marco')
        ->where('invitation.email', 'laura.bianchi@example.com')
        ->has('invitation.people', 1));
});

test('an invitation works once: opening it again says it is no longer valid', function (): void {
    $invitation = invite(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);
    $this->post(route('invitation.store', $invitation->token), ['password' => 'a-long-password']);
    auth()->logout();

    $this->get(route('invitation.show', $invitation->token))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('auth/InvalidInvitation'));
    $this->post(route('invitation.store', $invitation->token), ['password' => 'another-password'])->assertNotFound();
});

test('an expired invitation and one with an email that already has an account create nothing', function (string $case): void {
    $invitation = invite(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);
    match ($case) {
        'expired' => $this->travel(8)->days(),
        'email already used' => User::factory()->create(['email' => 'laura.bianchi@example.com']),
    };
    $accounts = User::query()->count();

    $this->get(route('invitation.show', $invitation->token))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('auth/InvalidInvitation'));
    $this->post(route('invitation.store', $invitation->token), ['password' => 'a-long-password'])->assertNotFound();

    expect(User::query()->count())->toBe($accounts);
    $this->assertGuest();
})->with(['expired', 'email already used']);

test('a second invitation for a person who already accepted one is no longer valid', function (): void {
    $first = invite(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);
    $this->actingAs($this->marco);
    $second = invite(['email' => 'laura@work.example.com', 'person_id' => $this->laura->id, 'role' => 'member']);
    $this->post(route('invitation.store', $first->token), ['password' => 'a-long-password'])->assertRedirect(route('dashboard'));
    auth()->logout();
    $accounts = User::query()->count();

    $this->get(route('invitation.show', $second->token))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('auth/InvalidInvitation'));
    $this->post(route('invitation.store', $second->token), ['password' => 'a-long-password'])->assertNotFound();

    expect(User::query()->count())->toBe($accounts)
        ->and($this->laura->fresh()->user_id)->toBe(User::query()->where('email', 'laura.bianchi@example.com')->value('id'));
});

test('an invitation for a person who was deleted is no longer valid, and creates nobody', function (): void {
    $invitation = invite(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);
    $this->actingAs($this->marco)->delete(route('people.destroy', $this->laura))->assertRedirect(route('people.index'));
    auth()->logout();
    $people = CurrentFamily::using($this->family->id, fn (): int => Person::query()->count());

    $this->get(route('invitation.show', $invitation->token))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('auth/InvalidInvitation'));
    $this->post(route('invitation.store', $invitation->token), ['password' => 'a-long-password'])->assertNotFound();

    expect(User::query()->where('email', 'laura.bianchi@example.com')->exists())->toBeFalse()
        ->and(CurrentFamily::using($this->family->id, fn (): int => Person::query()->count()))->toBe($people);
});

test('a made-up link says the invitation is not valid', function (): void {
    auth()->logout();

    $this->get(route('invitation.show', str_repeat('a', 64)))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('auth/InvalidInvitation'));
});

test('nobody invites a person of another family nor an email that already has an account', function (): void {
    $other = CurrentFamily::using(Family::factory()->create()->id, fn () => Person::factory()->create());

    $this->post(route('family.invitations.store'), ['email' => 'x@example.com', 'person_id' => $other->id, 'role' => 'member'])
        ->assertSessionHasErrors('person_id');
    $this->post(route('family.invitations.store'), ['email' => $this->marco->email, 'person_id' => $this->laura->id, 'role' => 'member'])
        ->assertSessionHasErrors('email');
});

test('the invitation email has the link and the expiry', function (): void {
    $invitation = invite(['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);

    $text = new InvitationMail($invitation)->render();

    expect($text)->toContain(route('invitation.show', $invitation->token))
        ->toContain($invitation->expires_at->format('d/m/Y'))
        ->toContain('Marco Bianchi')
        ->toContain('Join the family');
});

test('the invitation email goes out in the language of whoever sends it', function (): void {
    $this->withHeader('Accept-Language', 'it')
        ->post(route('family.invitations.store'), ['email' => 'laura.bianchi@example.com', 'person_id' => $this->laura->id, 'role' => 'member']);

    Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail): bool => $mail->locale === 'it');
});
