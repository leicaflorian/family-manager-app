<?php

declare(strict_types=1);

use App\Models\BankTransaction;
use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankFilterMatch;
use App\Support\Banks\RefineBankFilter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A transaction in memory, on account 7, as the bank gives it.
 */
function refineBankFilterRow(string $text, string $amount = '-45.00'): BankTransaction
{
    return (new BankTransaction)->forceFill(['text' => $text, 'amount' => $amount, 'financial_account_id' => 7]);
}

/**
 * The movements of the group *Eni (POS)* that stay, and the car wash that is taken out.
 *
 * @return array{0: Collection<int, BankTransaction>, 1: BankTransaction}
 */
function refineBankFilterEni(): array
{
    return [
        new Collection([
            refineBankFilterRow('PAGAMENTO POS ENI STATION VIA EMILIA LEVANTE BOLOGNA CARTA *1234', '-45.00'),
            refineBankFilterRow('PAGAMENTO POS ENI STATION VIA STALINGRADO BOLOGNA CARTA *1234', '-38.50'),
            refineBankFilterRow('PAGAMENTO POS ENI STATION VIA EMILIA LEVANTE BOLOGNA CARTA *1234', '-60.00'),
        ]),
        refineBankFilterRow('PAGAMENTO POS ENI LAVAGGIO SELF MODENA CARTA *1234', '-12.00'),
    ];
}

/**
 * The movements of a bar that stay, and one of the same bar in another town, with the same words otherwise.
 *
 * @return array{0: Collection<int, BankTransaction>, 1: BankTransaction}
 */
function refineBankFilterBar(): array
{
    return [
        new Collection([
            refineBankFilterRow('BAR ROSSI CAFFE MILANO', '-3.00'),
            refineBankFilterRow('BAR ROSSI CAFFE TORINO', '-4.00'),
        ]),
        refineBankFilterRow('BAR ROSSI MILANO TORINO', '-3.50'),
    ];
}

/**
 * The same text every time, with one amount far bigger than the others.
 *
 * @return array{0: Collection<int, BankTransaction>, 1: BankTransaction}
 */
function refineBankFilterAmounts(): array
{
    return [
        new Collection([
            refineBankFilterRow('ADDEBITO SDD EDISON ENERGIA', '-30.00'),
            refineBankFilterRow('ADDEBITO SDD EDISON ENERGIA', '-42.10'),
            refineBankFilterRow('ADDEBITO SDD EDISON ENERGIA', '-50.00'),
        ]),
        refineBankFilterRow('ADDEBITO SDD EDISON ENERGIA', '-120.00'),
    ];
}

test('the word only the dropped one has goes to Non contiene', function (): void {
    [$keep, $drop] = refineBankFilterEni();
    $criteria = BankFilterCriteria::fromArray(['contains' => ['ENI', 'POS'], 'accountId' => 7]);
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $refined = RefineBankFilter::without($criteria, $keep, $drop);

    // Pure: it never asks the database, the account of the filter included
    expect($queries)->toBe(0);

    expect($refined)->not->toBeNull()
        ->and($refined['change'])->toBe('excludes')
        ->and($refined['value'])->toBe('LAVAGGIO')
        ->and($refined['criteria']->excludes)->toBe(['LAVAGGIO'])
        ->and($refined['criteria']->contains)->toBe(['ENI', 'POS'])
        ->and($refined['criteria']->accountId)->toBe(7);
});

test('else a word all the others share goes to Contiene', function (): void {
    [$keep, $drop] = refineBankFilterBar();
    $criteria = BankFilterCriteria::fromArray(['contains' => ['ROSSI']]);

    $refined = RefineBankFilter::without($criteria, $keep, $drop);

    expect($refined)->not->toBeNull()
        ->and($refined['change'])->toBe('contains')
        ->and($refined['value'])->toBe('CAFFE')
        ->and($refined['criteria']->contains)->toBe(['ROSSI', 'CAFFE'])
        ->and($refined['criteria']->excludes)->toBe([]);
});

test('else an amount range that leaves only it out', function (): void {
    [$keep, $drop] = refineBankFilterAmounts();
    $criteria = BankFilterCriteria::fromArray(['contains' => ['EDISON']]);

    $refined = RefineBankFilter::without($criteria, $keep, $drop);

    expect($refined)->not->toBeNull()
        ->and($refined['change'])->toBe('amount')
        ->and($refined['value'])->toBe('50.00')
        ->and($refined['criteria']->amountMin)->toBeNull()
        ->and($refined['criteria']->amountMax)->toBe('50.00');

    // One smaller than all the others: the range starts from the smallest kept
    $small = refineBankFilterRow('ADDEBITO SDD EDISON ENERGIA', '-5.00');
    $refined = RefineBankFilter::without($criteria, $keep, $small);

    expect($refined['change'])->toBe('amount')
        ->and($refined['criteria']->amountMin)->toBe('30.00')
        ->and($refined['criteria']->amountMax)->toBeNull();
});

test('null when the dropped one is like the others', function (): void {
    [$keep] = refineBankFilterAmounts();
    $criteria = BankFilterCriteria::fromArray(['contains' => ['EDISON']]);

    expect(RefineBankFilter::without($criteria, $keep, refineBankFilterRow('ADDEBITO SDD EDISON ENERGIA', '-40.00')))->toBeNull()
        ->and(RefineBankFilter::without($criteria, new Collection, refineBankFilterRow('ADDEBITO SDD EDISON ENERGIA', '-40.00')))->toBeNull();
});

test('never proposes a common word nor a two-letter one', function (): void {
    $keep = new Collection([
        refineBankFilterRow('ENI STATION BOLOGNA', '-40.00'),
        refineBankFilterRow('ENI STATION BOLOGNA', '-45.00'),
    ]);
    $drop = refineBankFilterRow('ENI STATION BOLOGNA SEPA BONIFICO XY', '-42.00');
    $criteria = BankFilterCriteria::fromArray(['contains' => ['ENI']]);

    expect(RefineBankFilter::without($criteria, $keep, $drop))->toBeNull();
});

test('the refined criteria still take every kept transaction and not the dropped one', function (array $data): void {
    [[$keep, $drop], $contains] = $data;
    $refined = RefineBankFilter::without(BankFilterCriteria::fromArray(['contains' => $contains]), $keep, $drop);

    expect($refined)->not->toBeNull()
        ->and($refined['criteria']->isSafe())->toBeTrue()
        ->and(BankFilterMatch::matches($refined['criteria'], $drop))->toBeFalse();

    foreach ($keep as $kept) {
        expect(BankFilterMatch::matches($refined['criteria'], $kept))->toBeTrue();
    }
})->with([
    'a word in Non contiene' => fn (): array => [refineBankFilterEni(), ['ENI', 'POS']],
    'a word in Contiene' => fn (): array => [refineBankFilterBar(), ['ROSSI']],
    'an amount range' => fn (): array => [refineBankFilterAmounts(), ['EDISON']],
]);

test('null when the filter does not take the dropped one any more: nothing to change', function (): void {
    [$keep] = refineBankFilterEni();
    $criteria = BankFilterCriteria::fromArray(['contains' => ['ENI', 'POS'], 'excludes' => ['LAVAGGIO']]);

    expect(RefineBankFilter::without($criteria, $keep, refineBankFilterRow('PAGAMENTO POS ENI LAVAGGIO SELF MODENA CARTA *1234', '-12.00')))->toBeNull();
});
