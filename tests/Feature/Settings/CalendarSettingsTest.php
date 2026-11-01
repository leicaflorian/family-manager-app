<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
});

/**
 * A member of Marco's family.
 */
function calendarMember(User $marco): User
{
    return User::factory()->for($marco->family)->create(['role' => Role::Member]);
}

test('the page says the calendar is off, without an address', function (): void {
    $this->get(route('calendar.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/Calendar')
        ->where('calendar.on', false)
        ->where('calendar.url', null)
        ->where('calendar.can_change', true));
});

test('an admin turns the calendar on in the language of the page', function (): void {
    $this->withHeader('Accept-Language', 'it')->post(route('calendar.store'))->assertRedirect(route('calendar.edit'));

    $family = $this->marco->family->fresh();
    expect($family?->calendar_token_hash)->not->toBeNull()
        ->and($family?->calendar_locale)->toBe('it');

    $this->get(route('calendar.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('calendar.on', true)
        ->where('calendar.url', route('calendar.feed', ['token' => $family?->calendar_token])));
});

test('an admin turns the calendar off, the old address answers 404 and turning it on gives another', function (): void {
    $this->post(route('calendar.store'));
    $old = $this->marco->family->fresh()?->calendar_token;
    $this->get(route('calendar.feed', ['token' => $old]))->assertOk();

    $this->delete(route('calendar.destroy'))->assertRedirect(route('calendar.edit'));

    expect($this->marco->family->fresh()?->calendar_token_hash)->toBeNull();
    $this->get(route('calendar.feed', ['token' => $old]))->assertNotFound();

    $this->post(route('calendar.store'));
    expect($this->marco->family->fresh()?->calendar_token)->not->toBe($old);
});

test('an admin changes the address and the old one stops working', function (): void {
    $this->post(route('calendar.store'));
    $old = $this->marco->family->fresh()?->calendar_token;

    $this->post(route('calendar.reset'))->assertRedirect(route('calendar.edit'));

    $new = $this->marco->family->fresh()?->calendar_token;
    expect($new)->not->toBe($old);
    $this->get(route('calendar.feed', ['token' => $old]))->assertNotFound();
    $this->get(route('calendar.feed', ['token' => $new]))->assertOk();
});

test('changing the address updates the family\'s updated_at', function (): void {
    $this->post(route('calendar.store'));
    DB::table('families')->where('id', $this->marco->family_id)->update(['updated_at' => '2020-01-01 00:00:00']);

    $this->post(route('calendar.reset'));

    expect($this->marco->family->fresh()?->updated_at?->year)->toBeGreaterThan(2020);
});

test('changing the address of a calendar that is off answers 404', function (): void {
    $this->post(route('calendar.reset'))->assertNotFound();
});

test('a member sees the address but cannot turn on, off or change it', function (): void {
    $this->post(route('calendar.store'));
    $token = $this->marco->family->fresh()?->calendar_token;

    $this->actingAs(calendarMember($this->marco));

    $this->get(route('calendar.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('calendar.on', true)
        ->where('calendar.url', route('calendar.feed', ['token' => $token]))
        ->where('calendar.can_change', false));
    $this->post(route('calendar.store'))->assertForbidden();
    $this->delete(route('calendar.destroy'))->assertForbidden();
    $this->post(route('calendar.reset'))->assertForbidden();
    expect($this->marco->family->fresh()?->calendar_token)->toBe($token);
});

test('a secret that cannot be decrypted leaves the page working, with the calendar on and no address', function (): void {
    $this->post(route('calendar.store'));
    DB::table('families')->where('id', $this->marco->family_id)->update(['calendar_token' => 'not-encrypted']);
    $this->actingAs($this->marco->fresh());

    $this->get(route('calendar.edit'))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('calendar.on', true)
        ->where('calendar.url', null));

    $this->post(route('calendar.reset'))->assertRedirect(route('calendar.edit'));
    $this->get(route('calendar.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('calendar.on', true)
        ->where('calendar.url', fn ($url): bool => is_string($url) && $url !== ''));
});

test('a guest is sent to sign in', function (): void {
    auth()->logout();
    $this->get(route('calendar.edit'))->assertRedirect(route('login'));
});
