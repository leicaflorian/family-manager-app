<?php

declare(strict_types=1);

use App\Actions\Banks\MarkBankTransfer;
use App\Actions\Banks\RestoreBankTransaction;
use App\Actions\Banks\SyncBankConnection;
use App\Contracts\BankFeed;
use App\Data\BankTransferData;
use App\Enums\Role;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\User;
use App\Services\Banks\FakeBankFeed;
use App\Support\Banks\BankEntry;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Pest\Expectation;

beforeEach(function (): void {
    $this->marco = registerFamily();
});

/**
 * Two accounts of the Bianchi.
 *
 * @return array{FinancialAccount, FinancialAccount}
 */
function bankTransfersAccounts(): array
{
    $familyId = Family::query()->where('name', 'Bianchi')->sole()->id;

    return [
        FinancialAccount::factory()->create(['family_id' => $familyId, 'name' => 'Savings book']),
        FinancialAccount::factory()->create(['family_id' => $familyId, 'name' => 'Current account']),
    ];
}

/**
 * A booked transaction of the Bianchi on an account.
 *
 * @param  array<string, mixed>  $attributes  changes to the transaction
 */
function bankTransfersRow(FinancialAccount $account, string $amount, string $day, array $attributes = []): BankTransaction
{
    return BankTransaction::factory()->create([
        'family_id' => $account->family_id,
        'financial_account_id' => $account->id,
        'amount' => $amount,
        'booked_on' => $day,
        ...$attributes,
    ]);
}

test('a transfer leaves the list to sort and counts in no budget figure', function (): void {
    insideBianchi(function (): void {
        [$savings, $current] = bankTransfersAccounts();
        $out = bankTransfersRow($savings, '-200.00', '2026-11-10');
        bankTransfersRow($current, '-15.00', '2026-11-10');
        $expenses = Expense::query()->count();
        $incomes = Income::query()->count();

        expect(BankTransaction::query()->unsorted()->count())->toBe(2);

        resolve(MarkBankTransfer::class)->handle($out, $current);

        $out->refresh();
        expect(BankTransaction::query()->unsorted()->count())->toBe(1)
            ->and(BankTransaction::query()->unsorted()->whereKey($out->id)->exists())->toBeFalse()
            ->and($out->transfer_account_id)->toBe($current->id)
            ->and($out->transferAccount?->is($current))->toBeTrue()
            ->and($out->expense_id)->toBeNull()
            ->and($out->income_id)->toBeNull()
            ->and(Expense::query()->count())->toBe($expenses)
            ->and(Income::query()->count())->toBe($incomes);

        $data = BankTransferData::fromTransaction($out);
        expect($data->account)->toBe('Current account')
            ->and($data->direction)->toBe('to')
            ->and($data->paired)->toBeFalse();
    });
});

test('marks the other half on the other linked account, same amount within three days', function (): void {
    insideBianchi(function (): void {
        [$savings, $current] = bankTransfersAccounts();
        $out = bankTransfersRow($savings, '-200.00', '2026-11-10');
        $far = bankTransfersRow($current, '200.00', '2026-11-13');
        $near = bankTransfersRow($current, '200.00', '2026-11-11');

        resolve(MarkBankTransfer::class)->handle($out, $current, declined: true);

        $out->refresh();
        $near->refresh();
        expect($near->transfer_account_id)->toBe($savings->id)
            ->and($near->transfer_pair_id)->toBe($out->id)
            ->and($out->transfer_pair_id)->toBe($near->id)
            ->and($out->declined_at)->not->toBeNull()
            ->and($far->refresh()->transfer_account_id)->toBeNull()
            ->and(BankTransferData::fromTransaction($near->load('transferAccount'))->direction)->toBe('from')
            ->and(BankTransferData::fromTransaction($out->load('transferAccount'))->paired)->toBeTrue();
    });
});

