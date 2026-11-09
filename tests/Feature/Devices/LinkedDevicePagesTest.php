<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\LinkedDevice;
use App\Models\User;
use App\Services\PhoneLinks;
use App\Support\QrCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome'));
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
});

/** Asks for the panel the way it does: JSON. */
function askDevices(string $query = ''): TestResponse
{
    return test()->getJson(route('devices.index').$query);
}

test('opening the panel makes a code and its QR, with the phones of the account', function (): void {
    LinkedDevice::factory()->for($this->marco->family)->for($this->marco)->create([
        'name' => 'iPhone di Marco',
        'last_used_at' => now()->subDays(3),
        'last_upload_at' => now()->subDay(),
    ]);

    $response = askDevices()->assertOk();
    $code = $response->json('code');

    expect($code)->toMatch('/^[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/')
        ->and($response->json('qr'))->toStartWith('<?xml')->toContain('<svg')
        ->and($response->json('address'))->toBe(preg_replace('#^https?://#', '', route('connect')))
        ->and($response->json('expires_at'))->toBe(now()->addMinutes(10)->toIso8601String())
        ->and(resolve(PhoneLinks::class)->find($code))->not->toBeNull();

    $response->assertJsonPath('devices.0.name', 'iPhone di Marco')
        ->assertJsonPath('devices.0.linked_on', '2026-10-25')
        ->assertJsonPath('devices.0.last_upload_on', '2026-10-24')
        ->assertJsonPath('devices.0.linked_now', false);
});

test('the QR draws the link of the code', function (): void {
    $response = askDevices();

    expect($response->json('qr'))->toBe(QrCode::svg(route('connect.show', ['code' => $response->json('code')])))
        ->and($response->json('person'))->toBe('Marco')
        ->and($response->json('family'))->toBe($this->marco->family->name);
});

test('a reload of the phones does not make a new code', function (): void {
    $first = askDevices()->json('code');

    $again = askDevices('?only=devices')->assertOk();

    expect($again->json())->toHaveKey('devices')->not->toHaveKeys(['code', 'qr'])
        ->and(resolve(PhoneLinks::class)->find($first))->not->toBeNull();
});

test('a phone that entered less than a minute ago is linked now', function (): void {
    LinkedDevice::factory()->for($this->marco->family)->for($this->marco)->create(['last_used_at' => now()->subSeconds(20)]);
    LinkedDevice::factory()->for($this->marco->family)->for($this->marco)->create(['last_used_at' => now()->subMinutes(5)]);

    $linked = collect(askDevices('?only=devices')->json('devices'))->pluck('linked_now')->all();

    expect($linked)->toEqualCanonicalizing([true, false]);
});

test('the list has only my phones', function (): void {
    $mine = LinkedDevice::factory()->for($this->marco->family)->for($this->marco)->create(['name' => 'Mine']);
    $laura = User::factory()->for($this->marco->family)->create();
    LinkedDevice::factory()->for($this->marco->family)->for($laura)->create(['name' => 'Laura']);
    LinkedDevice::factory()->create(['name' => 'Elsewhere']);

    expect(collect(askDevices()->json('devices'))->pluck('id')->all())->toBe([$mine->id]);
});

test('a member links a phone of their own', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);
    $this->actingAs($member);

    $code = askDevices()->assertOk()->json('code');

    expect(resolve(PhoneLinks::class)->find($code))->toMatchArray(['user_id' => $member->id]);
});

test('forgetting a phone deletes it and the session of the phone', function (): void {
    $phone = LinkedDevice::factory()->for($this->marco->family)->for($this->marco)->create(['session_id' => 'phone-session']);
    DB::table('sessions')->insert(['id' => 'phone-session', 'payload' => '', 'last_activity' => time()]);

    $this->delete(route('devices.destroy', $phone))->assertRedirect();

    expect(LinkedDevice::query()->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'phone-session')->exists())->toBeFalse();
});

test('the phone of another person or another family answers 404, also with a body not valid', function (): void {
    $laura = User::factory()->for($this->marco->family)->create();
    $hers = LinkedDevice::factory()->for($this->marco->family)->for($laura)->create();
    $foreign = LinkedDevice::factory()->create();

    $this->delete(route('devices.destroy', $hers), ['junk' => ['x']])->assertNotFound();
    $this->delete(route('devices.destroy', $foreign), ['junk' => ['x']])->assertNotFound();
    $this->deleteJson(route('devices.destroy', 999999))->assertNotFound();

    expect(LinkedDevice::query()->withoutGlobalScopes()->count())->toBe(2);
});

test('only whoever signed in reaches the panel', function (): void {
    auth()->logout();

    $this->getJson(route('devices.index'))->assertUnauthorized();
});

test('my account has my phones', function (): void {
    LinkedDevice::factory()->for($this->marco->family)->for($this->marco)->create(['name' => 'iPhone di Marco']);
    LinkedDevice::factory()->create(['name' => 'Elsewhere']);

    $this->get(route('account.edit'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/Account')
        ->has('devices', 1)
        ->where('devices.0.name', 'iPhone di Marco'));
});
