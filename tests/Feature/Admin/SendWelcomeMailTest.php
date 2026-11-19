<?php

declare(strict_types=1);

use App\Actions\Admin\SendWelcomeMail;
use App\Mail\WelcomeMail;
use App\Models\Family;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function (): void {
    $this->laura = User::factory()->for(Family::factory()->create(['name' => 'Rossi']))
        ->create(['email' => 'laura@example.com', 'email_verified_at' => null, 'locale' => 'it']);
});

test('the welcome mail goes out in the language of the account, whatever the language of the panel', function (): void {
    Mail::fake();
    app()->setLocale('en');

    expect(resolve(SendWelcomeMail::class)->handle($this->laura))->toBeTrue();

    Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail): bool => $mail->hasTo('laura@example.com') && $mail->locale === 'it');
});

test('an account without a language gets the language of the panel', function (): void {
    Mail::fake();
    $this->laura->forceFill(['locale' => null])->save();
    app()->setLocale('en');

    resolve(SendWelcomeMail::class)->handle($this->laura);

    Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail): bool => $mail->locale === 'en');
});

test('a mail that does not go out is reported and told, not thrown', function (): void {
    Exceptions::fake();
    mailServerDown();

    expect(resolve(SendWelcomeMail::class)->handle($this->laura))->toBeFalse();

    Exceptions::assertReported(TransportException::class);
});
