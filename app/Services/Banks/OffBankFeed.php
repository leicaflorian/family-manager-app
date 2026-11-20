<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Contracts\BankFeed;
use App\Support\Banks\BankInstitution;
use App\Support\Banks\BankSession;
use App\Support\Banks\BankVisitor;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * The feed with no provider: it is off, and calling it is a mistake of the code that did not check `isActive()`.
 */
final class OffBankFeed implements BankFeed
{
    /**
     * Always false.
     */
    public function isActive(): bool
    {
        return false;
    }

    /**
     * @param  string  $country  unused
     * @return list<BankInstitution>
     *
     * @throws LogicException always
     */
    public function institutions(string $country): array
    {
        $this->off();
    }

    /**
     * @throws LogicException always
     */
    public function start(BankInstitution $institution, string $state, string $redirectUrl, CarbonImmutable $validUntil): string
    {
        $this->off();
    }

    /**
     * @throws LogicException always
     */
    public function complete(string $code): BankSession
    {
        $this->off();
    }

    /**
     * @throws LogicException always
     */
    public function transactions(string $session, string $accountUid, CarbonImmutable $from, CarbonImmutable $to, ?BankVisitor $visitor = null): array
    {
        $this->off();
    }

    /**
     * @throws LogicException always
     */
    public function revoke(string $session): void
    {
        $this->off();
    }

    /**
     * @throws LogicException always
     */
    private function off(): never
    {
        throw new LogicException('The link to the bank is off.');
    }
}
