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
