<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Contracts\BankFeed;
use App\Exceptions\Banks\BankConsentEnded;
use App\Exceptions\Banks\BankFeedFailed;
use App\Exceptions\Banks\BankLimitReached;
use App\Support\Banks\BankAccount;
use App\Support\Banks\BankEntry;
use App\Support\Banks\BankInstitution;
use App\Support\Banks\BankSession;
use App\Support\Banks\BankVisitor;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * The link to the banks through Enable Banking (https://enablebanking.com/docs/api/reference/).
 */
final readonly class EnableBankingFeed implements BankFeed
{
    /** Error codes of the provider that mean the consent is over. */
    private const array CONSENT_ENDED = ['EXPIRED_SESSION', 'CLOSED_SESSION', 'REVOKED_SESSION', 'SESSION_DOES_NOT_EXIST'];

    /** The most pages one read follows, so a provider that never stops cannot loop forever. */
    private const int MAX_PAGES = 200;

    /**
     * @param  EnableBankingToken  $token  makes the JWT of every call
     */
    public function __construct(private EnableBankingToken $token) {}

    /**
     * Whether the application id is set and the private key can be read.
     */
    public function isActive(): bool
    {
        return EnableBankingToken::setting('app_id') !== '' && EnableBankingToken::keyPath() !== null;
    }

    /**
     * The banks of a country that serve private persons.
     *
     * @param  string  $country  the ISO code of the country
     * @return list<BankInstitution>
     *
     * @throws BankFeedFailed
     */
    public function institutions(string $country): array
    {
        $body = $this->send(fn (PendingRequest $http): Response => $http->get('/aspsps', ['country' => $country]));
        $banks = [];

        foreach (Arr::wrap($body['aspsps'] ?? []) as $bank) {
            if (! is_array($bank) || ! in_array('personal', (array) ($bank['psu_types'] ?? []), true)) {
                continue;
            }

            $banks[] = new BankInstitution(
                $this->text($bank, 'name'),
                $this->str($bank['country'] ?? null, $country),
                is_string($bank['logo'] ?? null) ? $bank['logo'] : null,
                $this->consentDays($bank['maximum_consent_validity'] ?? null),
            );
        }

        return $banks;
    }

    /**
     * Asks a consent for a private person.
     *
     * @throws BankFeedFailed
     */
    public function start(BankInstitution $institution, string $state, string $redirectUrl, CarbonImmutable $validUntil): string
    {
        $body = $this->send(fn (PendingRequest $http): Response => $http->post('/auth', [
            'access' => ['valid_until' => $validUntil->utc()->format('Y-m-d\TH:i:s.uP')],
            'aspsp' => ['name' => $institution->name, 'country' => $institution->country],
            'state' => $state,
            'redirect_url' => $redirectUrl,
            'psu_type' => 'personal',
        ]));

        return $this->text($body, 'url');
    }

    /**
     * Opens the session with the code of the redirect.
     *
     * @throws BankFeedFailed
     */
    public function complete(string $code): BankSession
    {
        $body = $this->send(fn (PendingRequest $http): Response => $http->post('/sessions', ['code' => $code]));
        $accounts = [];

        foreach (Arr::wrap($body['accounts'] ?? []) as $account) {
            if (! is_array($account)) {
                continue;
            }

            $iban = Arr::get($account, 'account_id.iban');

            $accounts[] = new BankAccount(
                $this->text($account, 'uid'),
                $this->text($account, 'identification_hash'),
                $this->str($account['name'] ?? null, $this->str($account['product'] ?? null, '')),
                is_string($iban) && strlen($iban) >= 4 ? substr($iban, -4) : null,
                $this->text($account, 'currency'),
            );
        }

        return new BankSession(
            $this->text($body, 'session_id'),
            $this->parse($this->text((array) ($body['access'] ?? []), 'valid_until'), false),
            $this->str(Arr::get($body, 'aspsp.name'), ''),
            $accounts,
        );
    }

    /**
     * Every transaction between two dates, following the pages until the provider has no more.
     *
     * @return list<BankEntry>
     *
     * @throws BankFeedFailed
     */
    public function transactions(string $session, string $accountUid, CarbonImmutable $from, CarbonImmutable $to, ?BankVisitor $visitor = null): array
    {
        $entries = [];
        $key = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $query = ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()] + ($key === null ? [] : ['continuation_key' => $key]);
            $body = $this->send(function (PendingRequest $http) use ($accountUid, $query, $visitor): Response {
                if ($visitor instanceof BankVisitor) {
                    $http = $http->withHeaders(['Psu-Ip-Address' => $visitor->ip, 'Psu-User-Agent' => $visitor->userAgent]);
                }

                return $http->get('/accounts/'.rawurlencode($accountUid).'/transactions', $query);
            });

            foreach (Arr::wrap($body['transactions'] ?? []) as $transaction) {
                if (is_array($transaction)) {
                    $entries[] = $this->entry($transaction);
                }
            }

            $next = $body['continuation_key'] ?? null;

            if (! is_string($next) || $next === '' || $next === $key) {
                return $entries;
            }

            $key = $next;
        }

        throw new BankFeedFailed('The bank gave too many pages of transactions.');
    }

    /**
     * Closes the consent. A failure says only its kind: the address and the answer of the provider carry the session,
     * which is never logged.
     *
     * @throws BankFeedFailed
     */
    public function revoke(string $session): void
    {
        try {
            $this->send(fn (PendingRequest $http): Response => $http->delete('/sessions/'.rawurlencode($session)));
        } catch (BankFeedFailed $failure) {
            throw new BankFeedFailed('The bank provider did not close the consent ('.class_basename($failure->getPrevious() ?? $failure).').');
        }
    }

    /**
     * Makes a call with the token and the base address, and maps the errors of the provider.
     *
     * @param  callable(PendingRequest): Response  $call  the call to make
     * @return array<string, mixed> the JSON of the answer
     *
     * @throws BankFeedFailed
     */
    private function send(callable $call): array
    {
        $http = Http::baseUrl(Config::string('bank_feed.enable_banking.url', 'https://api.enablebanking.com'))
            ->withToken($this->token->make(CarbonImmutable::now()))
            ->acceptJson()
            ->timeout(30);

        try {
            $response = $call($http);
        } catch (ConnectionException $e) {
            throw new BankFeedFailed('The bank provider did not answer.', 0, $e);
        }

        if ($response->successful()) {
            /** @var array<string, mixed> $json */
            $json = (array) $response->json();

            return $json;
        }

        $error = $this->str($response->json('error'), '');
        $message = $this->str($response->json('message'), 'The bank provider answered with an error.');

        throw match (true) {
            $response->status() === 429 || $error === 'ASPSP_RATE_LIMIT_EXCEEDED' => new BankLimitReached($message),
            in_array($error, self::CONSENT_ENDED, true) => new BankConsentEnded($message),
            default => new BankFeedFailed($message),
        };
    }

    /**
     * One transaction of the provider as an entry.
     *
     * @param  array<array-key, mixed>  $transaction
     */
    private function entry(array $transaction): BankEntry
    {
        $debit = ($transaction['credit_debit_indicator'] ?? null) === 'DBIT';
        $party = Arr::get($transaction, $debit ? 'creditor.name' : 'debtor.name');
        $counterparty = is_string($party) && $party !== '' ? $party : null;
        $id = $transaction['entry_reference'] ?? null;
        $id = is_string($id) && $id !== '' ? $id : ($transaction['transaction_id'] ?? null);

        $lines = array_values(array_filter(
            array_map(fn (mixed $line): string => trim($this->str($line, '')), Arr::wrap($transaction['remittance_information'] ?? [])),
            fn (string $line): bool => $line !== '' && $line !== $counterparty,
        ));
        $rawAmount = Arr::get($transaction, 'transaction_amount.amount');

        if (! is_numeric($rawAmount)) {
            throw new BankFeedFailed('The bank provider answered a transaction without an amount.');
        }

        $amount = abs((float) $rawAmount);

        return new BankEntry(
            is_string($id) && $id !== '' ? $id : null,
            $this->date($transaction['booking_date'] ?? null),
            $this->date($transaction['value_date'] ?? null),
            $debit ? -$amount : $amount,
            $this->text((array) Arr::get($transaction, 'transaction_amount', []), 'currency'),
            implode(' ', array_filter([$counterparty, ...$lines])),
            $counterparty,
            ($transaction['status'] ?? null) === 'BOOK',
        );
    }

    /**
     * A day of the provider, or null when it gave none.
     */
    private function date(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? $this->parse($value, true) : null;
    }

    /**
     * Reads a date of the provider.
     *
     * @param  string  $value  the text of the date
     * @param  bool  $day  true to keep only the day (Europe/Rome)
     *
     * @throws BankFeedFailed when it is not a date
     */
    private function parse(string $value, bool $day): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::parse($value, 'Europe/Rome');
        } catch (\Throwable $e) {
            throw new BankFeedFailed('The bank provider answered with a date that cannot be read.', 0, $e);
        }

        return $day ? $date->startOfDay() : $date;
    }

    /**
     * A text field that must be there.
     *
     * @param  array<array-key, mixed>  $data
     *
     * @throws BankFeedFailed when it is missing
     */
    private function text(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (! is_string($value) || $value === '') {
            throw new BankFeedFailed("The bank provider answered without {$field}.");
        }

        return $value;
    }

    /**
     * A text of the provider, or the default when it is not text.
     */
    private function str(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * The longest consent of a bank in days, from its seconds; the default of the config when the bank says nothing
     * usable (one bank without it must not break the list).
     */
    private function consentDays(mixed $seconds): int
    {
        $days = is_numeric($seconds) ? (int) floor((float) $seconds / 86400) : 0;

        return $days > 0 ? $days : Config::integer('bank_feed.consent_days', 180);
    }
}
