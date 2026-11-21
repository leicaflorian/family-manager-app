<?php

declare(strict_types=1);

use App\Mail\BankConsentExpiringMail;
use App\Mail\InvitationMail;
use App\Models\Family;
use App\Models\Invitation;
use App\Models\Person;
use App\Models\User;
use App\Support\AppHostUrl;
use App\Support\CurrentFamily;
use App\Support\MailIcons;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\App;

uses(RefreshDatabase::class);

/**
 * The HTML of a notification's mail, rendered as it is sent.
 *
 * @param  MailMessage  $message  the mail of the notification
 */
function notificationHtml(MailMessage $message): string
{
    return (string) $message->render();
}

/**
 * The CSS properties that Mailpit's HTML check marks as badly supported by the mail programs.
 *
 * @return list<string>
 */
function unsupportedMailCss(): array
{
    return ['position:', 'box-sizing', 'rgba(', 'vw', 'box-shadow', '!important'];
}

test('the password mail says hello, gives the link and how long it lasts', function (): void {
    $user = User::factory()->for(Family::factory())->create(['name' => 'Marco Bianchi', 'email' => 'marco@example.com']);
    $message = new ResetPassword('the-token')->toMail($user);
    $html = notificationHtml($message);

    expect($message->subject)->toBe('Choose a new password')
        ->and($html)->toContain('Hi Marco')
        ->toContain('You asked to change your Fameja.app password.')
        ->toContain(route('password.reset', ['token' => 'the-token', 'email' => 'marco@example.com']))
        ->toContain('The link is valid for one hour.')
        ->toContain('If you did not ask for it, ignore this email: your password stays the same.')
        ->toContain('We are writing to you because you have an account on Fameja.app.');
});

test('the password mail in Italian', function (): void {
    App::setLocale('it');
    $user = User::factory()->for(Family::factory())->create(['name' => 'Marco Bianchi']);
    $message = new ResetPassword('the-token')->toMail($user);

    expect($message->subject)->toBe('Scegli una password nuova')
        ->and(notificationHtml($message))->toContain('Ciao Marco')
        ->toContain("Il link vale un'ora.");
});

test('the mail that confirms the email gives the link', function (): void {
    $user = User::factory()->for(Family::factory())->unverified()->create(['name' => 'Marco Bianchi']);
    $message = new VerifyEmail()->toMail($user);
    $html = notificationHtml($message);

    expect($message->subject)->toBe('Confirm your email')
        ->and($html)->toContain('Hi Marco')
        ->toContain('/email/verify/'.$user->id)
        ->toContain('If you did not sign up, ignore this email.');
});

test('the mails use only colours of the design system', function (): void {
    $theme = (string) file_get_contents(resource_path('views/vendor/mail/html/themes/family-manager.css'));
    $tokens = json_decode((string) file_get_contents(resource_path('design/tokens.json')), true, flags: JSON_THROW_ON_ERROR);
    $light = collect($tokens['color']['tokens'])
        ->map(fn (array $token): string => strtolower(is_string($token['value']) ? $token['value'] : $token['value']['light']))
        ->all();

    preg_match_all('/#[0-9a-f]{3,8}\b/i', $theme, $colors);

    expect($colors[0])->not->toBeEmpty();
    foreach ($colors[0] as $color) {
        expect($light)->toContain(strtolower($color));
    }
    expect(config('mail.markdown.theme'))->toBe('family-manager');
});

test('the mails leave out the CSS that mail programs do not read', function (): void {
    $user = User::factory()->for(Family::factory())->create(['name' => 'Marco Bianchi']);
    $html = notificationHtml(new ResetPassword('the-token')->toMail($user));

    foreach (unsupportedMailCss() as $css) {
        expect($html)->not->toContain($css);
    }
});

test('the invitation mail names who invites and the family', function (): void {
    $family = Family::factory()->create(['name' => 'Bianchi']);
    $marco = User::factory()->for($family)->admin()->create(['name' => 'Marco Bianchi']);
    $invitation = Invitation::factory()->for($family)->create(['created_by' => $marco->id]);
    $mail = new InvitationMail($invitation);

    $mail->assertHasSubject('Marco Bianchi invites you to the Bianchi family')
        ->assertSeeInHtml('Upload bills and receipts from your phone')
        ->assertSeeInHtml('Join the family')
        ->assertSeeInHtml('We are writing to you because Marco Bianchi added your email to their family.');
});

