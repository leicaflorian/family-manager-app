<?php

declare(strict_types=1);

use App\Support\Banks\BankText;

const BANK_TEXT_DEBIT = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';
const BANK_TEXT_FEE = 'COMMISSIONI DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';

it('a debit and its commission have the same mandate and the same creditor', function (): void {
    expect(BankText::mandate(BANK_TEXT_DEBIT))->toBe('SAT0023101207360112900')
        ->and(BankText::mandate(BANK_TEXT_FEE))->toBe('SAT0023101207360112900')
        ->and(BankText::creditor(BANK_TEXT_DEBIT))->toBe('LU08HOO0000000000000001642')
        ->and(BankText::creditor(BANK_TEXT_FEE))->toBe(BankText::creditor(BANK_TEXT_DEBIT));
});

it('reads the mandate whatever the case and the spaces', function (): void {
    expect(BankText::mandate('addebito   man.abc123  altro'))->toBe('ABC123');
});

it('a text without a mandate or a creditor gives null', function (): void {
    expect(BankText::mandate('PAGAMENTO POS ESSELUNGA'))->toBeNull()
        ->and(BankText::creditor('PAGAMENTO POS ESSELUNGA'))->toBeNull()
        ->and(BankText::mandate('ALLEMAN.X'))->toBeNull();
});

it('only a text that starts with COMMISSIONI or COMMISSIONE is a commission', function (): void {
    expect(BankText::isFee(BANK_TEXT_FEE))->toBeTrue()
        ->and(BankText::isFee('  commissione bonifico'))->toBeTrue()
        ->and(BankText::isFee(BANK_TEXT_DEBIT))->toBeFalse()
        ->and(BankText::isFee('PAGAMENTO COMMISSIONI'))->toBeFalse();
});

it('the words of a direct debit are its mandate', function (): void {
    expect(BankText::words(BANK_TEXT_DEBIT))->toBe(['MAN.SAT0023101207360112900']);
});

it('the words of a text with only a creditor are its creditor', function (): void {
    expect(BankText::words('ADDEBITO DIRETTO SEPA CID.IT99ZZZ0000001'))->toBe(['CID.IT99ZZZ0000001']);
});

it('the words of a POS payment are its shop without the common words', function (): void {
    expect(BankText::words('PAGAMENTO POS ESSELUNGA MILANO 1234'))->toBe(['ESSELUNGA', 'MILANO']);
});

it('keeps at most four words', function (): void {
    expect(BankText::words('NEGOZIO UNO DUE TRE QUATTRO CINQUE'))->toBe(['NEGOZIO', 'UNO', 'DUE', 'TRE']);
});
