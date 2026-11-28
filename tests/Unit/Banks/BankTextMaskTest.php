<?php

declare(strict_types=1);

use App\Support\Banks\BankTextMask;

test('an IBAN becomes IBAN', function (): void {
    expect(BankTextMask::forModel('BONIFICO DA IT60X0542811101000000123456 AFFITTO', []))->toBe('BONIFICO DA IBAN AFFITTO');
});

test('an amount becomes IMPORTO, with or without the currency', function (string $text, string $masked): void {
    expect(BankTextMask::forModel($text, []))->toBe($masked);
})->with([
    'comma and EUR after' => ['PAGAMENTO POS 42,00 EUR NETFLIX', 'PAGAMENTO POS IMPORTO NETFLIX'],
    'dot' => ['RICARICA 15.50 TIM', 'RICARICA IMPORTO TIM'],
    'EUR before' => ['ADDEBITO EUR 1.234,56 MUTUO', 'ADDEBITO IMPORTO MUTUO'],
    'thousands without currency' => ['STIPENDIO 1.834,56', 'STIPENDIO IMPORTO'],
]);

test('the names of the family become PERSONA, whole words only, whatever the case', function (): void {
    $masked = BankTextMask::forModel('BONIFICO A FAVORE DI OTTAVIO BELLANDI E ROSSETTINI', ['Ottavio', 'bellandi', 'Rossetti']);

    expect($masked)->toBe('BONIFICO A FAVORE DI PERSONA PERSONA E ROSSETTINI');
});

test('a name with an accent is found in the normalised text', function (): void {
    expect(BankTextMask::forModel('BONIFICO NICOLO ZANE', ['Nicolò']))->toBe('BONIFICO PERSONA ZANE');
});

test('names of one or two letters are not masked', function (): void {
    expect(BankTextMask::forModel('PAGAMENTO AL BAR', ['Al']))->toBe('PAGAMENTO AL BAR');
});

test('mandate and creditor codes and shop names stay', function (): void {
    $text = 'ADDEBITO DIRETTO SEPA ENEL ENERGIA MAN.IT12ZZZ0000012345678 CID.IT98ZZZ0000098765432 FATTURA 123';

    expect(BankTextMask::forModel($text, ['Ottavio']))->toBe($text);
});

test('an amount next to a currency becomes IMPORTO, also without two decimals', function (string $text, string $masked): void {
    expect(BankTextMask::forModel($text, []))->toBe($masked);
})->with([
    'whole, EUR after' => ['COMMISSIONE 42 EUR', 'COMMISSIONE IMPORTO'],
    'whole, EUR before' => ['RICARICA EUR 42 TIM', 'RICARICA IMPORTO TIM'],
    'one decimal' => ['PAGAMENTO 42,5 EUR BAR', 'PAGAMENTO IMPORTO BAR'],
    'thousands' => ['BONIFICO 1.234 EUR', 'BONIFICO IMPORTO'],
]);

test('an amount glued to letters becomes IMPORTO', function (): void {
    expect(BankTextMask::forModel('PAGAMENTO POS42,00 NETFLIX', []))->toBe('PAGAMENTO POS IMPORTO NETFLIX');
});

test('a card number or its tail becomes CARTA', function (string $text, string $masked): void {
    expect(BankTextMask::forModel($text, []))->toBe($masked);
})->with([
    'stars' => ['PAGAMENTO CARTA ****1234 AMAZON', 'PAGAMENTO CARTA CARTA AMAZON'],
    'crosses' => ['POS XXXX1234 ESSELUNGA', 'POS CARTA ESSELUNGA'],
    'sixteen digits' => ['ADDEBITO 4532015112830366 AMAZON', 'ADDEBITO CARTA AMAZON'],
    'thirteen digits' => ['ADDEBITO 4532015112830 AMAZON', 'ADDEBITO CARTA AMAZON'],
]);

test('a run of nine to twelve digits becomes NUMERO', function (string $text, string $masked): void {
    expect(BankTextMask::forModel($text, []))->toBe($masked);
})->with([
    'account' => ['GIROCONTO DA C/C 000012345678', 'GIROCONTO DA C/C NUMERO'],
    'phone glued' => ['RICARICA TEL3331234567 TIM', 'RICARICA TEL NUMERO TIM'],
    'nine digits' => ['RIF 123456789 ENEL', 'RIF NUMERO ENEL'],
]);

test('an invoice number shorter than nine digits stays', function (): void {
    expect(BankTextMask::forModel('FATTURA 12345678 DEL MESE', []))->toBe('FATTURA 12345678 DEL MESE');
});

test('an IBAN after a slash or a dot, or written in groups, becomes IBAN', function (string $text, string $masked): void {
    expect(BankTextMask::forModel($text, []))->toBe($masked);
})->with([
    'after C/C.' => ['GIROCONTO C/C.IT60X0542811101000000123456 RISPARMI', 'GIROCONTO C/C.IBAN RISPARMI'],
    'after a slash' => ['BONIFICO /IT60X0542811101000000123456 AFFITTO', 'BONIFICO /IBAN AFFITTO'],
    'in groups' => ['BONIFICO DA IT60 X054 2811 1010 0000 0123 456 AFFITTO CASA', 'BONIFICO DA IBAN AFFITTO CASA'],
]);

test('a creditor id after ID CREDITORE stays', function (): void {
    $text = 'SDD ENEL ENERGIA ID CREDITORE IT12ZZZ0000012345678 FATTURA 123';

    expect(BankTextMask::forModel($text, []))->toBe($text);
});
