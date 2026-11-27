<?php

declare(strict_types=1);

use App\Actions\Banks\SyncBankConnection;
use App\Contracts\BankFeed;
use App\Enums\BankConnectionStatus;
use App\Enums\BankMatchSource;
use App\Exceptions\Banks\BankConsentEnded;
use App\Exceptions\Banks\BankFeedFailed;
use App\Exceptions\Banks\BankLimitReached;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Services\Banks\FakeBankFeed;
use App\Support\Banks\BankEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

beforeEach(function (): void {
    registerFamily();
    $this->feed = new FakeBankFeed;
    app()->instance(BankFeed::class, $this->feed);
});

/**
 * A connection of the Bianchi with a readable session and one account that has a bank uid.
 *
 * @param  array<string, mixed>  $attributes  changes to the connection
 * @return array{BankConnection, FinancialAccount}
 */
function bankSyncSetup(array $attributes = [], ?string $uid = 'uid-1', bool $session = true): array
{
    $familyId = Family::query()->where('name', 'Bianchi')->sole()->id;
    $connection = BankConnection::factory()->create(['family_id' => $familyId, ...$attributes]);

    if ($session) {
        $connection->writeSession('session-'.$connection->id, CarbonImmutable::now()->addDays(100));
    }

    $account = FinancialAccount::factory()->create(['family_id' => $familyId, 'bank_connection_id' => $connection->id, 'bank_account_uid' => $uid]);

    return [$connection->refresh(), $account];
}

test('the first sync asks for 90 days, the next from five days before the last one', function (): void {
    insideBianchi(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'Europe/Rome'));
        [$connection] = bankSyncSetup();
        $this->feed->gives([new BankEntry('a', CarbonImmutable::parse('2026-11-19'), null, -5.0, 'EUR', 'Coffee', null, true)]);

        $count = resolve(SyncBankConnection::class)->handle($connection);

        expect($count)->toBe(1)
            ->and($this->feed->requests()[0]['from']->toDateString())->toBe('2026-08-22')
            ->and($this->feed->requests()[0]['to']->toDateString())->toBe('2026-11-20')
            ->and($connection->refresh()->last_synced_at?->toDateTimeString())->not->toBeNull();

        $this->travelTo(CarbonImmutable::parse('2026-11-21 10:00', 'Europe/Rome'));
        $count = resolve(SyncBankConnection::class)->handle($connection->refresh());

        expect($count)->toBe(0)
            ->and($this->feed->requests()[1]['from']->toDateString())->toBe('2026-11-15')
            ->and($this->feed->requests()[1]['to']->toDateString())->toBe('2026-11-21')
            ->and(BankTransaction::query()->count())->toBe(1);
    });
});

test('an account without a uid is skipped', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankSyncSetup(uid: null);

        resolve(SyncBankConnection::class)->handle($connection);

        expect($this->feed->calls())->toBe(0)
            ->and($connection->refresh()->status)->toBe(BankConnectionStatus::Active)
            ->and($connection->last_synced_at)->not->toBeNull();
    });
});

test('a successful read makes the bank active again and clears the last error', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankSyncSetup(['status' => BankConnectionStatus::Error, 'last_error' => 'down']);
        $this->feed->gives([]);

        resolve(SyncBankConnection::class)->handle($connection);

        expect($connection->refresh()->status)->toBe(BankConnectionStatus::Active)
            ->and($connection->last_error)->toBeNull();
    });
});

test('a connection without a readable session is marked to reconnect and the bank is not called', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankSyncSetup(session: false);

        resolve(SyncBankConnection::class)->handle($connection);

        expect($this->feed->calls())->toBe(0)
            ->and($connection->refresh()->status)->toBe(BankConnectionStatus::NeedsReconnect);
    });
});

test('a consent ended marks the bank to reconnect and the other bank is still read', function (): void {
    insideBianchi(function (): void {
        [$ended] = bankSyncSetup();
        [$other] = bankSyncSetup();
        $endedSession = $ended->sessionId();
        $entry = new BankEntry('ok', CarbonImmutable::parse('2026-11-19'), null, -5.0, 'EUR', 'Coffee', null, true);

        $feed = Mockery::mock(BankFeed::class);
        $feed->shouldReceive('transactions')->andReturnUsing(function (string $session) use ($endedSession, $entry): array {
            throw_if($session === $endedSession, BankConsentEnded::class, 'revoked');

            return [$entry];
        });
        app()->instance(BankFeed::class, $feed);

        $action = resolve(SyncBankConnection::class);

        expect($action->handle($ended))->toBe(0)
            ->and($action->handle($other))->toBe(1)
            ->and($ended->refresh()->status)->toBe(BankConnectionStatus::NeedsReconnect)
            ->and($other->refresh()->status)->toBe(BankConnectionStatus::Active)
            ->and($other->last_synced_at)->not->toBeNull();
    });
});

test('a limit reached changes nothing', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankSyncSetup(['status' => BankConnectionStatus::Error, 'last_error' => 'before']);
        $this->feed->failsWith(new BankLimitReached('too many'));
        Exceptions::fake();

        $count = resolve(SyncBankConnection::class)->handle($connection);

        $connection->refresh();
        expect($count)->toBe(0)
            ->and($connection->status)->toBe(BankConnectionStatus::Error)
            ->and($connection->last_error)->toBe('before')
            ->and($connection->last_synced_at)->toBeNull();
        Exceptions::assertNothingReported();
    });
});

