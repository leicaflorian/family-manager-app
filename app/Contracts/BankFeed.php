<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\Banks\BankConsentEnded;
use App\Exceptions\Banks\BankFeedFailed;
use App\Exceptions\Banks\BankLimitReached;
use App\Support\Banks\BankEntry;
use App\Support\Banks\BankInstitution;
use App\Support\Banks\BankSession;
use App\Support\Banks\BankVisitor;
use Carbon\CarbonImmutable;

/**
 * The link to the banks: the one door the app uses to list banks, ask a consent and read the transactions of an
 * account. Every method that calls the provider throws `BankFeedFailed`, `BankConsentEnded` when the permission of the
 * person is over, `BankLimitReached` when the bank has no reads left for the day.
 */
interface BankFeed
{
    /**
     * Whether the provider is set up and can be called.
     */
    public function isActive(): bool;

    /**
     * The banks of a country a person can connect.
     *
     * @param  string  $country  the ISO code of the country
     * @return list<BankInstitution>
     *
     * @throws BankFeedFailed
     */
    public function institutions(string $country): array;

    /**
     * Asks a consent and gives back where the person goes to give it.
     *
     * @param  BankInstitution  $institution  the bank
     * @param  string  $state  the secret that comes back with the person
     * @param  string  $redirectUrl  where the bank sends the person back
     * @param  CarbonImmutable  $validUntil  the end of the consent
     * @return string the address of the bank the person is sent to
     *
     * @throws BankFeedFailed
     */
    public function start(BankInstitution $institution, string $state, string $redirectUrl, CarbonImmutable $validUntil): string;

    /**
     * Turns the code that comes back from the bank into a session with its accounts.
     *
     * @param  string  $code  the code of the redirect
     *
     * @throws BankFeedFailed
     */
    public function complete(string $code): BankSession;

    /**
     * The transactions of an account between two dates, all the pages.
     *
     * @param  string  $session  the id of the session
     * @param  string  $accountUid  the id of the account in the session
     * @param  CarbonImmutable  $from  first day
     * @param  CarbonImmutable  $to  last day
     * @param  BankVisitor|null  $visitor  the person who is looking, when it is a person who asked
     * @return list<BankEntry>
     *
     * @throws BankFeedFailed
     * @throws BankConsentEnded
     * @throws BankLimitReached
     */
    public function transactions(string $session, string $accountUid, CarbonImmutable $from, CarbonImmutable $to, ?BankVisitor $visitor = null): array;

    /**
     * Closes the consent at the provider.
     *
     * @param  string  $session  the id of the session
     *
     * @throws BankFeedFailed
     */
    public function revoke(string $session): void;
}
