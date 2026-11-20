<?php

declare(strict_types=1);

use App\Exceptions\Banks\BankConsentEnded;
use App\Exceptions\Banks\BankFeedFailed;
use App\Exceptions\Banks\BankLimitReached;
use App\Services\Banks\EnableBankingFeed;
use App\Support\Banks\BankInstitution;
use App\Support\Banks\BankVisitor;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/** Reads a fixture of the provider as an array. */
function bankFeedFixture(string $name): array
{
    return json_decode((string) file_get_contents(base_path("tests/Fixtures/enable-banking/{$name}.json")), true);
}

/** A feed that signs with a key made for the test and talks to a fixed address. */
function bankFeedNew(): EnableBankingFeed
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $private);
    $path = tempnam(sys_get_temp_dir(), 'eb-key');
    file_put_contents($path, $private);
    config(['bank_feed.enable_banking.url' => 'https://api.test', 'bank_feed.enable_banking.app_id' => 'app-1', 'bank_feed.enable_banking.key_path' => $path]);
    Cache::flush();

    return resolve(EnableBankingFeed::class);
}

beforeEach(fn () => Http::preventStrayRequests());

it('lists the personal banks of Italy', function (): void {
    Http::fake(['api.test/aspsps*' => Http::response(bankFeedFixture('aspsps'))]);

    $banks = bankFeedNew()->institutions('IT');

    expect($banks)->toHaveCount(2)
        ->and($banks[0])->toEqual(new BankInstitution('Intesa Sanpaolo', 'IT', 'https://enablebanking.com/brands/IT/IntesaSanpaolo/', 180))
        ->and($banks[1]->name)->toBe('Banca di Prova')
        ->and($banks[1]->maxConsentDays)->toBe(90);
    Http::assertSent(fn (Request $r): bool => str_starts_with($r->header('Authorization')[0], 'Bearer eyJ') && $r['country'] === 'IT' && $r->method() === 'GET');
});

it('starts a consent and returns the address of the bank', function (): void {
    Http::fake(['api.test/auth' => Http::response(bankFeedFixture('auth'))]);

    $url = bankFeedNew()->start(new BankInstitution('Intesa Sanpaolo', 'IT', null, 180), 'state-1', 'https://my.test/back', CarbonImmutable::parse('2027-05-19 10:00:00', 'UTC'));

    expect($url)->toBe('https://auth.enablebanking.com/ais/start?sessionid=7d1f');
    Http::assertSent(fn (Request $r): bool => $r->data() === [
        'access' => ['valid_until' => '2027-05-19T10:00:00.000000+00:00'],
        'aspsp' => ['name' => 'Intesa Sanpaolo', 'country' => 'IT'],
        'state' => 'state-1',
        'redirect_url' => 'https://my.test/back',
        'psu_type' => 'personal',
    ]);
});

it('turns the code into a session with its accounts', function (): void {
    Http::fake(['api.test/sessions' => Http::response(bankFeedFixture('session'))]);

    $session = bankFeedNew()->complete('the-code');

    expect($session->id)->toBe('5f3c1b3a-3a1e-4c7e-8d52-0d1b2f6a9e10')
        ->and($session->institution)->toBe('Intesa Sanpaolo')
        ->and($session->validUntil->toDateString())->toBe('2027-05-19')
        ->and($session->accounts)->toHaveCount(2)
        ->and($session->accounts[0]->uid)->toBe('a1b2c3d4-0000-4000-8000-000000000001')
        ->and($session->accounts[0]->key)->toBe('hash-conto-corrente')
        ->and($session->accounts[0]->name)->toBe('Conto Arancio')
        ->and($session->accounts[0]->lastFour)->toBe('4821')
        ->and($session->accounts[1]->name)->toBe('Carta prepagata')
        ->and($session->accounts[1]->lastFour)->toBeNull();
    Http::assertSent(fn (Request $r): bool => $r->data() === ['code' => 'the-code']);
});

