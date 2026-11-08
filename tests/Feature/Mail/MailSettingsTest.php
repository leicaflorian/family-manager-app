<?php

declare(strict_types=1);

use App\Actions\Mail\RegenerateMailAddress;
use App\Enums\Role;
use App\Models\FamilySetting;
use App\Models\Organization;
use App\Models\Person;
use App\Models\ReceivedMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

const SETTINGS_ADDRESS = 'bianchi.k7m2x9@bollette.familymanager.test';

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 09:41', 'Europe/Rome'));
    config(['services.mailgun.inbox_domain' => 'bollette.familymanager.test']);
    $this->marco = familyWithMailAddress('Bianchi', 'marco@example.com', SETTINGS_ADDRESS);
    $this->laura = User::factory()->for($this->marco->family)->create(['role' => Role::Member, 'email_verified_at' => now(), 'name' => 'Laura Bianchi', 'email' => 'laura@example.com']);
});

/**
 * The settings row of the Bianchi.
 */
function bianchiSettings(): FamilySetting
{
    return insideBianchi(fn (): FamilySetting => FamilySetting::query()->sole());
}

/**
 * A mail the Bianchi received.
 *
 * @param  array<string, mixed>  $attributes
 */
function senderMail(array $attributes): ReceivedMail
{
    return ReceivedMail::factory()->create([
        'family_id' => test()->marco->family_id,
        'recipient' => SETTINGS_ADDRESS,
        'recipients' => [SETTINGS_ADDRESS],
        ...$attributes,
    ]);
}

test('the page has the address, the senders and the accounts', function (): void {
    $edison = insideBianchi(fn (): Organization => Organization::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'Edison']));
    senderMail(['sender_address' => 'fatture@edison.example', 'organization_id' => $edison->id, 'received_at' => now()->subDays(3)]);
    senderMail(['sender' => 'Fastweb <noreply@fastweb.example>', 'sender_address' => 'noreply@fastweb.example', 'received_at' => now()->subDay()]);
    insideBianchi(fn () => Person::factory()->create(['family_id' => $this->marco->family_id, 'first_name' => 'Giulia', 'user_id' => null]));

    $this->actingAs($this->laura)->get(route('mail-settings.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/Mail')
        ->where('settings.address', SETTINGS_ADDRESS)
        ->where('settings.can_edit', false)
        ->where('settings.attachments_only', true)
        ->where('settings.senders', [['name' => 'Fastweb', 'address' => 'noreply@fastweb.example'], ['name' => 'Edison', 'address' => 'fatture@edison.example']])
        ->where('settings.accounts', [
            ['id' => $this->marco->id, 'name' => $this->marco->name, 'email' => 'marco@example.com', 'chosen' => true],
            ['id' => $this->laura->id, 'name' => 'Laura Bianchi', 'email' => 'laura@example.com', 'chosen' => true],
        ])
        ->where('settings.people_without_account', ['Giulia']));
});

