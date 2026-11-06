<?php

declare(strict_types=1);

use App\Support\Money;

test('an amount is compared in cents, without the float error', function (): void {
    expect(Money::cents('30.10') - Money::cents('0.10'))->toBe(3000)
        ->and(Money::cents(null))->toBe(0);
});

test('an amount is written with the comma and the euro after it, in both languages', function (): void {
    expect(Money::euro(84.2))->toBe("84,20\u{a0}€")
        ->and(Money::euro(1234.5))->toBe("1.234,50\u{a0}€");
});