test('not a half of four days later, nor one of another amount', function (): void {
    insideBianchi(function (): void {
        [$savings, $current] = bankTransfersAccounts();
        $out = bankTransfersRow($savings, '-200.00', '2026-11-10');
        $late = bankTransfersRow($current, '200.00', '2026-11-14');
        $other = bankTransfersRow($current, '200.01', '2026-11-10');
        $sameSign = bankTransfersRow($current, '-200.00', '2026-11-10');
        $linked = bankTransfersRow($current, '200.00', '2026-11-10', ['expense_id' => Expense::factory()->create(['family_id' => $savings->family_id])->id]);
        $ignored = bankTransfersRow($current, '200.00', '2026-11-10', ['ignored_at' => now()]);

        resolve(MarkBankTransfer::class)->handle($out, $current);

        expect($out->refresh()->transfer_pair_id)->toBeNull()
            ->and($out->transfer_account_id)->toBe($current->id);

        foreach ([$late, $other, $sameSign, $linked, $ignored] as $row) {
            expect($row->refresh()->transfer_account_id)->toBeNull();
        }
    });
});

test('a half that arrives later is paired at the next read', function (): void {
    insideBianchi(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00', 'Europe/Rome'));
        $feed = new FakeBankFeed;
        app()->instance(BankFeed::class, $feed);
        [$savings, $current] = bankTransfersAccounts();
        $connection = BankConnection::factory()->create(['family_id' => $savings->family_id]);
        $connection->writeSession('session-1', CarbonImmutable::now()->addDays(100));
        $current->forceFill(['bank_connection_id' => $connection->id, 'bank_account_uid' => 'uid-1'])->save();

        $out = bankTransfersRow($savings, '-200.00', '2026-11-10');
        resolve(MarkBankTransfer::class)->handle($out, $current);
        expect($out->refresh()->transfer_pair_id)->toBeNull();

        $feed->gives([
            new BankEntry('in-1', CarbonImmutable::parse('2026-11-11'), null, 200.0, 'EUR', 'From savings', null, true),
            new BankEntry('in-2', CarbonImmutable::parse('2026-11-11'), null, 35.0, 'EUR', 'Salary', null, true),
        ]);
        resolve(SyncBankConnection::class)->handle($connection->refresh());

        $half = BankTransaction::query()->where('provider_id', 'in-1')->sole();
        $salary = BankTransaction::query()->where('provider_id', 'in-2')->sole();
        expect($half->transfer_pair_id)->toBe($out->id)
            ->and($half->transfer_account_id)->toBe($savings->id)
            ->and($out->refresh()->transfer_pair_id)->toBe($half->id)
            ->and($salary->transfer_account_id)->toBeNull()
            ->and(BankTransaction::query()->unsorted()->pluck('id')->all())->toBe([$salary->id]);
    });
});

test('a new transaction is not the half of a transfer to another account or already paired', function (): void {
    insideBianchi(function (): void {
        [$savings, $current] = bankTransfersAccounts();
        $third = FinancialAccount::factory()->create(['family_id' => $savings->family_id]);
        $toThird = bankTransfersRow($savings, '-50.00', '2026-11-10', ['transfer_account_id' => $third->id]);
        $paired = bankTransfersRow($savings, '-60.00', '2026-11-10', ['transfer_account_id' => $current->id]);
        $first = bankTransfersRow($current, '60.00', '2026-11-10', ['transfer_account_id' => $savings->id, 'transfer_pair_id' => $paired->id]);
        $paired->forceFill(['transfer_pair_id' => $first->id])->save();
        $new50 = bankTransfersRow($current, '50.00', '2026-11-10');
        $new60 = bankTransfersRow($current, '60.00', '2026-11-10');

        $count = resolve(MarkBankTransfer::class)->pairNew([$new50, $new60]);

        expect($count)->toBe(0)
            ->and($new50->refresh()->transfer_account_id)->toBeNull()
            ->and($new60->refresh()->transfer_account_id)->toBeNull()
            ->and($toThird->refresh()->transfer_pair_id)->toBeNull();
    });
});

