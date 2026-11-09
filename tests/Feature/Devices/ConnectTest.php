<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\User;
use App\Services\PhoneLinks;
use Carbon\CarbonImmutable;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

const PHONE_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome'));
    RateLimiter::clear('phone-link:wrong:127.0.0.1');
    $this->bianchi = Family::factory()->create(['name' => 'Bianchi']);
    $this->marco = User::factory()->for($this->bianchi)->create(['name' => 'Marco Bianchi']);
    $this->links = resolve(PhoneLinks::class);
});

/** Opens a page with the user agent of an iPhone. */
function phone(): TestCase
{
    return test()->withHeader('User-Agent', PHONE_AGENT);
}

test('the page to write the code opens without signing in', function (): void {
    phone()->get(route('connect'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('connect/Connect')->where('code', null)->where('person', null));
});

test('the link of a valid code shows who it is for, twice, without using it', function (): void {
    $code = $this->links->newCode($this->marco);

    foreach ([1, 2] as $_) {
        phone()->get(route('connect.show', $code))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('connect/Connect')
                ->where('code', $code)->where('person', 'Marco')->where('family', 'Bianchi'));
    }

    expect($this->links->find($code))->not->toBeNull()
        ->and(LinkedDevice::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('the link of a wrong code goes back to the page with a message', function (): void {
    phone()->get(route('connect.show', 'ABCD-EFGH'))
        ->assertRedirect(route('connect'))
        ->assertSessionHasErrors(['code' => 'The code has expired: make a new one on the computer']);

    phone()->get(route('connect.show', 'abc'))
        ->assertSessionHasErrors(['code' => 'This code is not right: check that you wrote it well']);
});

test('the POST uses the code once: the phone is made, the cookie is set and Marco enters', function (): void {
    $code = $this->links->newCode($this->marco);

    $response = phone()->post(route('connect.use', $code));

    $response->assertRedirect(route('connect.linked'));
    $this->assertAuthenticatedAs($this->marco);
    $device = LinkedDevice::query()->withoutGlobalScopes()->sole();
    expect($device->name)->toBe('iPhone of Marco')
        ->and($device->session_id)->toBe(session()->getId())
        ->and(session('linked_device_id'))->toBe($device->id)
        ->and(session('phone_secret'))->toHaveLength(64)
        ->and($device->last_used_at?->equalTo(now()))->toBeTrue();

    // The second try finds nothing
    phone()->post(route('connect.use', $code))->assertSessionHasErrors('code');
    expect(LinkedDevice::query()->withoutGlobalScopes()->count())->toBe(1);
});

test('the cookie is HttpOnly, SameSite=Lax, lasts 400 days and is Secure on https', function (): void {
    $code = $this->links->newCode($this->marco);

    $response = phone()->post('https://localhost'.route('connect.use', $code, false));

    $cookie = $response->getCookie(PhoneLinks::COOKIE, false);
    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getExpiresTime())->toBe(now()->addDays(400)->getTimestamp());
});

test('the cookie is not Secure on plain http, so the phone can try on the home network', function (): void {
    $code = $this->links->newCode($this->marco);

    $response = phone()->post(route('connect.use', $code));

    $cookie = $response->getCookie(PhoneLinks::COOKIE, false);
    expect($cookie->isSecure())->toBeFalse()->and($cookie->isHttpOnly())->toBeTrue();
});

test('the secret in the cookie is the one that finds the phone, and the page gets it once', function (): void {
    $code = $this->links->newCode($this->marco);

    $response = phone()->post(route('connect.use', $code));
    $secret = $response->getCookie(PhoneLinks::COOKIE)->getValue();

    expect(LinkedDevice::findByToken($secret))->not->toBeNull()
        ->and(session('phone_secret'))->toBe($secret);

    phone()->withSession(['phone_secret' => $secret])->actingAs($this->marco)->get(route('connect.linked'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('connect/Linked')
            ->where('name', 'Marco')->where('family', 'Bianchi')->where('secret', $secret));

    // Read once: the session no longer holds it
    phone()->actingAs($this->marco)->get(route('connect.linked'))->assertInertia(fn ($page) => $page->where('secret', null));
});

test('the code of Marco of the Bianchi enters him in the Bianchi even if the phone was in the Rossi', function (): void {
    $rossi = Family::factory()->create(['name' => 'Rossi']);
    $anna = User::factory()->for($rossi)->create(['name' => 'Anna Rossi']);
    $this->actingAs($anna);
    $code = $this->links->newCode($this->marco);
    session()->put('url.intended', '/rossi-page');

    phone()->post(route('connect.use', $code))->assertRedirect(route('connect.linked'));

    $this->assertAuthenticatedAs($this->marco);
    expect(LinkedDevice::query()->withoutGlobalScopes()->sole()->family_id)->toBe($this->bianchi->id)
        ->and(session()->has('url.intended'))->toBeFalse();

    phone()->actingAs($this->marco)->get(route('connect.linked'))
        ->assertInertia(fn ($page) => $page->where('family', 'Bianchi')->where('name', 'Marco'));
});

test('a new code for Marco makes the one before useless', function (): void {
    $first = $this->links->newCode($this->marco);
    $second = $this->links->newCode($this->marco);

    phone()->post(route('connect.use', $first))->assertSessionHasErrors('code');
    phone()->post(route('connect.use', $second))->assertRedirect(route('connect.linked'));
});

test('a code that expired at 19:15 does not work', function (): void {
    $code = $this->links->newCode($this->marco);
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:15', 'Europe/Rome'));

    phone()->post(route('connect.use', $code))->assertSessionHasErrors(['code' => 'The code has expired: make a new one on the computer']);
    $this->assertGuest();
});

test('five wrong codes in ten minutes and then 429, even for the right code', function (): void {
    $code = $this->links->newCode($this->marco);

    foreach (range(1, 5) as $_) {
        phone()->post(route('connect.use', 'ABCD-EFGH'))->assertSessionHasErrors('code');
    }

    // A plain request gets the page, with the message and the status 429
    phone()->post(route('connect.use', 'ABCD-EFGH'))->assertStatus(429)
        ->assertInertia(fn ($page) => $page->component('connect/Connect')->where('blocked', 'Too many attempts: try again in a few minutes'));
    phone()->get(route('connect.show', $code))->assertStatus(429);
    phone()->post(route('connect.use', $code))->assertStatus(429);
    phone()->post(route('connect.resume'), ['secret' => 'nope'])->assertStatus(429);
    expect(LinkedDevice::query()->withoutGlobalScopes()->count())->toBe(0);

    // An Inertia visit goes back to the page with the message under the field
    phone()->withHeader('X-Inertia', 'true')->post(route('connect.use', $code))
        ->assertRedirect(route('connect'))
        ->assertSessionHasErrors(['code' => 'Too many attempts: try again in a few minutes']);

    // Ten minutes later it is allowed again
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:14:30', 'Europe/Rome'));
    $fresh = $this->links->newCode($this->marco);
    phone()->post(route('connect.use', $fresh))->assertRedirect(route('connect.linked'));
});

test('connect/resume with the secret enters the phone and sets the cookie, with a wrong one it does not', function (): void {
    $device = LinkedDevice::factory()->for($this->bianchi)->for($this->marco)->create(['token_hash' => hash('sha256', 'the-secret'), 'last_used_at' => now()->subDays(3)]);

    phone()->post(route('connect.resume'), ['secret' => 'wrong'])->assertSessionHasErrors('secret');
    $this->assertGuest();

    $response = phone()->post(route('connect.resume'), ['secret' => 'the-secret']);

    $response->assertRedirect(route('scan'));
    $this->assertAuthenticatedAs($this->marco);
    expect($response->getCookie(PhoneLinks::COOKIE)->getValue())->toBe('the-secret')
        ->and($device->refresh()->last_used_at?->equalTo(now()))->toBeTrue()
        ->and(session('linked_device_id'))->toBe($device->id);
});

test('connect/resume asks for the secret', function (): void {
    phone()->post(route('connect.resume'))->assertSessionHasErrors('secret');
});

test('with the session over, the cookie brings the phone back in and writes last_used_at', function (): void {
    $device = LinkedDevice::factory()->for($this->bianchi)->for($this->marco)
        ->create(['token_hash' => hash('sha256', 'the-secret'), 'last_used_at' => now()->subDays(3), 'session_id' => 'old']);

    $this->withCookie(PhoneLinks::COOKIE, 'the-secret')->get(route('scan'))->assertOk();

    $this->assertAuthenticatedAs($this->marco);
    $device->refresh();
    expect($device->last_used_at?->equalTo(now()))->toBeTrue()
        ->and($device->session_id)->not->toBe('old')
        ->and(session('linked_device_id'))->toBe($device->id);
});

test('a cookie with a wrong secret or an expired phone enters nobody', function (): void {
    LinkedDevice::factory()->for($this->bianchi)->for($this->marco)
        ->create(['token_hash' => hash('sha256', 'old'), 'last_used_at' => now()->subDays(181)]);

    $this->withCookie(PhoneLinks::COOKIE, 'nope')->get(route('scan'))->assertRedirect(route('login'));
    $this->withCookie(PhoneLinks::COOKIE, 'old')->get(route('scan'))->assertRedirect(route('login'));
});

test('someone already signed in is not replaced by the cookie', function (): void {
    $laura = User::factory()->for($this->bianchi)->create();
    LinkedDevice::factory()->for($this->bianchi)->for($this->marco)->create(['token_hash' => hash('sha256', 's')]);

    $this->actingAs($laura)->withCookie(PhoneLinks::COOKIE, 's')->get(route('scan'))->assertOk();

    $this->assertAuthenticatedAs($laura);
});

test('the scan page is for who signed in', function (): void {
    $this->get(route('scan'))->assertRedirect(route('login'));
    $this->actingAs($this->marco)->get(route('scan'))->assertInertia(fn ($page) => $page->component('scan/Scan'));
});

test('the phone page without a session or with the secret in the address asks nothing of the server', function (): void {
    $this->get(route('connect.linked'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('connect/Linked')->where('name', null)->where('family', null)->where('secret', null));
});

test('wrong codes are counted by the address of the proxy only when the proxy is trusted', function (): void {
    TrustProxies::at(['10.0.0.1']);
    RateLimiter::clear('phone-link:wrong:203.0.113.9');

    phone()->withServerVariables(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'])
        ->post(route('connect.use', 'ABCD-EFGH'));
    $trusted = RateLimiter::attempts('phone-link:wrong:203.0.113.9');

    phone()->withServerVariables(['REMOTE_ADDR' => '10.9.9.9', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'])
        ->post(route('connect.use', 'ABCD-EFGH'));

    TrustProxies::flushState();
    expect($trusted)->toBe(1)
        ->and(RateLimiter::attempts('phone-link:wrong:203.0.113.9'))->toBe(1)
        ->and(RateLimiter::attempts('phone-link:wrong:10.9.9.9'))->toBe(1);
});
