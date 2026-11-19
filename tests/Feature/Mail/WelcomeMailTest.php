<?php

declare(strict_types=1);

use App\Mail\WelcomeMail;
use App\Models\Family;
use App\Models\User;
use App\Support\MailIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.url' => 'https://my.fameja.app']);
    $this->laura = User::factory()->for(Family::factory()->create(['name' => 'Rossi']))
        ->create(['name' => 'Laura Rossi', 'email' => 'laura@example.com', 'email_verified_at' => null]);
});

test('the welcome mail greets, names the family and gives the link of a week', function (): void {
    new WelcomeMail($this->laura)
        ->assertHasSubject('Welcome to Fameja.app')
        ->assertSeeInHtml('Hi Laura')
        ->assertSeeInHtml('Welcome to Fameja.app: the Rossi family is ready. Choose your password to sign in.')
        ->assertSeeInHtml('Choose your password')
        ->assertSeeInHtml('https://my.fameja.app/start/'.$this->laura->id.'?')
        ->assertSeeInHtml('The link is valid for 7 days.')
        ->assertSeeInHtml('We are writing to you because a Fameja.app account was opened with this email.')
        ->assertSeeInHtml('https://my.fameja.app/mail-icons/welcome.png');
});

test('the welcome mail in Italian', function (): void {
    new WelcomeMail($this->laura)->locale('it')
        ->assertHasSubject('Ti diamo il benvenuto in Fameja.app')
        ->assertSeeInHtml('Ciao Laura')
        ->assertSeeInHtml('Scegli la tua password')
        ->assertSeeInHtml('Il link vale 7 giorni.');
});

test('the welcome mail has its icon', function (): void {
    expect(MailIcons::all())->toHaveKey('welcome');
});

test('the pictures of a mail sent from the panel are on the host of the app', function (): void {
    // The generator of a request of the panel writes the host of the panel
    URL::forceRootUrl('https://admin.fameja.app');

    new WelcomeMail($this->laura)
        ->assertSeeInHtml('https://my.fameja.app/mail-icons/welcome.png')
        ->assertSeeInHtml('https://my.fameja.app/apple-touch-icon.png')
        ->assertDontSeeInHtml('admin.fameja.app');
});