test('putting it back restores both halves', function (): void {
    insideBianchi(function (): void {
        [$savings, $current] = bankTransfersAccounts();
        $out = bankTransfersRow($savings, '-200.00', '2026-11-10');
        $in = bankTransfersRow($current, '200.00', '2026-11-11');
        resolve(MarkBankTransfer::class)->handle($out, $current);
        expect(BankTransaction::query()->unsorted()->count())->toBe(0);

        resolve(RestoreBankTransaction::class)->handle($in);

        foreach ([$out, $in] as $row) {
            $row->refresh();
            expect($row->transfer_account_id)->toBeNull()
                ->and($row->transfer_pair_id)->toBeNull();
        }
        expect(BankTransaction::query()->unsorted()->count())->toBe(2);
    });
});

test('putting back an ignored transaction clears the ignore mark only', function (): void {
    insideBianchi(function (): void {
        [$savings] = bankTransfersAccounts();
        $row = bankTransfersRow($savings, '-5.00', '2026-11-10', ['ignored_at' => now()]);

        resolve(RestoreBankTransaction::class)->handle($row);

        expect($row->refresh()->ignored_at)->toBeNull()
            ->and(BankTransaction::query()->unsorted()->whereKey($row->id)->exists())->toBeTrue();
    });
});

test('the account of the transaction itself is refused', function (): void {
    insideBianchi(function (): void {
        [$savings] = bankTransfersAccounts();
        $row = bankTransfersRow($savings, '-200.00', '2026-11-10');

        try {
            resolve(MarkBankTransfer::class)->handle($row, $savings);
            $this->fail('The account of the transaction was accepted.');
        } catch (ValidationException $failure) {
            expect($failure->errors())->toHaveKey('account_id');
        }

        expect($row->refresh()->transfer_account_id)->toBeNull();
    });
});

test('a transaction that is not to sort any more is refused', function (): void {
    insideBianchi(function (): void {
        [$savings, $current] = bankTransfersAccounts();
        $row = bankTransfersRow($savings, '-200.00', '2026-11-10', ['ignored_at' => now()]);

        expect(fn () => resolve(MarkBankTransfer::class)->handle($row, $current))->toThrow(ValidationException::class);
    });
});

test('pruning keeps a transfer of 13 months ago and deletes an unlinked one', function (): void {
    insideBianchi(function (): void {
        [$savings, $current] = bankTransfersAccounts();
        $old = now()->subMonths(13)->toDateString();
        $transfer = bankTransfersRow($savings, '-200.00', $old, ['transfer_account_id' => $current->id]);
        $stale = bankTransfersRow($savings, '-10.00', $old);

        $ids = (new BankTransaction)->prunable()->pluck('id')->all();

        expect($ids)->toBe([$stale->id])->not->toContain($transfer->id);
    });
});

/**
 * Signs the first account of the Bianchi in (or a member of the family) with the link to the bank on.
 *
 * @param  bool  $member  sign in as a `member`
 */
function bankTransfersSignIn(bool $member = false): User
{
    config(['bank_feed.provider' => 'fake']);
    $marco = test()->marco;
    $marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $user = $member ? User::factory()->create(['family_id' => $marco->family_id, 'role' => Role::Member]) : $marco;
    test()->actingAs($user);

    return $user;
}

test('marks a transfer from the page', function (): void {
    bankTransfersSignIn();
    [$savings, $current] = insideBianchi(bankTransfersAccounts(...));
    $out = insideBianchi(fn (): BankTransaction => bankTransfersRow($savings, '-200.00', '2026-11-10'));

    $this->post(route('budget.bank.transfer', $out), ['account_id' => $current->id])
        ->assertRedirect(route('budget.bank.index'))
        ->assertSessionHasNoErrors();

    insideBianchi(function () use ($out, $current): void {
        expect($out->refresh()->transfer_account_id)->toBe($current->id);
    });
});

