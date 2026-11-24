<?php

declare(strict_types=1);

use App\Actions\Families\CreateFamily;
use App\Enums\CallOutcome;
use App\Models\Family;
use App\Models\FamilySetting;
use App\Models\File;
use App\Models\MailInboxCall;
use App\Models\ReceivedMail;
use App\Models\ReminderNotice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\Support\MailgunCall;

const MAIL_OFF_ADDRESS = 'bianchi.k7m2x9@bollette.familymanager.test';

/**
 * Turns the Mail off or on for the family of an account.
 *
 * @param  User  $user  the account
 * @param  bool  $on  whether the Mail is on
 */
function mailOffSwitch(User $user, bool $on): void
{
    $user->family->forceFill(['mail_enabled' => $on])->save();
}

beforeEach(function (): void {
    Queue::fake();
    config(['services.mailgun.inbox_domain' => 'bollette.familymanager.test']);
    $this->admin = familyWithMailAddress('Bianchi', 'marco@example.com', MAIL_OFF_ADDRESS);
});

test('every route of the received mail and of its settings sits behind the Mail feature, the webhook does not', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => preg_match('/^(mail\.|mail-settings\.)/', (string) $route->getName()) === 1);

    foreach ($routes as $route) {
        $behind = in_array('feature:mail', $route->gatherMiddleware(), true);

        expect($behind)->toBe($route->getName() !== 'mail.mailgun', "{$route->getName()} has the wrong feature middleware");
    }
});

test('the pages of the mail answer 404 with the Mail off and work with it on', function (string $url): void {
    $this->actingAs($this->admin)->get($url)->assertOk();

    mailOffSwitch($this->admin, false);

    $this->actingAs($this->admin->fresh())->get($url)->assertNotFound();
})->with([
    'received mail' => '/mail',
    'mail settings' => '/settings/mail',
]);

test('a family created now has no address for the bills', function (): void {
    $created = resolve(CreateFamily::class)->handle('Neri', 'Anna', 'Neri', 'neri@example.com', 'password-1234');

    expect(FamilySetting::query()->withoutGlobalScopes()->where('family_id', $created->family_id)->value('mail_inbox_recipient'))->toBeNull();
});

test('mail-inbox:addresses skips a family with the Mail off', function (): void {
    $off = Family::factory()->create(['name' => 'Verdi', 'mail_enabled' => false]);
    $on = Family::factory()->create(['name' => 'Neri', 'mail_enabled' => true]);

    $this->artisan('mail-inbox:addresses')->expectsOutput('Addresses made: 1')->assertSuccessful();

    $recipient = fn (Family $family) => FamilySetting::query()->withoutGlobalScopes()->where('family_id', $family->id)->value('mail_inbox_recipient');

    expect($recipient($off))->toBeNull()->and($recipient($on))->not->toBeNull();
});

test('a signed call to a family with the Mail off is answered 200, saves nothing and is logged as MailOff', function (): void {
    mailOffSwitch($this->admin, false);

    MailgunCall::to(MAIL_OFF_ADDRESS)->attach('bolletta.pdf', "%PDF-1.7\n", 'application/pdf')->send()->assertOk();

    expect(File::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(ReceivedMail::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(MailInboxCall::query()->where('family_id', $this->admin->family_id)->value('outcome'))->toBe(CallOutcome::MailOff);

    mailOffSwitch($this->admin, true);

    MailgunCall::to(MAIL_OFF_ADDRESS)->attach('bolletta.pdf', "%PDF-1.7\n", 'application/pdf')->send()->assertOk();

    expect(File::query()->withoutGlobalScopes()->count())->toBe(1);
});

test('the Home has no mails and the bell no mail notices with the Mail off', function (): void {
    $admin = $this->admin;
    insideFamilyOf($admin, function () use ($admin): void {
        $mail = ReceivedMail::factory()->create(['family_id' => $admin->family_id]);
        ReminderNotice::factory()->create([
            'family_id' => $admin->family_id,
            'user_id' => $admin->id,
            'reminder_id' => null,
            'due_date' => null,
            'offset' => null,
            'received_mail_id' => $mail->id,
            'lines' => ['x'],
            'sent_at' => CarbonImmutable::now(),
        ]);
    });
    $count = fn (): int => $this->actingAs($admin->fresh())->get('/notices')->viewData('page')['props']['unread_notices'];

    expect($count())->toBe(1);

    mailOffSwitch($admin, false);

    $this->actingAs($admin->fresh())->get('/notices')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('unread', [])->where('seen', [])->where('hasNotices', false)->where('unread_notices', 0));
    $this->actingAs($admin->fresh())->get('/dashboard')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('home.mails', []));
});