test('the senders are the eight most recent ones, each once', function (): void {
    foreach (range(1, 10) as $number) {
        senderMail(['sender' => "S{$number} <s{$number}@example.test>", 'sender_address' => "s{$number}@example.test", 'received_at' => now()->subDays(20 - $number)]);
    }
    senderMail(['sender' => 'S10 <s10@example.test>', 'sender_address' => 's10@example.test', 'received_at' => now()->subDays(1)]);

    $this->actingAs($this->marco)->get(route('mail-settings.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('settings.senders', 8)
        ->where('settings.senders.0.address', 's10@example.test')
        ->where('settings.senders.7.address', 's3@example.test'));
});

test('the senders of another family are not listed', function (): void {
    $rossi = familyWithMailAddress('Rossi', 'anna@example.com', 'rossi.aaaaaa@bollette.familymanager.test');
    ReceivedMail::factory()->create(['family_id' => $rossi->family_id, 'sender' => 'Hera <hera@example.test>', 'sender_address' => 'hera@example.test']);

    $this->actingAs($this->marco)->get(route('mail-settings.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('settings.senders', 0));
});

test('an admin sees the page as editable', function (): void {
    $this->actingAs($this->marco)->get(route('mail-settings.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('settings.can_edit', true));
});

test('without the domain the page says the mail is not active yet', function (): void {
    config(['services.mailgun.inbox_domain' => null]);

    $this->actingAs($this->marco)->get(route('mail-settings.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('settings.address', null));
});

test('an admin chooses who gets the notice and whether it is only for mails with files', function (): void {
    $this->actingAs($this->marco)
        ->put(route('mail-settings.update'), ['notify' => [$this->laura->id], 'attachments_only' => false])
        ->assertRedirect(route('mail-settings.edit'));

    expect(bianchiSettings()->mail_inbox_notify)->toBe([$this->laura->id])
        ->and(bianchiSettings()->mail_inbox_notify_attachments_only)->toBeFalse()
        ->and(insideBianchi(fn () => bianchiSettings()->notifyUsers()->pluck('id')->all()))->toBe([$this->laura->id]);

    $this->get(route('mail-settings.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('settings.accounts.0.chosen', false)
        ->where('settings.accounts.1.chosen', true)
        ->where('settings.attachments_only', false));
});

test('everybody checked is saved as null, so the accounts that join later get the notices too', function (): void {
    bianchiSettings()->update(['mail_inbox_notify' => [$this->laura->id]]);

    $this->actingAs($this->marco)
        ->put(route('mail-settings.update'), ['notify' => [$this->laura->id, $this->marco->id], 'attachments_only' => true])
        ->assertRedirect();

    expect(bianchiSettings()->mail_inbox_notify)->toBeNull();
});

test('nobody checked is saved as an empty list and nobody gets the notice', function (): void {
    $this->actingAs($this->marco)
        ->put(route('mail-settings.update'), ['notify' => [], 'attachments_only' => true])
        ->assertRedirect();

    expect(bianchiSettings()->mail_inbox_notify)->toBe([])
        ->and(bianchiSettings()->notifyUsers())->toHaveCount(0);

    $this->get(route('mail-settings.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('settings.accounts.0.chosen', false)
        ->where('settings.accounts.1.chosen', false));
});

test('an account of another family does not pass', function (): void {
    $rossi = registerFamily('Rossi', 'anna@example.com');

    $this->actingAs($this->marco)
        ->put(route('mail-settings.update'), ['notify' => [$rossi->id], 'attachments_only' => true])
        ->assertSessionHasErrors('notify.0');

    expect(bianchiSettings()->mail_inbox_notify)->toBeNull();
});

test('a member cannot change who gets the notice', function (): void {
    $this->actingAs($this->laura)
        ->put(route('mail-settings.update'), ['notify' => [], 'attachments_only' => false])
        ->assertForbidden();

    expect(bianchiSettings()->mail_inbox_notify)->toBeNull();
});

test('an admin changes the address: the same family name, and the old one receives no more', function (): void {
    $response = $this->actingAs($this->marco)->post(route('mail-settings.address'));

    $response->assertRedirect(route('mail-settings.edit'));
    $new = bianchiSettings()->mail_inbox_recipient;
    expect($new)->toMatch('/^bianchi\.[a-hjkmnp-z2-9]{6}@bollette\.familymanager\.test$/')
        ->and($new)->not->toBe(SETTINGS_ADDRESS)
        ->and(FamilySetting::findByMailRecipient(SETTINGS_ADDRESS))->toBeNull()
        ->and(FamilySetting::findByMailRecipient((string) $new)?->family_id)->toBe($this->marco->family_id);
});

test('a member cannot change the address', function (): void {
    $this->actingAs($this->laura)->post(route('mail-settings.address'))->assertForbidden();

    expect(bianchiSettings()->mail_inbox_recipient)->toBe(SETTINGS_ADDRESS);
});

test('the address is not changed without the domain', function (): void {
    config(['services.mailgun.inbox_domain' => null]);

    $this->actingAs($this->marco)->post(route('mail-settings.address'))->assertNotFound();

    expect(bianchiSettings()->mail_inbox_recipient)->toBe(SETTINGS_ADDRESS);
});

test('the action gives the new address and Mailgun is not called', function (): void {
    Http::preventStrayRequests();

    $address = resolve(RegenerateMailAddress::class)->handle($this->marco->family);

    expect($address)->toBe(bianchiSettings()->mail_inbox_recipient);
});

test('a guest is sent to the sign-in', function (): void {
    auth()->logout();

    $this->get(route('mail-settings.edit'))->assertRedirect(route('login'));
});
