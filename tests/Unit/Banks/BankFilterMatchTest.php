<?php

declare(strict_types=1);

use App\Models\BankTransaction;
use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankFilterMatch;

/**
 * A transaction in memory (no database: an account that is not linked to a bank reads every account).
 *
 * @param  array<string, mixed>  $attributes
 */
function bankFilterMatchTransaction(string $text, string $amount = '-84.20', array $attributes = []): BankTransaction
{
    return (new BankTransaction)->forceFill([
        'text' => $text,
        'amount' => $amount,
        'financial_account_id' => 7,
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $values
 */
function bankFilterMatchCriteria(array $values = []): BankFilterCriteria
{
    return BankFilterCriteria::fromArray(['contains' => ['edison'], 'direction' => 'expense', ...$values]);
}

test('takes a text with every word in any order, whatever the case and the accents', function (): void {
    $criteria = bankFilterMatchCriteria(['contains' => ['energia', 'EDISON']]);

    expect(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Addebito SDD Edison Energià SpA')))->toBeTrue()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('ENERGIA pulita di Edison')))->toBeTrue()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Addebito SDD Edison SpA')))->toBeFalse();
});

test('a phrase must be in that order', function (): void {
    $criteria = bankFilterMatchCriteria(['contains' => ['edison energia']]);

    expect(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('SDD Edison  Energia SpA')))->toBeTrue()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('SDD Energia Edison SpA')))->toBeFalse();
});

test('a word of Non contiene refuses', function (): void {
    $criteria = bankFilterMatchCriteria(['excludes' => ['storno']]);

    expect(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Edison fattura')))->toBeTrue()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Edison STORNO fattura')))->toBeFalse();
});

test('refuses the other direction and an amount out of the range', function (): void {
    $criteria = bankFilterMatchCriteria(['amountMin' => '50.00', 'amountMax' => '100.00']);

    expect(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Edison')))->toBeTrue()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Edison', '84.20')))->toBeFalse()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Edison', '-49.99')))->toBeFalse()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Edison', '-100.01')))->toBeFalse()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Edison', '-100.00')))->toBeTrue()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Edison', '-50.00')))->toBeTrue();
});

test('a filter without words takes nothing', function (): void {
    $criteria = bankFilterMatchCriteria(['contains' => []]);

    expect($criteria->isSafe())->toBeFalse()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Anything at all')))->toBeFalse();
});

test('nor one with only two-letter words', function (): void {
    $criteria = bankFilterMatchCriteria(['contains' => ['sd', 'a', '  ']]);

    expect($criteria->isSafe())->toBeFalse()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('SDD a casa')))->toBeFalse();
});

test('an advanced filter runs its pattern', function (): void {
    $criteria = bankFilterMatchCriteria(['contains' => [], 'pattern' => 'sdd.*edison']);

    expect($criteria->isSafe())->toBeTrue()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('SDD Edison Energia')))->toBeTrue()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Edison SDD')))->toBeFalse();
});

test('a pattern that matches the empty text takes nothing', function (string $pattern): void {
    $criteria = bankFilterMatchCriteria(['contains' => ['edison'], 'pattern' => $pattern]);

    expect($criteria->isSafe())->toBeFalse()
        ->and(BankFilterMatch::matches($criteria, bankFilterMatchTransaction('Edison')))->toBeFalse();
})->with(['everything' => '.*', 'optional' => 'a?', 'broken' => '(', 'slow' => '(a+)+$']);

test('hits gives the words found', function (): void {
    $criteria = bankFilterMatchCriteria(['contains' => ['edison', 'energia elettrica', 'gas']]);

    expect(BankFilterMatch::hits($criteria, 'SDD Edison Energià Elettrica SpA'))->toBe(['EDISON', 'ENERGIA ELETTRICA'])
        ->and(BankFilterMatch::hits($criteria, 'Niente'))->toBe([]);
});

test('hits of an unsafe filter is empty', function (): void {
    expect(BankFilterMatch::hits(bankFilterMatchCriteria(['contains' => ['ab']]), 'AB cd'))->toBe([]);
});
