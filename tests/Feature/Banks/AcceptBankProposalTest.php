<?php

declare(strict_types=1);

use App\Actions\Banks\AcceptBankProposal;
use App\Actions\Banks\LinkRecordedMovement;
use App\Enums\BankFilterAction;
use App\Enums\BankMatchSource;
use App\Enums\Role;
use App\Models\BankConnection;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Services\Banks\BankProposals;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Pest\Expectation;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-20 10:00', 'Europe/Rome'));
    config(['bank_feed.provider' => 'fake']);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->laura = User::factory()->create(['family_id' => $this->marco->family_id, 'role' => Role::Member, 'locale' => 'en']);
    $this->posta = insideBianchi(fn (): FinancialAccount => FinancialAccount::factory()->create([
        'family_id' => $this->marco->family_id,
        'name' => 'Banco Posta',
        'last_four_digits' => '1111',
        'bank_connection_id' => BankConnection::factory()->create(['family_id' => $this->marco->family_id])->id,
    ]));
});

/**
 * A booked transaction of the Bianchi on Banco Posta.
 *
 * @param  array<string, mixed>  $attributes  more columns
 */
function acceptBankProposalRow(string $text, string $amount, string $day, array $attributes = []): BankTransaction
{
    return BankTransaction::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'financial_account_id' => FinancialAccount::query()->where('name', 'Banco Posta')->sole()->id,
        'text' => $text,
        'counterparty' => null,
        'amount' => $amount,
        'booked_on' => $day,
        'value_on' => $day,
        ...$attributes,
    ]);
}

/**
 * A monthly planned expense of the Bianchi on the 18th, without a rule or a filter.
 */
function acceptBankProposalPlanned(): PlannedExpense
{
    return PlannedExpense::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'title' => 'Retta danza',
        'amount' => 45.00,
        'fee_amount' => null,
        'is_estimated' => false,
        'start_date' => '2026-01-18',
        'end_date' => null,
        'bank_match' => null,
        'financial_account_id' => null,
    ]);
}

/**
 * The key the list proposes for a transaction.
 */
function acceptBankProposalKey(BankTransaction $transaction): ?string
{
    return insideBianchi(fn (): ?string => (resolve(BankProposals::class)->for(new Collection([$transaction]))[$transaction->id] ?? null)?->key);
}

test('yes links the recorded expense and creates nothing', function (): void {
    [$row, $expense] = insideBianchi(fn (): array => [
        acceptBankProposalRow('PAGAMENTO POS IPMK - SCHIO', '-23.90', '2026-11-15'),
        Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Spesa Famila', 'amount' => 23.90, 'date' => '2026-11-15', 'financial_account_id' => null]),
    ]);

    $this->actingAs($this->laura)
        ->post(route('budget.bank.proposal.accept', $row->id), ['key' => 'expense:'.$expense->id])
        ->assertRedirect(route('budget.bank.index'))
        ->assertInertiaFlash('toast.message', 'Linked: Spesa Famila');

    insideBianchi(function () use ($row, $expense): void {
        expect($row->refresh()->expense_id)->toBe($expense->id)
            ->and($row->matched_by)->toBe(BankMatchSource::Duplicate)
            ->and(Expense::query()->count())->toBe(1)
            ->and(BankFilter::query()->count())->toBe(0);
    });
});

test('yes on a due date pays it as proposed', function (): void {
    insideBianchi(function (): void {
        $planned = acceptBankProposalPlanned();
        $row = acceptBankProposalRow('BONIFICO A FAVORE DI ASD PASSO A DUE', '-45.00', '2026-11-17');

        $result = resolve(AcceptBankProposal::class)->handle($row, 'planned_expense:'.$planned->id.':2026-11-18', $this->laura);

        expect($result['payment'])->toBeInstanceOf(Expense::class)
            ->and($result['payment']?->planned_expense_id)->toBe($planned->id)
            ->and($result['payment']?->occurrence_date?->toDateString())->toBe('2026-11-18')
            ->and($row->refresh()->expense_id)->toBe($result['payment']?->id)
            ->and($row->matched_by)->toBe(BankMatchSource::Proposed)
            ->and($result['filter'])->toBeNull();
    });
});

