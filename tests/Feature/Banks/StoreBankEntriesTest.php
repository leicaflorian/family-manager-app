<?php

declare(strict_types=1);

use App\Actions\Banks\StoreBankEntries;
use App\Enums\BankTransactionStatus;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Support\Banks\BankEntry;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    registerFamily();
});

/**
 * A connection with one account in the Bianchi family and the entries stored through it.
 *
 * @param  list<BankEntry>  $entries  the entries to store
 * @param  array{BankConnection, FinancialAccount}|null  $setup  an existing connection and account
 * @return array{BankConnection, FinancialAccount, list<BankTransaction>}
 */
function bankStoreRun(array $entries, ?array $setup = null): array
{
    if ($setup === null) {
        $familyId = Family::query()->where('name', 'Bianchi')->sole()->id;
        $connection = BankConnection::factory()->create(['family_id' => $familyId]);
        $account = FinancialAccount::factory()->create(['family_id' => $familyId, 'bank_connection_id' => $connection->id, 'bank_account_uid' => 'uid-1']);
    } else {
        [$connection, $account] = $setup;
    }

    return [$connection, $account, resolve(StoreBankEntries::class)->handle($connection, $account, $entries)];
}

/**
 * A booked entry of a day.
 */
function bankStoreEntry(?string $id, string $day, float $amount, string $text, bool $booked = true): BankEntry
{
    $date = CarbonImmutable::parse($day, 'Europe/Rome');

    return new BankEntry($id, $booked ? $date : null, $date, $amount, 'EUR', $text, null, $booked);
}

test('the same entries read twice are stored once', function (): void {
    insideBianchi(function (): void {
        $entries = [bankStoreEntry('a', '2026-11-10', -10.0, 'One'), bankStoreEntry('b', '2026-11-11', -20.5, 'Two')];

        [$connection, $account, $first] = bankStoreRun($entries);
        [, , $second] = bankStoreRun($entries, [$connection, $account]);

        expect($first)->toHaveCount(2)
            ->and($second)->toBe([])
            ->and(BankTransaction::query()->count())->toBe(2)
            ->and(BankTransaction::query()->where('provider_id', 'b')->sole()->amount)->toBe('-20.50');
    });
});

test('two identical entries of the same day without an id stay two, also after a second read', function (): void {
    insideBianchi(function (): void {
        $coffee = fn (): BankEntry => bankStoreEntry(null, '2026-11-10', -1.2, 'Bar Roma');

        [$connection, $account, $first] = bankStoreRun([$coffee(), $coffee()]);
        [, , $second] = bankStoreRun([$coffee(), $coffee()], [$connection, $account]);
        [, , $third] = bankStoreRun([$coffee(), $coffee(), $coffee()], [$connection, $account]);

        expect($first)->toHaveCount(2)
            ->and($second)->toBe([])
            ->and($third)->toHaveCount(1)
            ->and(BankTransaction::query()->count())->toBe(3);
    });
});

test('a pending entry that becomes booked is new once', function (): void {
    insideBianchi(function (): void {
        [$connection, $account, $first] = bankStoreRun([bankStoreEntry(null, '2026-11-10', -30.0, 'Shop', false)]);
        expect($first)->toBe([])
            ->and(BankTransaction::query()->sole()->status)->toBe(BankTransactionStatus::Pending);

        [, , $second] = bankStoreRun([bankStoreEntry('x1', '2026-11-10', -30.0, 'Shop')], [$connection, $account]);
        [, , $third] = bankStoreRun([bankStoreEntry('x1', '2026-11-10', -30.0, 'Shop')], [$connection, $account]);

        expect($second)->toHaveCount(1)
            ->and($third)->toBe([])
            ->and(BankTransaction::query()->count())->toBe(1)
            ->and(BankTransaction::query()->sole()->status)->toBe(BankTransactionStatus::Booked);
    });
});

test('a pending entry that disappears is removed', function (): void {
    insideBianchi(function (): void {
        [$connection, $account] = bankStoreRun([bankStoreEntry(null, '2026-11-10', -30.0, 'Shop', false), bankStoreEntry(null, '2026-11-10', -30.0, 'Shop', false)]);
        expect(BankTransaction::query()->count())->toBe(2);

        bankStoreRun([bankStoreEntry(null, '2026-11-10', -30.0, 'Shop', false)], [$connection, $account]);
        expect(BankTransaction::query()->count())->toBe(1);

        bankStoreRun([], [$connection, $account]);
        expect(BankTransaction::query()->count())->toBe(0);
    });
});

test('an entry already linked or ignored is left as it is', function (): void {
    insideBianchi(function (): void {
        [$connection, $account] = bankStoreRun([bankStoreEntry('a', '2026-11-10', -10.0, 'Old text')]);
        $row = BankTransaction::query()->sole();
        $row->forceFill(['ignored_at' => now(), 'text' => 'Changed by the family'])->save();

        [, , $again] = bankStoreRun([bankStoreEntry('a', '2026-11-10', -10.0, 'New text from the bank')], [$connection, $account]);

        expect($again)->toBe([])
            ->and($row->refresh()->text)->toBe('Changed by the family')
            ->and($row->ignored_at)->not->toBeNull();
    });
});

test('a pending entry already linked to a payment is not rewritten', function (): void {
    insideBianchi(function (): void {
        [$connection, $account] = bankStoreRun([bankStoreEntry(null, '2026-11-10', -30.0, 'Shop', false)]);
        $expense = Expense::factory()->create(['family_id' => $account->family_id]);
        $row = BankTransaction::query()->sole();
        $row->forceFill(['expense_id' => $expense->id])->save();

        bankStoreRun([bankStoreEntry(null, '2026-11-10', -30.0, 'Shop', false)], [$connection, $account]);

        expect(BankTransaction::query()->count())->toBe(1)
            ->and($row->refresh()->expense_id)->toBe($expense->id);
    });
});

test('an entry in another currency is stored with its currency', function (): void {
    insideBianchi(function (): void {
        $entry = new BankEntry('usd', CarbonImmutable::parse('2026-11-10'), null, -9.99, 'USD', 'Shop abroad', 'Shop', true);

        bankStoreRun([$entry]);

        expect(BankTransaction::query()->sole()->currency)->toBe('USD');
    });
});
