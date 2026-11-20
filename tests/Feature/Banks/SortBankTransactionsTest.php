<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-05 07:00', 'Europe/Rome'));
    config(['bank_feed.provider' => 'fake']);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->laura = User::factory()->create(['family_id' => $this->marco->family_id, 'role' => Role::Member]);
});

/**
 * A transaction of the Bianchi still to sort.
 *
 * @param  array<string, mixed>  $attributes  what differs from a booked debit of the day
 */
function bankSortOne(array $attributes = []): BankTransaction
{
    return insideBianchi(fn (): BankTransaction => BankTransaction::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'amount' => '-20.00', 'booked_on' => '2026-11-05', 'value_on' => '2026-11-05', ...$attributes,
    ]));
}

test('ignoring hides the transaction and the count drops', function (): void {
    $transaction = bankSortOne();
    expect(insideBianchi(fn (): int => BankTransaction::query()->unsorted()->count()))->toBe(1);

    $this->actingAs($this->marco)->post("/budget/bank/{$transaction->id}/ignore")->assertRedirect();

    insideBianchi(function () use ($transaction): void {
        expect($transaction->refresh()->ignored_at)->not->toBeNull()
            ->and(BankTransaction::query()->unsorted()->count())->toBe(0)
            ->and(BankTransaction::query()->ignored()->count())->toBe(1);
    });
});

test('restoring brings it back', function (): void {
    $transaction = bankSortOne(['ignored_at' => now()]);

    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/ignore")->assertRedirect();

    insideBianchi(function () use ($transaction): void {
        expect($transaction->refresh()->ignored_at)->toBeNull()
            ->and(BankTransaction::query()->unsorted()->count())->toBe(1);
    });
});

test('a linked transaction cannot be ignored', function (): void {
    $transaction = insideBianchi(function (): BankTransaction {
        $expense = Expense::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id]);

        return bankSortOne(['expense_id' => $expense->id]);
    });

    $this->actingAs($this->marco)->post("/budget/bank/{$transaction->id}/ignore")->assertStatus(422);

    insideBianchi(fn () => expect($transaction->refresh()->ignored_at)->toBeNull());
});

test('a member can ignore and restore', function (): void {
    $transaction = bankSortOne();

    $this->actingAs($this->laura)->post("/budget/bank/{$transaction->id}/ignore")->assertRedirect();
    insideBianchi(fn () => expect($transaction->refresh()->ignored_at)->not->toBeNull());

    $this->actingAs($this->laura)->delete("/budget/bank/{$transaction->id}/ignore")->assertRedirect();
    insideBianchi(fn () => expect($transaction->refresh()->ignored_at)->toBeNull());
});

test('ignoring twice and restoring one that is not ignored change nothing', function (): void {
    $transaction = bankSortOne();

    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/ignore")->assertRedirect();
    insideBianchi(fn () => expect($transaction->refresh()->ignored_at)->toBeNull());

    $this->actingAs($this->marco)->post("/budget/bank/{$transaction->id}/ignore")->assertRedirect();
    $first = insideBianchi(fn () => $transaction->refresh()->ignored_at);
    Date::setTestNow(CarbonImmutable::parse('2026-11-06 07:00', 'Europe/Rome'));
    $this->actingAs($this->marco)->post("/budget/bank/{$transaction->id}/ignore")->assertRedirect();
    insideBianchi(fn () => expect($transaction->refresh()->ignored_at?->equalTo($first))->toBeTrue());
});

test("another family's transaction is 404", function (): void {
    $transaction = bankSortOne();
    $other = registerFamily('Rossi', 'rossi@example.com');
    $other->family->forceFill(['bank_feed_enabled' => true])->save();

    $this->actingAs($other)->post("/budget/bank/{$transaction->id}/ignore")->assertNotFound();
    $this->actingAs($other)->delete("/budget/bank/{$transaction->id}/ignore")->assertNotFound();
    insideBianchi(fn () => expect($transaction->refresh()->ignored_at)->toBeNull());
});

test('ignoring answers 404 while the feed is off', function (): void {
    $transaction = bankSortOne();
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();

    $this->actingAs($this->marco)->post("/budget/bank/{$transaction->id}/ignore")->assertNotFound();
    $this->actingAs($this->marco)->delete("/budget/bank/{$transaction->id}/ignore")->assertNotFound();
});
