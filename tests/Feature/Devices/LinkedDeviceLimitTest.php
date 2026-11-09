<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\User;
use App\Services\PhoneLinks;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

beforeEach(function (): void {
    $this->bianchi = Family::factory()->create(['name' => 'Bianchi']);
    $this->marco = User::factory()->for($this->bianchi)->create(['name' => 'Marco Bianchi', 'password' => 'secret-password']);
    $this->device = LinkedDevice::factory()->for($this->bianchi)->for($this->marco)
        ->create(['token_hash' => hash('sha256', 'the-secret'), 'last_used_at' => now()]);
});

/** A request as the linked phone makes it: signed in, with the mark in the session. */
function linkedPhone(): TestCase
{
    return test()->actingAs(test()->marco)->withSession(['linked_device_id' => test()->device->id]);
}

test('a linked phone is sent to the scan from the pages of the app', function (string $route): void {
    linkedPhone()->get(route($route))->assertRedirect(route('scan'));
})->with(['dashboard', 'documents.index', 'account.edit', 'upload', 'agenda']);

test('a linked phone answers 403 to any other method outside the scan', function (): void {
    $document = Document::factory()->for($this->bianchi)->create();

    linkedPhone()->post(route('upload.store'))->assertForbidden();
    linkedPhone()->delete(route('documents.destroy', $document))->assertForbidden();
    linkedPhone()->put(route('agenda.filters'))->assertForbidden();
    expect(Document::query()->count())->toBe(1);
});

test('a linked phone opens the scan, the linking pages and can sign out', function (): void {
    linkedPhone()->get(route('scan'))->assertOk();
    linkedPhone()->get(route('connect'))->assertOk();

    linkedPhone()->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

test('a phone with its cookie signs out to the sign-in, where the cookie does not bring it back in', function (): void {
    $this->withCookie(PhoneLinks::COOKIE, 'the-secret')->get(route('scan'))->assertOk();

    $this->withCookie(PhoneLinks::COOKIE, 'the-secret')->post(route('logout'))->assertRedirect(route('login'));
    $this->withCookie(PhoneLinks::COOKIE, 'the-secret')->get(route('login'))->assertOk();
    $this->assertGuest();
});

test('an account without the cookie signs out to the start, as before', function (): void {
    $this->actingAs($this->marco)->post(route('logout'))->assertRedirect('/');
});

test('a request without a route name is not broken by the limit', function (): void {
    linkedPhone()->get('/this/does/not/exist')->assertNotFound();
});

test('the pages outside the web group are not touched', function (): void {
    $this->get('calendar/nothing.ics')->assertNotFound();
});

test('the scan of a linked phone shares only what the scan needs', function (): void {
    linkedPhone()->get(route('scan'))->assertInertia(fn ($page) => $page
        ->component('scan/Scan')
        ->where('auth.user.name', 'Marco Bianchi')
        ->where('family.name', 'Bianchi')
        ->where('locale', 'en')
        ->has('theme')
        ->missing('areas')
        ->missing('unread_notices')
        ->missing('to_sort_count'));
});

test('the pages are told when the phone is linked with a code', function (): void {
    linkedPhone()->get(route('scan'))->assertInertia(fn ($page) => $page->where('linked_phone', true));
});

test('the pages are told when the account entered with the password', function (): void {
    $this->actingAs($this->marco)->get(route('scan'))->assertInertia(fn ($page) => $page->where('linked_phone', false));
});

test('a linked phone signed out can ask for a new password without entering again', function (): void {
    $this->withCookie(PhoneLinks::COOKIE, 'the-secret')->get(route('password.request'))->assertOk();
    $this->assertGuest();

    $this->withCookie(PhoneLinks::COOKIE, 'the-secret')->post(route('password.email'), ['email' => $this->marco->email])->assertRedirect();
    $this->assertGuest();

    $this->withCookie(PhoneLinks::COOKIE, 'the-secret')->get(route('password.reset', ['token' => 'a-token']))->assertOk();
    $this->assertGuest();

    $this->withCookie(PhoneLinks::COOKIE, 'the-secret')->post(route('password.update'), [
        'token' => 'a-token', 'email' => $this->marco->email, 'password' => 'a-new-password-1', 'password_confirmation' => 'a-new-password-1',
    ]);
    $this->assertGuest();
});

test('an account without a linked phone sees the whole app and the shared props', function (): void {
    $this->actingAs($this->marco)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('areas')->has('unread_notices')->has('to_sort_count')->has('family'));
    $this->get(route('documents.index'))->assertOk();
});

test('the cookie sign-in keeps the mark, so the first page is already limited', function (): void {
    $this->withCookie(PhoneLinks::COOKIE, 'the-secret')->get(route('dashboard'))->assertRedirect(route('scan'));

    expect(session('linked_device_id'))->toBe($this->device->id);
});

test('a login with the password lifts the limit', function (): void {
    Session::put('linked_device_id', $this->device->id);
    Event::dispatch(new Login('web', $this->marco, false));
    expect(session()->has('linked_device_id'))->toBeFalse();

    Session::put('linked_device_id', $this->device->id);
    Event::dispatch(new Login('other', $this->marco, false));
    expect(session('linked_device_id'))->toBe($this->device->id);
});

test('signing out and in with the password opens the whole app, and signing out brings the cookie back to the scan', function (): void {
    $this->withCookie(PhoneLinks::COOKIE, 'the-secret')->get(route('scan'))->assertOk();
    $this->post(route('logout'));
    $this->assertGuest();

    $this->post(route('login.store'), ['email' => $this->marco->email, 'password' => 'secret-password'])->assertRedirect();
    expect(session()->has('linked_device_id'))->toBeFalse();
    $this->get(route('dashboard'))->assertOk();

    $this->post(route('logout'));
    $this->withCookie(PhoneLinks::COOKIE, 'the-secret')->get(route('dashboard'))->assertRedirect(route('scan'));
});

test('a linked phone with an email not verified reaches the notice instead of looping', function (): void {
    $this->marco->forceFill(['email_verified_at' => null])->save();

    linkedPhone()->get(route('scan'))->assertRedirect(route('verification.notice'));
    linkedPhone()->get(route('verification.notice'))->assertOk();
});

test('a linked phone does not get the photo of its account', function (): void {
    linkedPhone()->get(route('scan'))->assertInertia(fn ($page) => $page->where('auth.user', ['name' => 'Marco Bianchi']));
});

test('an expired cookie of a phone no longer linked is cleared in the response', function (): void {
    $response = $this->withCookie(PhoneLinks::COOKIE, 'not-a-secret')->get(route('dashboard'));

    $response->assertCookieExpired(PhoneLinks::COOKIE);
});

test('a good cookie is left as it is', function (): void {
    $this->withCookie(PhoneLinks::COOKIE, 'the-secret')->get(route('scan'))->assertCookieMissing(PhoneLinks::COOKIE);
});

test('a phone unlinked from the computer is signed out at its next request', function (): void {
    linkedPhone()->get(route('scan'))->assertOk();
    $this->device->delete();

    linkedPhone()->get(route('scan'))->assertRedirect(route('login'));
    $this->assertGuest();
});