test('an account of another family gives 404', function (): void {
    bankTransfersSignIn();
    [$savings] = insideBianchi(bankTransfersAccounts(...));
    $out = insideBianchi(fn (): BankTransaction => bankTransfersRow($savings, '-200.00', '2026-11-10'));
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $foreign = insideFamilyOf($rossi, fn (): FinancialAccount => FinancialAccount::factory()->create(['family_id' => $rossi->family_id]));
    $theirs = insideFamilyOf($rossi, fn (): BankTransaction => BankTransaction::factory()->create(['family_id' => $rossi->family_id, 'financial_account_id' => $foreign->id]));
    $this->actingAs($this->marco);

    $this->post(route('budget.bank.transfer', $out), ['account_id' => $foreign->id])->assertNotFound();
    $this->post(route('budget.bank.transfer', $theirs), ['account_id' => $savings->id])->assertNotFound();

    insideBianchi(fn (): Expectation => expect($out->refresh()->transfer_account_id)->toBeNull());
});

test('a member can mark and restore', function (): void {
    bankTransfersSignIn(member: true);
    [$savings, $current] = insideBianchi(bankTransfersAccounts(...));
    $out = insideBianchi(fn (): BankTransaction => bankTransfersRow($savings, '-200.00', '2026-11-10'));

    $this->post(route('budget.bank.transfer', $out), ['account_id' => $current->id])->assertRedirect();
    insideBianchi(fn (): Expectation => expect($out->refresh()->transfer_account_id)->toBe($current->id));

    $this->delete(route('budget.bank.restore', $out))->assertRedirect();
    insideBianchi(fn (): Expectation => expect($out->refresh()->transfer_account_id)->toBeNull());
});

test('the same account is refused with an error on account_id', function (): void {
    bankTransfersSignIn();
    [$savings] = insideBianchi(bankTransfersAccounts(...));
    $out = insideBianchi(fn (): BankTransaction => bankTransfersRow($savings, '-200.00', '2026-11-10'));

    $this->post(route('budget.bank.transfer', $out), ['account_id' => $savings->id])->assertSessionHasErrors('account_id');
    $this->post(route('budget.bank.transfer', $out), [])->assertSessionHasErrors('account_id');

    insideBianchi(fn (): Expectation => expect($out->refresh()->transfer_account_id)->toBeNull());
});

test('restore puts back both halves from the page', function (): void {
    bankTransfersSignIn();
    [$savings, $current] = insideBianchi(bankTransfersAccounts(...));
    [$out, $in] = insideBianchi(fn (): array => [
        bankTransfersRow($savings, '-200.00', '2026-11-10'),
        bankTransfersRow($current, '200.00', '2026-11-11'),
    ]);
    $this->post(route('budget.bank.transfer', $out), ['account_id' => $current->id])->assertRedirect();
    insideBianchi(fn (): Expectation => expect($in->refresh()->transfer_pair_id)->toBe($out->id));

    $this->delete(route('budget.bank.restore', $in))->assertRedirect();

    insideBianchi(function () use ($out, $in): void {
        expect($out->refresh()->transfer_account_id)->toBeNull()
            ->and($out->transfer_pair_id)->toBeNull()
            ->and($in->refresh()->transfer_account_id)->toBeNull()
            ->and($in->transfer_pair_id)->toBeNull();
    });
});

test('a marked transfer leaves the list to sort and stays in the list all', function (): void {
    bankTransfersSignIn();
    [$savings, $current] = insideBianchi(bankTransfersAccounts(...));
    $out = insideBianchi(fn (): BankTransaction => bankTransfersRow($savings, '-200.00', '2026-11-10', ['text' => 'MOVE']));
    $this->post(route('budget.bank.transfer', $out), ['account_id' => $current->id])->assertRedirect();

    $this->get(route('budget.bank.index'))->assertInertia(fn ($page) => $page->has('rows', 0));
    $this->get(route('budget.bank.index', ['show' => 'all']))->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.status', 'transfer'));
});
