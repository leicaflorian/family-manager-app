<?php

declare(strict_types=1);

use App\Contracts\BankFeed;
use App\Exceptions\Banks\BankFeedFailed;
use App\Models\Family;
use App\Services\Banks\BankFeedAvailability;
use App\Services\Banks\EnableBankingFeed;
use App\Services\Banks\FakeBankFeed;
use App\Services\Banks\OffBankFeed;
use App\Support\Banks\BankEntry;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;

it('binds the feed of the provider in the config', function (string $provider, string $class): void {
    config(['bank_feed.provider' => $provider]);

    expect(resolve(BankFeed::class))->toBeInstanceOf($class);
})->with([
    ['enable_banking', EnableBankingFeed::class],
    ['fake', FakeBankFeed::class],
    ['', OffBankFeed::class],
]);

it('never answers in production', function (): void {
    config(['bank_feed.provider' => 'fake']);
    app()->detectEnvironment(fn (): string => 'production');

    expect(resolve(BankFeed::class))->toBeInstanceOf(OffBankFeed::class);
});

it('is off with no provider and throws on every call', function (): void {
    $feed = new OffBankFeed;

    expect($feed->isActive())->toBeFalse();
    $feed->institutions('IT');
})->throws(LogicException::class);

it('is available only when the feed is active and the family has it on', function (): void {
    $family = Family::factory()->create(['bank_feed_enabled' => true]);

    CurrentFamily::using($family->id, function () use ($family): void {
        config(['bank_feed.provider' => '']);
        expect(BankFeedAvailability::on())->toBeFalse();

        config(['bank_feed.provider' => 'fake']);
        expect(BankFeedAvailability::on())->toBeTrue();

        $family->forceFill(['bank_feed_enabled' => false])->save();
        expect(BankFeedAvailability::on())->toBeFalse();
    });
});

it('has one bank and sends the person straight back', function (): void {
    $feed = new FakeBankFeed;

    $banks = $feed->institutions('IT');
    $url = $feed->start($banks[0], 'st', 'https://my.test/back', CarbonImmutable::now());
    $session = $feed->complete('fake');

    expect($banks)->toHaveCount(1)
        ->and($banks[0]->name)->toBe('Banca di prova')
        ->and($url)->toBe('https://my.test/back?code=fake&state=st')
        ->and($session->accounts[0]->lastFour)->toBe('4821');
});

it('gives the transactions between two dates counted back from today', function (): void {
    $feed = new FakeBankFeed;
    $today = CarbonImmutable::now('Europe/Rome')->startOfDay();

    $all = $feed->transactions('s', 'u', $today->subDays(90), $today);
    $recent = $feed->transactions('s', 'u', $today->subDays(7), $today);

    expect($all)->toHaveCount(12)
        ->and(count($recent))->toBeLessThan(12)->toBeGreaterThan(0)
        ->and(collect($all)->contains(fn (BankEntry $e): bool => ! $e->booked))->toBeTrue()
        ->and($feed->calls())->toBe(2);
});

it('gives what the test says and fails as the test says', function (): void {
    $entry = new BankEntry('a', null, null, -1.0, 'EUR', 'x', null, true);
    $feed = (new FakeBankFeed)->gives([$entry]);

    expect($feed->transactions('s', 'u', CarbonImmutable::now(), CarbonImmutable::now()))->toBe([$entry]);

    $feed->failsWith(new BankFeedFailed('down'));
    $feed->transactions('s', 'u', CarbonImmutable::now(), CarbonImmutable::now());
})->throws(BankFeedFailed::class, 'down');
