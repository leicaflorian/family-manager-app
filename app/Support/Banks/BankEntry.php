<?php

declare(strict_types=1);

namespace App\Support\Banks;

use Carbon\CarbonImmutable;

/**
 * A transaction as the bank reports it.
 */
final readonly class BankEntry
{
    /**
     * @param  string|null  $providerId  the id the bank gives it, when it gives one
     * @param  CarbonImmutable|null  $bookedOn  the day the bank booked it
     * @param  CarbonImmutable|null  $valueOn  the day of the value
     * @param  float  $amount  the amount with its sign: negative is money out
     * @param  string  $currency  the ISO code of the currency
     * @param  string  $text  the description: the other party and the reason
     * @param  string|null  $counterparty  who paid or was paid
     * @param  bool  $booked  false while the bank still has it pending
     */
    public function __construct(
        public ?string $providerId,
        public ?CarbonImmutable $bookedOn,
        public ?CarbonImmutable $valueOn,
        public float $amount,
        public string $currency,
        public string $text,
        public ?string $counterparty,
        public bool $booked,
    ) {}
}
