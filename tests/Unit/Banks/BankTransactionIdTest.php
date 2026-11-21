<?php

declare(strict_types=1);

use App\Support\Banks\BankTransactionId;

test('the id of a bank transaction in an address is a number of up to 18 digits', function (mixed $value, ?int $id): void {
    expect(BankTransactionId::parse($value))->toBe($id);
})->with([
    'a number' => ['42', 42],
    'eighteen digits' => ['123456789012345678', 123456789012345678],
    'nineteen digits' => ['1234567890123456789', null],
    'empty' => ['', null],
    'with a letter' => ['42a', null],
    'negative' => ['-4', null],
    'not text' => [['42'], null],
    'missing' => [null, null],
]);