it('reads every page of the transactions', function (): void {
    Http::fake(['api.test/accounts/acc-1/transactions*' => Http::sequence()
        ->push(bankFeedFixture('transactions-page1'))
        ->push(bankFeedFixture('transactions-page-empty'))
        ->push(bankFeedFixture('transactions-page2')),
    ]);

    $entries = bankFeedNew()->transactions('session', 'acc-1', CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-20'));

    expect($entries)->toHaveCount(3);
    $sent = Http::recorded();
    expect($sent)->toHaveCount(3)
        ->and($sent[0][0]['date_from'])->toBe('2026-10-01')
        ->and($sent[0][0]['date_to'])->toBe('2026-11-20')
        ->and($sent[0][0]->url())->not->toContain('continuation_key')
        ->and($sent[1][0]['continuation_key'])->toBe('page-2')
        ->and($sent[2][0]['continuation_key'])->toBe('page-3');
});

it('makes a debit negative and a credit positive', function (): void {
    Http::fake(['api.test/accounts/acc-1/transactions*' => Http::sequence()
        ->push(['transactions' => bankFeedFixture('transactions-page2')['transactions']]),
    ]);

    [$credit, $pending] = bankFeedNew()->transactions('s', 'acc-1', CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-20'));

    expect($credit->amount)->toBe(1850.0)
        ->and($credit->providerId)->toBe('tx-2')
        ->and($credit->counterparty)->toBe('ACME SRL')
        ->and($credit->text)->toBe('ACME SRL STIPENDIO NOVEMBRE')
        ->and($credit->booked)->toBeTrue()
        ->and($credit->bookedOn?->toDateString())->toBe('2026-11-10')
        ->and($pending->amount)->toBe(-12.5)
        ->and($pending->booked)->toBeFalse()
        ->and($pending->bookedOn)->toBeNull()
        ->and($pending->counterparty)->toBe('EDISON ENERGIA SPA')
        ->and($pending->text)->toBe('EDISON ENERGIA SPA');
});

it('reads a debit with the creditor and the reason', function (): void {
    Http::fake(['api.test/accounts/acc-1/transactions*' => Http::response(['transactions' => bankFeedFixture('transactions-page1')['transactions']])]);

    [$debit] = bankFeedNew()->transactions('s', 'acc-1', CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-20'));

    expect($debit->amount)->toBe(-84.2)
        ->and($debit->providerId)->toBe('ref-1')
        ->and($debit->valueOn?->toDateString())->toBe('2026-11-18')
        ->and($debit->text)->toBe('EDISON ENERGIA SPA SDD FATTURA 123 LUCE OTTOBRE');
});

it('sends the visitor headers only with a visitor', function (): void {
    Http::fake(['api.test/accounts/acc-1/transactions*' => Http::response(['transactions' => []])]);
    $feed = bankFeedNew();
    $from = CarbonImmutable::parse('2026-10-01');
    $to = CarbonImmutable::parse('2026-11-20');

    $feed->transactions('s', 'acc-1', $from, $to);
    $feed->transactions('s', 'acc-1', $from, $to, new BankVisitor('203.0.113.9', 'Safari/18'));

    $sent = Http::recorded();
    expect($sent[0][0]->hasHeader('Psu-Ip-Address'))->toBeFalse()
        ->and($sent[0][0]->hasHeader('Psu-User-Agent'))->toBeFalse()
        ->and($sent[1][0]->header('Psu-Ip-Address'))->toBe(['203.0.113.9'])
        ->and($sent[1][0]->header('Psu-User-Agent'))->toBe(['Safari/18']);
});

it('treats an expired session as a consent ended', function (string $fixture, int $status): void {
    Http::fake(['api.test/*' => Http::response(bankFeedFixture($fixture), $status)]);

    bankFeedNew()->transactions('s', 'acc-1', CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-20'));
})->with([['error-expired', 401], ['error-closed', 422]])->throws(BankConsentEnded::class);

it('treats a forbidden answer as a consent ended', function (): void {
    Http::fake(['api.test/*' => Http::response(['message' => 'no', 'code' => 403], 403)]);

    bankFeedNew()->transactions('s', 'acc-1', CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-20'));
})->throws(BankConsentEnded::class);

it('treats a rate limit as a limit reached', function (int $status): void {
    Http::fake(['api.test/*' => Http::response(bankFeedFixture('error-rate-limit'), $status)]);

    bankFeedNew()->transactions('s', 'acc-1', CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-20'));
})->with([429, 500])->throws(BankLimitReached::class);

it('treats a 500 as a failure with the message of the provider', function (): void {
    Http::fake(['api.test/*' => Http::response(bankFeedFixture('error-server'), 500)]);

    try {
        bankFeedNew()->institutions('IT');
    } catch (BankFeedFailed $e) {
        expect($e)->not->toBeInstanceOf(BankConsentEnded::class)->not->toBeInstanceOf(BankLimitReached::class)
            ->and($e->getMessage())->toContain('Something broke at the bank');

        return;
    }

    $this->fail('No exception');
});

it('revokes a session', function (): void {
    Http::fake(['api.test/sessions/sess-1' => Http::response(['message' => 'OK'])]);

    bankFeedNew()->revoke('sess-1');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'DELETE' && str_ends_with($r->url(), '/sessions/sess-1'));
});

it('is active only with an application id and a readable key', function (): void {
    config(['bank_feed.enable_banking.app_id' => null, 'bank_feed.enable_banking.key_path' => null]);
    expect(resolve(EnableBankingFeed::class)->isActive())->toBeFalse();

    $path = tempnam(sys_get_temp_dir(), 'eb-key');
    config(['bank_feed.enable_banking.app_id' => 'app', 'bank_feed.enable_banking.key_path' => $path]);
    expect(resolve(EnableBankingFeed::class)->isActive())->toBeTrue();

    config(['bank_feed.enable_banking.key_path' => $path.'-missing']);
    expect(resolve(EnableBankingFeed::class)->isActive())->toBeFalse();
    File::delete($path);
});