test('a failure is reported and saved as the last error', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankSyncSetup();
        $this->feed->failsWith(new BankFeedFailed('The bank said no'));
        Exceptions::fake();

        $count = resolve(SyncBankConnection::class)->handle($connection);

        $connection->refresh();
        expect($count)->toBe(0)
            ->and($connection->status)->toBe(BankConnectionStatus::Error)
            ->and($connection->last_error)->toBe('The bank said no')
            ->and($connection->last_synced_at)->toBeNull();
        Exceptions::assertReported(BankFeedFailed::class);
    });
});

test('an unexpected error is reported and saved too, and nothing leaves the action', function (): void {
    insideBianchi(function (): void {
        [$connection] = bankSyncSetup();
        $feed = Mockery::mock(BankFeed::class);
        $feed->shouldReceive('transactions')->andThrow(new RuntimeException('boom'));
        app()->instance(BankFeed::class, $feed);
        Exceptions::fake();

        $count = resolve(SyncBankConnection::class)->handle($connection);

        expect($count)->toBe(0)
            ->and($connection->refresh()->status)->toBe(BankConnectionStatus::Error)
            ->and($connection->last_error)->toBe('boom');
        Exceptions::assertReported(RuntimeException::class);
    });
});

test('another read saving the same transaction at the same moment is no error of the bank', function (): void {
    Exceptions::fake();

    insideBianchi(function (): void {
        [$connection, $account] = bankSyncSetup(['status' => BankConnectionStatus::Active, 'last_error' => null]);
        $this->feed->gives([new BankEntry('a', CarbonImmutable::parse('2026-11-19'), null, -5.0, 'EUR', 'Coffee', null, true)]);
        // The other read writes the same row between the check and the insert of this one
        BankTransaction::creating(static function (BankTransaction $row) use ($account): void {
            if (DB::table('bank_transactions')->where('provider_id', $row->provider_id)->doesntExist()) {
                DB::table('bank_transactions')->insert([
                    'family_id' => $account->family_id, 'financial_account_id' => $account->id, 'provider_id' => $row->provider_id,
                    'amount' => '-5.00', 'currency' => 'EUR', 'text' => 'Coffee', 'status' => 'booked', 'booked_on' => '2026-11-19', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        resolve(SyncBankConnection::class)->handle($connection);

        expect($connection->refresh()->status)->toBe(BankConnectionStatus::Active)
            ->and($connection->last_error)->toBeNull();
        Exceptions::assertNothingReported();
    });
});

test('a new commission joins the expense its debit is already linked to', function (): void {
    insideBianchi(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00', 'Europe/Rome'));
        [$connection, $account] = bankSyncSetup();
        $text = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';
        $debit = BankTransaction::factory()->create([
            'family_id' => $account->family_id, 'financial_account_id' => $account->id, 'provider_id' => 'debit-1',
            'text' => $text, 'amount' => '-10.00', 'booked_on' => '2026-11-24', 'value_on' => '2026-11-24',
        ]);
        $expense = Expense::factory()->create(['family_id' => $account->family_id, 'amount' => 10, 'fee_amount' => null, 'date' => '2026-11-24']);
        $debit->linkTo($expense, BankMatchSource::Created);
        $this->feed->gives([new BankEntry('fee-1', CarbonImmutable::parse('2026-11-25'), null, -0.40, 'EUR', 'COMMISSIONI '.$text, null, true)]);

        expect(resolve(SyncBankConnection::class)->handle($connection))->toBe(1)
            ->and((float) $expense->refresh()->amount)->toBe(10.4)
            ->and((float) $expense->fee_amount)->toBe(0.4)
            ->and(BankTransaction::query()->where('provider_id', 'fee-1')->sole()->expense_id)->toBe($expense->id);
    });
});

test('a commission that arrives before its debit does not pay the due date', function (): void {
    insideBianchi(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00', 'Europe/Rome'));
        [$connection, $account] = bankSyncSetup();
        $text = 'DOMICILIAZIONE (ADDEBITO DIRETTO SEPA) Satispay Europe S.A. CID.LU08HOO0000000000000001642 MAN.SAT0023101207360112900';
        PlannedExpense::factory()->create([
            'family_id' => $account->family_id, 'title' => 'Ricarica Satispay', 'bank_match' => 'MAN.SAT0023101207360112900', 'amount' => 10.0,
            'fee_amount' => null, 'is_estimated' => false, 'start_date' => '2026-01-24', 'financial_account_id' => $account->id,
        ]);
        $fee = new BankEntry('fee-1', CarbonImmutable::parse('2026-11-24'), null, -0.40, 'EUR', 'COMMISSIONI '.$text, null, true);
        $debit = new BankEntry('debit-1', CarbonImmutable::parse('2026-11-25'), null, -10.0, 'EUR', $text, null, true);

        $this->feed->gives([$fee]);
        resolve(SyncBankConnection::class)->handle($connection);

        expect(Expense::query()->count())->toBe(0)
            ->and(BankTransaction::query()->where('provider_id', 'fee-1')->sole()->expense_id)->toBeNull();

        $this->travelTo(CarbonImmutable::parse('2026-11-26 10:00', 'Europe/Rome'));
        $this->feed->gives([$fee, $debit]);
        resolve(SyncBankConnection::class)->handle($connection->refresh());

        $expense = Expense::query()->sole();
        expect((float) $expense->amount)->toBe(10.4)
            ->and((float) $expense->fee_amount)->toBe(0.4)
            ->and(BankTransaction::query()->where('provider_id', 'fee-1')->sole()->matched_by)->toBe(BankMatchSource::Fee)
            ->and(BankTransaction::query()->where('provider_id', 'debit-1')->sole()->expense_id)->toBe($expense->id);
    });
});
