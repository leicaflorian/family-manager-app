<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Contracts\BankFeed;
use App\Enums\BankConnectionStatus;
use App\Exceptions\Banks\BankFeedFailed;
use App\Jobs\SyncBank;
use App\Models\BankConnection;
use App\Support\Banks\BankSession;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * The last step of connecting a bank: turns the code that comes back from the bank into a session, keeps it in the
 * connection of the family (the same one when the bank was already connected, so connecting again keeps its
 * transactions), ties its accounts and asks for the first read. Runs inside the family of the person.
 */
final readonly class CompleteBankConnection
{
    public function __construct(private BankFeed $feed, private LinkBankAccounts $link) {}

    /**
     * @param  string  $code  the code of the redirect
     * @return BankConnection the connection, active
     *
     * @throws BankFeedFailed when the provider does not accept the code
     */
    public function handle(string $code): BankConnection
    {
        $session = $this->feed->complete($code);

        $connection = DB::transaction(fn (): BankConnection => $this->keep($session));

        dispatch(new SyncBank($connection->id, $connection->family_id));

        return $connection;
    }

    /**
     * Writes the connection and its accounts.
     *
     * @param  BankSession  $session  what the bank gave
     */
    private function keep(BankSession $session): BankConnection
    {
        $connection = BankConnection::query()->updateOrCreate(
            ['provider' => Config::string('bank_feed.provider'), 'institution_name' => $session->institution],
            [
                'institution_country' => Config::string('bank_feed.country'),
                'status' => BankConnectionStatus::Active,
                'valid_until' => $session->validUntil,
                'last_error' => null,
                'expiry_notified_at' => null,
            ],
        );
        $connection->writeSession($session->id, $session->validUntil);
        $this->link->handle($connection, $session->accounts);

        return $connection;
    }
}
