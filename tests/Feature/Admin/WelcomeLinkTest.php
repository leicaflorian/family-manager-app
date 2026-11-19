<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\User;
use App\Support\WelcomeLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    config(['app.url' => 'https://my.fameja.app']);
    $this->laura = User::factory()->for(Family::factory()->create(['name' => 'Rossi']))
        ->create(['email' => 'laura@example.com', 'email_verified_at' => null]);
});

/**
 * The token of the page of the password a welcome link sends to.
 *
 * @param  string  $location  the address of the redirect
 */
function welcomeLinkToken(string $location): string
{
    $path = (string) parse_url($location, PHP_URL_PATH);

    return basename($path);
}

test('the link is on the host of the app, also from the panel', function (): void {
    // The generator of a request of the panel writes the host of the panel
    URL::forceRootUrl('https://admin.fameja.app');

    expect(WelcomeLink::for($this->laura))->toStartWith('https://my.fameja.app/start/'.$this->laura->id.'?');
});

test('opening the link opens the choice of the password, and does not confirm the email yet', function (): void {
    $response = $this->get(WelcomeLink::for($this->laura));

    $location = (string) $response->headers->get('Location');
    expect($location)->toStartWith(rtrim(route('password.reset', ['token' => 'x']), 'x'))
        ->and($location)->toContain('email=laura%40example.com')
        ->and($this->laura->refresh()->email_verified_at)->toBeNull()
        ->and(Password::broker()->tokenExists($this->laura, welcomeLinkToken($location)))->toBeTrue();
});

test('the token of the link lasts like a forgotten password', function (): void {
    $this->get(WelcomeLink::for($this->laura));

    expect(config('auth.passwords.users.expire'))->toBe(60);
});

test('a used link goes to the sign in with a message', function (): void {
    $link = WelcomeLink::for($this->laura);
    $this->laura->forceFill(['email_verified_at' => now()])->save();

    $this->get($link)
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'This link was already used. To sign in, use Forgot password.');

    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

test('a link opened before the person, by a mail scanner, still works for the person', function (): void {
    $link = WelcomeLink::for($this->laura);
    $this->get($link);

    $location = (string) $this->get($link)->headers->get('Location');

    expect($location)->toContain('/reset-password/')
        ->and(Password::broker()->tokenExists($this->laura, welcomeLinkToken($location)))->toBeTrue();
});

test('saving the password confirms the email, and the link is used', function (): void {
    $link = WelcomeLink::for($this->laura);
    $location = (string) $this->get($link)->headers->get('Location');

    $this->post(route('password.update'), [
        'token' => welcomeLinkToken($location),
        'email' => 'laura@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasNoErrors();

    expect($this->laura->refresh()->email_verified_at)->not->toBeNull();
    $this->get($link)->assertRedirect(route('login'));
});

test('an old link does not open anything after the email changes', function (): void {
    $link = WelcomeLink::for($this->laura);
    $this->laura->forceFill(['email' => 'laura.new@example.com', 'email_verified_at' => null])->save();

    $this->get($link)
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'This link was already used. To sign in, use Forgot password.');

    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

test('an expired link answers 403', function (): void {
    $link = WelcomeLink::for($this->laura);

    $this->travel(8)->days();

    $this->get($link)->assertForbidden();
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

test('a link still valid after six days', function (): void {
    $link = WelcomeLink::for($this->laura);

    $this->travel(6)->days();

    $this->get($link)->assertRedirectContains('/reset-password/');
});

test('a forged link answers 403', function (): void {
    $link = WelcomeLink::for($this->laura);

    $this->get(preg_replace('/signature=[^&]+/', 'signature=forged', $link))->assertForbidden();
});

test('the password page then sets the password', function (): void {
    $location = (string) $this->get(WelcomeLink::for($this->laura))->headers->get('Location');

    $this->post(route('password.update'), [
        'token' => welcomeLinkToken($location),
        'email' => 'laura@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('a-brand-new-password', (string) $this->laura->refresh()->password))->toBeTrue();
});