test('yes saves the filter of a planned item that has none and says so', function (): void {
    [$planned, $row] = insideBianchi(fn (): array => [
        acceptBankProposalPlanned(),
        acceptBankProposalRow('BONIFICO A FAVORE DI ASD PASSO A DUE', '-45.00', '2026-11-17'),
    ]);

    $this->actingAs($this->marco)
        ->post(route('budget.bank.proposal.accept', $row->id), ['key' => 'planned_expense:'.$planned->id.':2026-11-18'])
        ->assertRedirect(route('budget.bank.index'))
        ->assertInertiaFlash('toast.message', 'Paid: Retta danza November 2026 · from now on it pays itself');

    insideBianchi(function () use ($planned, $row): void {
        $filter = BankFilter::query()->sole();

        expect($filter->action)->toBe(BankFilterAction::Pay)
            ->and($filter->planned_expense_id)->toBe($planned->id)
            ->and($filter->contains)->toBe(['FAVORE', 'ASD', 'PASSO', 'DUE'])
            ->and($filter->direction)->toBe('expense')
            ->and($filter->financial_account_id)->toBe($row->financial_account_id)
            ->and($filter->name)->toBe('Retta danza')
            ->and($row->refresh()->matched_by)->toBe(BankMatchSource::Proposed);
    });
});

test('a filter is saved when last month\'s movement is already linked to the same planned item', function (): void {
    [$planned, $row] = insideBianchi(function (): array {
        $planned = acceptBankProposalPlanned();
        $october = Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Retta danza ottobre 2026', 'amount' => 45.00, 'date' => '2026-10-17', 'planned_expense_id' => $planned->id, 'occurrence_date' => '2026-10-18', 'financial_account_id' => null]);
        acceptBankProposalRow('BONIFICO A FAVORE DI ASD PASSO A DUE', '-45.00', '2026-10-17', ['expense_id' => $october->id, 'matched_by' => BankMatchSource::Assigned, 'counterparty' => 'Ottobre']);

        return [$planned, acceptBankProposalRow('BONIFICO A FAVORE DI ASD PASSO A DUE', '-45.00', '2026-11-17')];
    });

    $this->actingAs($this->marco)
        ->post(route('budget.bank.proposal.accept', $row->id), ['key' => 'planned_expense:'.$planned->id.':2026-11-18'])
        ->assertInertiaFlash('toast.message', 'Paid: Retta danza November 2026 · from now on it pays itself');

    insideBianchi(fn (): Expectation => expect(BankFilter::query()->where('planned_expense_id', $planned->id)->count())->toBe(1));
});

test('no filter when its words would take other movements', function (array $other): void {
    [$planned, $row] = insideBianchi(function () use ($other): array {
        $planned = acceptBankProposalPlanned();
        acceptBankProposalRow(...$other);

        return [$planned, acceptBankProposalRow('BONIFICO A FAVORE DI ASD PASSO A DUE', '-45.00', '2026-11-17')];
    });

    $this->actingAs($this->marco)
        ->post(route('budget.bank.proposal.accept', $row->id), ['key' => 'planned_expense:'.$planned->id.':2026-11-18'])
        ->assertInertiaFlash('toast.message', 'Paid: Retta danza November 2026');

    insideBianchi(fn () => expect(BankFilter::query()->count())->toBe(0)
        ->and($row->refresh()->expense_id)->not->toBeNull());
})->with([
    'another movement' => [['BONIFICO A FAVORE DI ASD PASSO A DUE SAGGIO', '-30.00', '2026-10-02', ['counterparty' => 'Saggio']]],
    'a commission' => [['COMMISSIONI BONIFICO A FAVORE DI ASD PASSO A DUE', '-1.00', '2026-11-17', ['counterparty' => 'Poste']]],
]);

test('nor for a member', function (): void {
    [$planned, $row] = insideBianchi(fn (): array => [
        acceptBankProposalPlanned(),
        acceptBankProposalRow('BONIFICO A FAVORE DI ASD PASSO A DUE', '-45.00', '2026-11-17'),
    ]);

    $this->actingAs($this->laura)
        ->post(route('budget.bank.proposal.accept', $row->id), ['key' => 'planned_expense:'.$planned->id.':2026-11-18'])
        ->assertRedirect(route('budget.bank.index'))
        ->assertInertiaFlash('toast.message', 'Paid: Retta danza November 2026');

    insideBianchi(fn (): Expectation => expect(BankFilter::query()->count())->toBe(0)
        ->and($row->refresh()->matched_by)->toBe(BankMatchSource::Proposed));
});

