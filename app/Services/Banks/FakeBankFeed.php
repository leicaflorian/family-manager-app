<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Contracts\BankFeed;
use App\Exceptions\Banks\BankFeedFailed;
use App\Support\Banks\BankAccount;
use App\Support\Banks\BankEntry;
use App\Support\Banks\BankInstitution;
use App\Support\Banks\BankSession;
use App\Support\Banks\BankVisitor;
use Carbon\CarbonImmutable;

/**
 * A bank that never calls anyone: one bank, one account, a dozen transactions counted back from today. Used in the
 * tests (`gives()`, `failsWith()`, `calls()`) and in local work with `BANK_FEED_PROVIDER=fake`, never in production.
 */
final class FakeBankFeed implements BankFeed
{
    /** @var list<BankEntry>|null what the tests want to give instead of the file */
    private ?array $entries = null;

    private ?BankFeedFailed $failure = null;

    private int $calls = 0;

    /** @var list<array{from: CarbonImmutable, to: CarbonImmutable}> the dates asked at every read */
    private array $requests = [];

    /** @var list<BankVisitor|null> who was looking at every read of the transactions, null when nobody asked */
    private array $visitors = [];

    /**
     * Every read of the transactions gives these entries, whatever the dates.
     *
     * @param  list<BankEntry>  $entries  the entries to give
     */
    public function gives(array $entries): self
    {
        $this->entries = $entries;
        $this->failure = null;

        return $this;
    }

    /**
     * Every read of the transactions fails with this error.
     *
     * @param  BankFeedFailed  $failure  the error to throw
     */
    public function failsWith(BankFeedFailed $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    /**
     * How many times the transactions were read.
     */
    public function calls(): int
    {
        return $this->calls;
    }

    /**
     * The first and last day asked at every read of the transactions, in order.
     *
     * @return list<array{from: CarbonImmutable, to: CarbonImmutable}>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * The visitor given at every read of the transactions, in order.
     *
     * @return list<BankVisitor|null>
     */
    public function visitors(): array
    {
        return $this->visitors;
    }

    /**
     * Always true.
     */
    public function isActive(): bool
    {
        return true;
    }

    /**
     * The one bank of the fake.
     *
     * @param  string  $country  the country asked
     * @return list<BankInstitution>
     */
    public function institutions(string $country): array
    {
        return [new BankInstitution('Banca di prova', $country, null, 180)];
    }

    /**
     * The person goes straight back with a code, as if the bank had said yes.
     *
     * @param  BankInstitution  $institution  the bank
     * @param  string  $state  the secret to give back
     * @param  string  $redirectUrl  where to go back
     * @param  CarbonImmutable  $validUntil  unused
     */
    public function start(BankInstitution $institution, string $state, string $redirectUrl, CarbonImmutable $validUntil): string
    {
        return $redirectUrl.(str_contains($redirectUrl, '?') ? '&' : '?').http_build_query(['code' => 'fake', 'state' => $state]);
    }

    /**
     * A session with one account that ends in 4821.
     *
     * @param  string  $code  unused
     */
    public function complete(string $code): BankSession
    {
        return new BankSession(
            'fake-session',
            CarbonImmutable::now()->addDays(180),
            'Banca di prova',
            [new BankAccount('fake-account', 'fake-account-key', 'Conto di prova', '4821', 'EUR')],
        );
    }

    /**
     * The transactions of the file with the dates between the two given, or what `gives()` says, or the failure of
     * `failsWith()`.
     *
     * @param  string  $session  unused
     * @param  string  $accountUid  unused
     * @param  CarbonImmutable  $from  first day
     * @param  CarbonImmutable  $to  last day
     * @param  BankVisitor|null  $visitor  kept for `visitors()`
     * @return list<BankEntry>
     *
     * @throws BankFeedFailed when `failsWith()` was called
     */
    public function transactions(string $session, string $accountUid, CarbonImmutable $from, CarbonImmutable $to, ?BankVisitor $visitor = null): array
    {
        $this->calls++;
        $this->requests[] = ['from' => $from, 'to' => $to];
        $this->visitors[] = $visitor;

        if ($this->failure instanceof BankFeedFailed) {
            throw $this->failure;
        }

        if ($this->entries !== null) {
            return $this->entries;
        }

        $today = CarbonImmutable::now('Europe/Rome')->startOfDay();
        $entries = [];

        /** @var list<array{days_ago: int, amount: float|int, text: string, counterparty: string|null, booked: bool}> $rows */
        $rows = json_decode((string) file_get_contents(database_path('seeders/bank/transactions.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($rows as $index => $row) {
            $day = $today->subDays($row['days_ago']);

            if ($day->toDateString() < $from->toDateString() || $day->toDateString() > $to->toDateString()) {
                continue;
            }

            $entries[] = new BankEntry(
                'fake-'.$day->toDateString().'-'.$index,
                $row['booked'] ? $day : null,
                $day,
                (float) $row['amount'],
                'EUR',
                $row['text'],
                $row['counterparty'],
                $row['booked'],
            );
        }

        return $entries;
    }

    /**
     * Nothing to close.
     *
     * @param  string  $session  unused
     */
    public function revoke(string $session): void {}
}
