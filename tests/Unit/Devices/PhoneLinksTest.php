<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\Person;
use App\Models\User;
use App\Services\PhoneLinks;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome'));
    $this->family = Family::factory()->create();
    $this->marco = User::factory()->for($this->family)->create(['name' => 'Marco Bianchi']);
    $this->links = resolve(PhoneLinks::class);
});

/** A request from an iPhone. */
function iphoneRequest(): Request
{
    return Request::create('/connect/x', 'POST', server: ['HTTP_USER_AGENT' => IPHONE_AGENT]);
}

test('a code has 8 characters of the alphabet in two groups of four', function (): void {
    $code = $this->links->newCode($this->marco);

    expect($code)->toMatch('/^['.PhoneLinks::ALPHABET.']{4}-['.PhoneLinks::ALPHABET.']{4}$/')
        ->and(PhoneLinks::CODE_MINUTES)->toBe(10)
        ->and(PhoneLinks::MAX_WRONG)->toBe(5)
        ->and(PhoneLinks::COOKIE)->toBe('fm_device')
        ->and(PhoneLinks::COOKIE_DAYS)->toBe(400);
});

test('the cache holds the hash of the code, not the code', function (): void {
    $code = $this->links->newCode($this->marco);

    expect(Cache::get('phone-link:'.hash('sha256', str_replace('-', '', $code))))
        ->toBe(['user_id' => $this->marco->id, 'family_id' => $this->family->id])
        ->and(Cache::get('phone-link:'.hash('sha256', $code)))->toBeNull();
});

test('a code is found written in lower case, with spaces or without the dash', function (): void {
    $code = $this->links->newCode($this->marco);
    $plain = str_replace('-', '', $code);

    expect($this->links->find(strtolower($code)))->toBe(['user_id' => $this->marco->id, 'family_id' => $this->family->id])
        ->and($this->links->find(substr($plain, 0, 4).' '.substr($plain, 4)))->not->toBeNull()
        ->and($this->links->find($plain))->not->toBeNull()
        ->and($this->links->find('ABCD-EFGH'))->toBeNull()
        ->and($this->links->find('short'))->toBeNull();
});

test('a code is valid at 19:13 and not at 19:15', function (): void {
    $code = $this->links->newCode($this->marco);

    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:13', 'Europe/Rome'));
    expect($this->links->find($code))->not->toBeNull();

    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:14:01', 'Europe/Rome'));
    expect($this->links->find($code))->toBeNull()
        ->and($this->links->use($code, iphoneRequest()))->toBeNull();
});

test('finding a code does not use it', function (): void {
    $code = $this->links->newCode($this->marco);

    $this->links->find($code);
    $this->links->find($code);

    expect($this->links->find($code))->not->toBeNull()
        ->and(LinkedDevice::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('a code is used once: the phone is made with its name and a secret that is not saved', function (): void {
    app()->setLocale('it');
    $code = $this->links->newCode($this->marco);

    $linked = $this->links->use($code, iphoneRequest());

    expect($linked)->not->toBeNull()
        ->and($linked['secret'])->toHaveLength(64)
        ->and($linked['device']->name)->toBe('iPhone di Marco')
        ->and($linked['device']->user_id)->toBe($this->marco->id)
        ->and($linked['device']->family_id)->toBe($this->family->id)
        ->and($linked['device']->token_hash)->toBe(hash('sha256', $linked['secret']))
        ->and($this->links->use($code, iphoneRequest()))->toBeNull()
        ->and($this->links->find($code))->toBeNull()
        ->and(LinkedDevice::findByToken($linked['secret'])?->is($linked['device']))->toBeTrue();
});

test('the name uses the first name of the person tied to the account', function (): void {
    app()->setLocale('it');
    $person = Person::factory()->for($this->family)->create(['first_name' => 'Marcello', 'user_id' => $this->marco->id]);
    $code = $this->links->newCode($this->marco);

    expect($this->links->use($code, iphoneRequest())['device']->name)->toBe('iPhone di '.$person->first_name);
});

test('a new code for the account cancels the one before and leaves the others', function (): void {
    $laura = User::factory()->for($this->family)->create();
    $first = $this->links->newCode($this->marco);
    $lauras = $this->links->newCode($laura);

    $second = $this->links->newCode($this->marco);

    expect($this->links->find($first))->toBeNull()
        ->and($this->links->find($second))->not->toBeNull()
        ->and($this->links->find($lauras))->not->toBeNull();
});