test('a stale key is refused', function (): void {
    insideBianchi(function (): void {
        $row = acceptBankProposalRow('PAGAMENTO POS IPMK - SCHIO', '-23.90', '2026-11-15');
        $expense = Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Spesa Famila', 'amount' => 23.90, 'date' => '2026-11-15', 'financial_account_id' => null]);
        expect(acceptBankProposalKey($row))->toBe('expense:'.$expense->id);

        // Another movement took the expense meanwhile
        $other = acceptBankProposalRow('PAGAMENTO POS FAMILA', '-23.90', '2026-11-16', ['counterparty' => 'Famila']);
        resolve(LinkRecordedMovement::class)->handle($other, $expense);

        expect(fn () => resolve(AcceptBankProposal::class)->handle($row, 'expense:'.$expense->id, $this->marco))
            ->toThrow(ValidationException::class)
            ->and($row->refresh()->expense_id)->toBeNull();
    });

    $row = insideBianchi(fn (): BankTransaction => acceptBankProposalRow('PAGAMENTO POS ALTRO', '-9.90', '2026-11-15', ['counterparty' => 'Altro']));

    $this->actingAs($this->marco)
        ->from(route('budget.bank.index'))
        ->post(route('budget.bank.proposal.accept', $row->id), ['key' => 'expense:999999'])
        ->assertRedirect(route('budget.bank.index'))
        ->assertSessionHasErrors('key');
});

test('two yes at once settle it once', function (): void {
    [$planned, $row] = insideBianchi(fn (): array => [
        acceptBankProposalPlanned(),
        acceptBankProposalRow('BONIFICO A FAVORE DI ASD PASSO A DUE', '-45.00', '2026-11-17'),
    ]);
    $key = 'planned_expense:'.$planned->id.':2026-11-18';

    $this->actingAs($this->laura)->post(route('budget.bank.proposal.accept', $row->id), ['key' => $key])->assertRedirect(route('budget.bank.index'));
    $this->actingAs($this->laura)->from(route('budget.bank.index'))->post(route('budget.bank.proposal.accept', $row->id), ['key' => $key])
        ->assertRedirect(route('budget.bank.index'))
        ->assertSessionHasErrors('key');

    insideBianchi(function () use ($planned, $row, $key): void {
        expect(Expense::query()->where('planned_expense_id', $planned->id)->count())->toBe(1)
            ->and(fn () => resolve(AcceptBankProposal::class)->handle($row, $key, $this->marco))->toThrow(ValidationException::class)
            ->and(Expense::query()->where('planned_expense_id', $planned->id)->count())->toBe(1);
    });
});

test('no remembers the pair', function (): void {
    [$row, $expense] = insideBianchi(fn (): array => [
        acceptBankProposalRow('PAGAMENTO POS IPMK - SCHIO', '-23.90', '2026-11-15'),
        Expense::factory()->create(['family_id' => $this->marco->family_id, 'title' => 'Spesa Famila', 'amount' => 23.90, 'date' => '2026-11-15', 'financial_account_id' => null]),
    ]);

    $this->actingAs($this->laura)
        ->delete(route('budget.bank.proposal.reject', $row->id), ['key' => 'expense:'.$expense->id])
        ->assertRedirect();

    insideBianchi(function () use ($row, $expense): void {
        expect($row->refresh()->rejected_proposals)->toBe(['expense:'.$expense->id])
            ->and(acceptBankProposalKey($row))->toBeNull()
            ->and($row->expense_id)->toBeNull();
    });

    // Saying no twice keeps one entry
    $this->actingAs($this->laura)->delete(route('budget.bank.proposal.reject', $row->id), ['key' => 'expense:'.$expense->id]);
    insideBianchi(fn (): Expectation => expect($row->refresh()->rejected_proposals)->toBe(['expense:'.$expense->id]));
});

test('another family gets 404, a key that is not a key 422', function (): void {
    $row = insideBianchi(fn (): BankTransaction => acceptBankProposalRow('PAGAMENTO POS IPMK - SCHIO', '-23.90', '2026-11-15'));
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $rossi->family->forceFill(['bank_feed_enabled' => true])->save();

    $this->actingAs($rossi)->post(route('budget.bank.proposal.accept', $row->id), ['key' => 'expense:1'])->assertNotFound();
    $this->actingAs($rossi)->delete(route('budget.bank.proposal.reject', $row->id), ['key' => 'expense:1'])->assertNotFound();
    $this->actingAs($this->marco)->from(route('budget.bank.index'))->post(route('budget.bank.proposal.accept', $row->id), ['key' => 'whatever'])->assertSessionHasErrors('key');
    $this->actingAs($this->marco)->from(route('budget.bank.index'))->delete(route('budget.bank.proposal.reject', $row->id), ['key' => ''])->assertSessionHasErrors('key');
});