test('the mail about a bank consent says what happens and links to the banks on the host of the app', function (): void {
    config(['app.url' => 'https://my.fameja.app']);
    $user = User::factory()->for(Family::factory())->admin()->create(['name' => 'Marco Bianchi']);

    $expiring = new BankConsentExpiringMail($user, 'BancoPosta', '18/05/2027', false);
    $ended = new BankConsentExpiringMail($user, 'BancoPosta', '18/05/2027', true);

    $expiring->assertHasSubject('Connect BancoPosta again')
        ->assertSeeInHtml('The link with BancoPosta ends on 18/05/2027. Connect it again to keep receiving the transactions.')
        ->assertSeeInHtml('Connect again')
        ->assertSeeInHtml('https://my.fameja.app/settings/banks')
        ->assertSeeInHtml(AppHostUrl::asset('mail-icons/banks.png'));
    $ended->assertSeeInHtml('BancoPosta has removed the link.');
    foreach (unsupportedMailCss() as $css) {
        expect($expiring->render())->not->toContain($css);
    }
});

test('the layout has the name of the app and no Laravel words', function (): void {
    $user = User::factory()->for(Family::factory())->create(['name' => 'Marco Bianchi']);
    $html = notificationHtml(new ResetPassword('the-token')->toMail($user));

    expect($html)->toContain('Fameja.app')
        ->toContain('apple-touch-icon.png')
        ->not->toContain('All rights reserved')
        ->not->toContain('Regards');
});

test('the mail says hello with the first name of the person tied to the account', function (): void {
    $user = User::factory()->for(Family::factory())->create(['name' => 'Laura Bianchi']);
    CurrentFamily::using($user->family_id, fn () => Person::factory()->for($user->family)->create(['first_name' => 'Lalla', 'user_id' => $user->id]));

    expect($user->firstName())->toBe('Lalla')
        ->and(notificationHtml(new ResetPassword('the-token')->toMail($user)))->toContain('Hi Lalla');
});

test('the mails about the account and the report have their icon', function (): void {
    $user = User::factory()->for(Family::factory())->create(['name' => 'Marco Bianchi']);

    expect(notificationHtml(new ResetPassword('the-token')->toMail($user)))->toContain(asset('mail-icons/key.png'))
        ->and(notificationHtml(new VerifyEmail()->toMail($user)))->toContain(asset('mail-icons/mail.png'));
});

test('every icon of the mails has its picture, drawn at twice its size', function (): void {
    $icons = MailIcons::all();

    expect($icons)->not->toBeEmpty();
    foreach (array_keys($icons) as $name) {
        $size = getimagesize(public_path('mail-icons/'.$name.'.png'));
        expect($size)->not->toBeFalse()
            ->and([$size[0] ?? 0, $size[1] ?? 0])->toBe([48, 48]);
    }
});

test('the invitation shows the circles of the people of the family, one slightly over the other', function (): void {
    $family = Family::factory()->create(['name' => 'Bianchi']);
    $marco = User::factory()->for($family)->admin()->create(['name' => 'Marco Bianchi']);
    CurrentFamily::using($family->id, function () use ($family): void {
        Person::factory()->for($family)->create(['first_name' => 'Marco', 'last_name' => 'Bianchi', 'initials' => 'MB']);
        Person::factory()->for($family)->create(['first_name' => 'Laura', 'last_name' => 'Bianchi', 'initials' => 'LB']);
    });
    $invitation = Invitation::factory()->for($family)->create(['created_by' => $marco->id]);

    new InvitationMail($invitation)
        ->assertSeeInHtml('>MB<', false)
        ->assertSeeInHtml('>LB<', false)
        ->assertSeeInHtml('background-color: #0070e0', false)
        ->assertSeeInHtml('background-color: #1b7f5f; margin-left: -10px', false);
});
