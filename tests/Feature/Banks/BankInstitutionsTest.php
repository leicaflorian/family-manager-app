<?php

declare(strict_types=1);

use App\Contracts\BankFeed;
use App\Services\Banks\BankInstitutions;
use App\Services\Banks\FakeBankFeed;
use App\Support\Banks\BankInstitution;
use Illuminate\Support\Facades\Cache;

test('the bank list read a second time from a cache that serializes still gives banks', function (): void {
    // Redis and the database store serialize, and the app does not let them rebuild classes
    config(['cache.stores.array.serialize' => true]);
    Cache::purge('array');
    Cache::forget('bank-institutions:IT');
    app()->instance(BankFeed::class, new FakeBankFeed);

    $first = resolve(BankInstitutions::class)->all();
    $second = resolve(BankInstitutions::class)->all();

    expect($second)->toHaveCount(1)
        ->and($second[0])->toBeInstanceOf(BankInstitution::class)
        ->and($second[0]->name)->toBe('Banca di prova')
        ->and($second[0]->maxConsentDays)->toBe(180)
        ->and($second)->toEqual($first)
        ->and(resolve(BankInstitutions::class)->find('Banca di prova'))->toBeInstanceOf(BankInstitution::class);
});
