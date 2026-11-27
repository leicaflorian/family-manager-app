<?php

declare(strict_types=1);

use App\Enums\BankTransactionStatus;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('a bank transaction is unsorted until linked, ignored or pending', function (): void {
    $family = Family::factory()->create();

    CurrentFamily::using($family->id, function () use ($family): void {
        $open = BankTransaction::factory()->for($family)->create();
        $linked = BankTransaction::factory()->for($family)->create(['expense_id' => Expense::factory()->for($family)->create()->id]);
        $ignored = BankTransaction::factory()->for($family)->create(['ignored_at' => now()]);
        $pending = BankTransaction::factory()->for($family)->create(['status' => BankTransactionStatus::Pending]);

        expect(BankTransaction::query()->unsorted()->pluck('id')->all())->toBe([$open->id])
            ->and(BankTransaction::query()->ignored()->pluck('id')->all())->toBe([$ignored->id])
            ->and($linked->movement()?->is($linked->expense()->first()))->toBeTrue()
            ->and($pending->kind())->toBe('expense');
    });
});

test('a foreign currency transaction is never unsorted', function (): void {
    $family = Family::factory()->create();

    CurrentFamily::using($family->id, function () use ($family): void {
        BankTransaction::factory()->for($family)->create(['currency' => 'USD']);

        expect(BankTransaction::query()->unsorted()->count())->toBe(0);
    });
});

test('the kind follows the sign of the amount', function (): void {
    expect(BankTransaction::factory()->make(['amount' => '-10.00'])->kind())->toBe('expense')
        ->and(BankTransaction::factory()->make(['amount' => '10.00'])->kind())->toBe('income');
});

test('the session is stored encrypted and read back', function (): void {
    $connection = BankConnection::factory()->create();
    CurrentFamily::set($connection->family_id);

    expect($connection->sessionId())->toBeNull();

    $connection->writeSession('secret-session-id', CarbonImmutable::parse('2027-01-15 10:00:00'));
    $connection->refresh();

    expect(str_contains((string) DB::table('bank_connections')->where('id', $connection->id)->value('session'), 'secret-session-id'))->toBeFalse()
        ->and($connection->sessionId())->toBe('secret-session-id')
        ->and($connection->valid_until?->toDateString())->toBe('2027-01-15');
});

test('a session written with another key reads as null', function (): void {
    $connection = BankConnection::factory()->create();
    DB::table('bank_connections')->where('id', $connection->id)->update(['session' => Crypt::encryptString('old')]);

    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');

    expect($connection->refresh()->sessionId())->toBeNull();
});

test('old unlinked transactions are prunable, linked ones never', function (): void {
    $family = Family::factory()->create();
    $old = now()->subMonths(13)->toDateString();

    $expenseId = Expense::factory()->for($family)->create()->id;
    $stale = BankTransaction::factory()->for($family)->create(['booked_on' => $old]);
    $linked = BankTransaction::factory()->for($family)->create(['booked_on' => $old, 'expense_id' => $expenseId]);
    $recent = BankTransaction::factory()->for($family)->create(['booked_on' => now()->subMonths(2)->toDateString()]);

    $ids = (new BankTransaction)->prunable()->pluck('id')->all();

    expect($ids)->toBe([$stale->id])->not->toContain($linked->id, $recent->id);
});

test('writing a session from inside another family does not touch the row', function (): void {
    $connection = BankConnection::factory()->create();
    $other = Family::factory()->create();

    CurrentFamily::using($other->id, fn () => $connection->writeSession('x', CarbonImmutable::parse('2027-01-15')));

    expect(DB::table('bank_connections')->where('id', $connection->id)->value('session'))->toBeNull();
});

test('many connections can be made in one process, in one family too', function (): void {
    $family = Family::factory()->create();

    BankConnection::factory()->count(8)->for($family)->create();

    expect(BankConnection::query()->withoutGlobalScopes()->where('family_id', $family->id)->count())->toBe(8);
});

test('an expense can belong to the bank transaction that paid it together with others', function (): void {
    $family = Family::factory()->create();

    CurrentFamily::using($family->id, function () use ($family): void {
        $transaction = BankTransaction::factory()->for($family)->create();
        $luce = Expense::factory()->for($family)->create(['bank_transaction_id' => $transaction->id]);
        $gas = Expense::factory()->for($family)->create(['bank_transaction_id' => $transaction->id]);
        $alone = Expense::factory()->for($family)->create();

        expect($alone->bank_transaction_id)->toBeNull()
            ->and($luce->bankTransaction?->is($transaction))->toBeTrue()
            ->and($transaction->splitExpenses()->orderBy('id')->pluck('id')->all())->toBe([$luce->id, $gas->id])
            ->and(collect(Schema::getIndexes('expenses'))->contains(static fn (array $index): bool => $index['columns'] === ['bank_transaction_id']))->toBeTrue();

        $transaction->delete();

        expect($luce->refresh()->bank_transaction_id)->toBeNull();
    });
});
