<?php

declare(strict_types=1);

namespace App\Support\Banks;

/**
 * The person who is looking at the page when a read is asked, as the banks want to know it (a read the person asks
 * is not counted in the daily limit of the banks).
 */
final readonly class BankVisitor
{
    /**
     * @param  string  $ip  the address of the person
     * @param  string  $userAgent  the browser of the person
     */
    public function __construct(
        public string $ip,
        public string $userAgent,
    ) {}
}
