<?php

declare(strict_types=1);

use App\Support\Banks\BankMatch;

it('normalizes to upper case without accents and with single spaces', function (): void {
    expect(BankMatch::normalize("  Edison   Energià\tSpA "))->toBe('EDISON ENERGIA SPA');
});

it('ignores case and accents', function (): void {
    expect(BankMatch::matches('sdd.*edison energia', 'Addebito diretto SDD Edison Energià SpA Fatt. 0123456789'))->toBeTrue()
        ->and(BankMatch::matches('sdd.*enel', 'Addebito diretto SDD Edison Energià SpA'))->toBeFalse();
});

it('refuses a rule that is empty, matches everything, is broken, is slow or is too long', function (string $rule): void {
    expect(BankMatch::valid($rule))->toBeFalse();
})->with([
    'empty' => '',
    'everything' => '.*',
    'unclosed group' => '(',
    'optional only' => 'a*',
    'catastrophic backtracking' => '(a+)+$',
    'too long' => fn (): string => str_repeat('a', 201),
]);

it('accepts a working rule', function (): void {
    expect(BankMatch::valid('SDD.*EDISON'))->toBeTrue();
});

it('works with a rule that has a tilde', function (): void {
    expect(BankMatch::valid('A~B'))->toBeTrue()
        ->and(BankMatch::matches('A~B', 'pagamento a~b srl'))->toBeTrue();
});

it('drops numbers and short words from a signature', function (): void {
    expect(BankMatch::signature('Pagamento POS 1234 di Esselunga Milano *5678'))->toBe('PAGAMENTO POS ESSELUNGA MILANO');
});

it('builds from a text a rule that matches its own text', function (): void {
    $text = 'PAGAMENTO POS ESSELUNGA MILANO CARTA *1234';
    $rule = BankMatch::fromText($text);

    expect($rule)->toBe('PAGAMENTO.*POS.*ESSELUNGA.*MILANO')
        ->and(BankMatch::valid($rule))->toBeTrue()
        ->and(BankMatch::matches($rule, $text))->toBeTrue();
});
