<?php

declare(strict_types=1);

use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankFilterMatch;
use App\Support\CurrentFamily;

test('a filter on a linked account refuses another account, and one with no account reads them all', function (): void {
    $family = Family::factory()->create();

    CurrentFamily::using($family->id, function () use ($family): void {
        $connection = BankConnection::factory()->for($family)->create();
        $mine = FinancialAccount::factory()->for($family)->create(['bank_connection_id' => $connection->id]);
        $other = FinancialAccount::factory()->for($family)->create(['bank_connection_id' => $connection->id]);
        $transaction = BankTransaction::factory()->for($family)->create(['financial_account_id' => $other->id, 'text' => 'Edison']);

        $onMine = BankFilterCriteria::fromArray(['contains' => ['edison'], 'accountId' => $mine->id]);
        $onAny = BankFilterCriteria::fromArray(['contains' => ['edison']]);

        expect(BankFilterMatch::matches($onMine, $transaction))->toBeFalse()
            ->and(BankFilterMatch::matches($onAny, $transaction))->toBeTrue()
            ->and(BankFilterMatch::matches(BankFilterCriteria::fromArray(['contains' => ['edison'], 'accountId' => $other->id]), $transaction))->toBeTrue();
    });
});
