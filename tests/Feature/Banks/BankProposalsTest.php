<?php

declare(strict_types=1);

use App\Data\BankProposalData;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Banks\BankProposals;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-20 10:00', 'Europe/Rome'));
    $this->marco = registerFamily();
    $this->posta = insideBianchi(fn (): FinancialAccount => bankProposalsAccount('Banco Posta', '1111', connected: true));
});

/**
 * An account of the Bianchi.
 */
function bankProposalsAccount(string $name, ?string $lastFour = null, bool $connected = false): FinancialAccount
{
    $family = Family::query()->where('name', 'Bianchi')->sole()->id;

    return FinancialAccount::factory()->create([
        'family_id' => $family,
        'name' => $name,
        'last_four_digits' => $lastFour,
        'bank_connection_id' => $connected ? BankConnection::factory()->create(['family_id' => $family])->id : null,
    ]);
}

/**
 * A booked transaction of the Bianchi, on Banco Posta unless an account is given.
 *
 * @param  array<string, mixed>  $attributes  more columns
 */
function bankProposalsRow(string $text, string $amount, string $day, array $attributes = []): BankTransaction
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
 * A recorded expense of the Bianchi, on no account.
 *
 * @param  array<string, mixed>  $attributes  more columns
 */
function bankProposalsExpense(string $title, float $amount, string $date, array $attributes = []): Expense
{
    return Expense::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'title' => $title,
        'amount' => $amount,
        'fee_amount' => null,
        'date' => $date,
        'financial_account_id' => null,
        'planned_expense_id' => null,
        'organization_id' => null,
        ...$attributes,
    ]);
}

/**
 * A monthly planned expense of the Bianchi, without a rule.
 *
 * @param  array<string, mixed>  $attributes  more columns
 */
function bankProposalsPlanned(string $title, float $amount, string $start, array $attributes = []): PlannedExpense
{
    return PlannedExpense::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'title' => $title,
        'amount' => $amount,
        'fee_amount' => null,
        'is_estimated' => false,
        'is_recurring' => true,
        'interval' => 1,
        'interval_unit' => 'month',
        'start_date' => $start,
        'end_date' => null,
        'bank_match' => null,
        'organization_id' => null,
        'financial_account_id' => null,
        ...$attributes,
    ]);
}

/**
 * The proposals of some transactions, by id.
 *
 * @param  list<BankTransaction>  $rows  the transactions
 * @return array<int, BankProposalData>
 */
function bankProposalsFor(array $rows): array
{
    return resolve(BankProposals::class)->for(new Collection($rows));
}

test('proposes the single expense of the same amount recorded the same day', function (): void {
    insideBianchi(function (): void {
        $row = bankProposalsRow('PAGAMENTO POS IPMK - SCHIO 15/11/2026', '-23.90', '2026-11-15');
        $expense = bankProposalsExpense('Spesa Famila', 23.90, '2026-11-15');

        $proposal = bankProposalsFor([$row])[$row->id] ?? null;

        expect($proposal)->toBeInstanceOf(BankProposalData::class)
            ->and($proposal?->key)->toBe('expense:'.$expense->id)
            ->and($proposal?->type)->toBe('movement')
            ->and($proposal?->title)->toBe('Spesa Famila')
            ->and($proposal?->date)->toBe('2026-11-15')
            ->and($proposal?->amount)->toBe(23.90)
            ->and($proposal?->recorded)->toBeTrue();
    });
});

test('not one of eight days before', function (): void {
    insideBianchi(function (): void {
        $row = bankProposalsRow('PAGAMENTO POS IPMK - SCHIO', '-23.90', '2026-11-15');
        bankProposalsExpense('Spesa Famila', 23.90, '2026-11-07');

        expect(bankProposalsFor([$row]))->toBe([]);
    });
});

test('not one already linked', function (): void {
    insideBianchi(function (): void {
        $row = bankProposalsRow('PAGAMENTO POS IPMK - SCHIO', '-23.90', '2026-11-15');
        $expense = bankProposalsExpense('Spesa Famila', 23.90, '2026-11-15');
        bankProposalsRow('PAGAMENTO POS ALTRO', '-23.90', '2026-11-14', ['expense_id' => $expense->id, 'counterparty' => 'Altro']);

        expect(bankProposalsFor([$row]))->toBe([]);
    });
});

test('not one of another amount, another account or for a commission', function (): void {
    insideBianchi(function (): void {
        $other = bankProposalsAccount('Buddy Bank', '2222');
        $row = bankProposalsRow('PAGAMENTO POS IPMK - SCHIO', '-23.90', '2026-11-15');
        bankProposalsExpense('Altro importo', 23.80, '2026-11-15');
        bankProposalsExpense('Altro conto', 23.90, '2026-11-15', ['financial_account_id' => $other->id]);
        $fee = bankProposalsRow('COMMISSIONI SU ADDEBITO', '-0.40', '2026-11-15');
        bankProposalsExpense('Commissione', 0.40, '2026-11-15');

        expect(bankProposalsFor([$row, $fee]))->toBe([]);
    });
});

test('proposes the unpaid due date of the same amount', function (): void {
    insideBianchi(function (): void {
        $planned = bankProposalsPlanned('Retta danza', 45.00, '2026-01-18');
        $row = bankProposalsRow('BONIFICO A FAVORE DI ASD PASSO A DUE', '-45.00', '2026-11-17');

        $proposal = bankProposalsFor([$row])[$row->id] ?? null;

        expect($proposal?->key)->toBe('planned_expense:'.$planned->id.':2026-11-18')
            ->and($proposal?->type)->toBe('deadline')
            ->and($proposal?->title)->toBe('Retta danza November 2026')
            ->and($proposal?->date)->toBe('2026-11-18')
            ->and($proposal?->amount)->toBe(45.0)
            ->and($proposal?->recorded)->toBeFalse();
    });
});

test('prefers the recorded arrival to the due date it settles', function (): void {
    insideBianchi(function (): void {
        $planned = PlannedIncome::factory()->create([
            'family_id' => $this->marco->family_id,
            'title' => 'Assegno Unico',
            'amount' => 407.60,
            'is_estimated' => false,
            'is_recurring' => true,
            'interval' => 1,
            'interval_unit' => 'month',
            'start_date' => '2026-01-18',
            'end_date' => null,
            'bank_match' => null,
            'financial_account_id' => null,
        ]);
        $row = bankProposalsRow('BONIFICO SEPA Da INPS ASSEGNO UNICO', '407.60', '2026-11-17');

        expect(bankProposalsFor([$row])[$row->id]->key ?? null)->toBe('planned_income:'.$planned->id.':2026-11-18');

        $income = Income::factory()->create([
            'family_id' => $this->marco->family_id,
            'title' => 'Assegno Unico novembre 2026',
            'amount' => 407.60,
            'date' => '2026-11-18',
            'planned_income_id' => $planned->id,
            'occurrence_date' => '2026-11-18',
            'financial_account_id' => null,
        ]);

        $proposal = bankProposalsFor([$row])[$row->id] ?? null;

        expect($proposal?->key)->toBe('income:'.$income->id)
            ->and($proposal?->type)->toBe('movement')
            ->and($proposal?->title)->toBe('Assegno Unico novembre 2026');
    });
});

test('an estimated planned expense within 20% is proposed', function (): void {
    insideBianchi(function (): void {
        $water = bankProposalsPlanned('Acqua', 50.00, '2026-01-16', ['is_estimated' => true]);
        bankProposalsPlanned('Gas', 50.00, '2026-01-16', ['is_estimated' => true, 'end_date' => '2026-06-30']);
        $near = bankProposalsRow('ADDEBITO ACQUEDOTTO', '-55.00', '2026-11-16');
        $far = bankProposalsRow('ADDEBITO ALTRO', '-70.00', '2026-11-16', ['financial_account_id' => bankProposalsAccount('Buddy Bank', '2222', true)->id]);

        $proposals = bankProposalsFor([$near, $far]);

        expect($proposals[$near->id]->key ?? null)->toBe('planned_expense:'.$water->id.':2026-11-16')
            ->and($proposals)->not->toHaveKey($far->id);
    });
});

test('the name of the organization in the text gives the points that win', function (): void {
    insideBianchi(function (): void {
        $famila = Organization::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'Famila S.p.A.']);
        $row = bankProposalsRow('PAGAMENTO POS FAMILA SCHIO', '-23.90', '2026-11-15');
        bankProposalsExpense('Altro', 23.90, '2026-11-15');
        $named = bankProposalsExpense('Spesa Famila', 23.90, '2026-11-15', ['organization_id' => $famila->id]);

        expect(bankProposalsFor([$row])[$row->id]->key ?? null)->toBe('expense:'.$named->id);
    });
});

test('two equal candidates give nothing', function (): void {
    insideBianchi(function (): void {
        $row = bankProposalsRow('PAGAMENTO POS IPMK - SCHIO', '-23.90', '2026-11-15');
        bankProposalsExpense('Spesa Famila', 23.90, '2026-11-15');
        bankProposalsExpense('Farmacia', 23.90, '2026-11-15');

        expect(bankProposalsFor([$row]))->toBe([]);
    });
});

test('one expense is proposed to the closer of two movements, not to both', function (): void {
    insideBianchi(function (): void {
        $expense = bankProposalsExpense('Spesa Famila', 23.90, '2026-11-15');
        $far = bankProposalsRow('PAGAMENTO POS IPMK - SCHIO', '-23.90', '2026-11-19', ['counterparty' => 'IPMK']);
        $close = bankProposalsRow('PAGAMENTO POS FAMILA', '-23.90', '2026-11-14', ['counterparty' => 'Famila']);

        $proposals = bankProposalsFor([$far, $close]);

        expect($proposals[$close->id]->key ?? null)->toBe('expense:'.$expense->id)
            ->and($proposals)->not->toHaveKey($far->id);
    });
});

test('a rejected pair is not proposed again and the next candidate is', function (string $rejected): void {
    insideBianchi(function () use ($rejected): void {
        $first = bankProposalsExpense('Spesa Famila', 23.90, '2026-11-15');
        $next = bankProposalsExpense('Farmacia', 23.90, '2026-11-13');
        $row = bankProposalsRow('PAGAMENTO POS IPMK - SCHIO', '-23.90', '2026-11-15');

        expect(bankProposalsFor([$row])[$row->id]->key ?? null)->toBe('expense:'.$first->id);

        $key = 'expense:'.$first->id;
        $row->forceFill(['rejected_proposals' => $rejected === 'value' ? [$key] : [$key => true]])->save();
        $row = $row->refresh();

        expect(bankProposalsFor([$row])[$row->id]->key ?? null)->toBe('expense:'.$next->id);
    });
})->with([
    'as a value of the list' => ['value'],
    'as a key' => ['key'],
]);

test('proposes a transfer when the other account has the opposite half', function (): void {
    insideBianchi(function (): void {
        $buddy = bankProposalsAccount('Buddy Bank', '2222', connected: true);
        $out = bankProposalsRow('BONIFICO ISTANTANEO 123', '-200.00', '2026-11-12');
        $in = bankProposalsRow('BONIFICO IN ENTRATA 456', '200.00', '2026-11-13', ['financial_account_id' => $buddy->id]);

        $proposals = bankProposalsFor([$out, $in]);

        expect($proposals[$out->id]->key ?? null)->toBe('transfer:'.$buddy->id)
            ->and($proposals[$out->id]->type ?? null)->toBe('transfer')
            ->and($proposals[$out->id]->title ?? null)->toBe('Buddy Bank')
            ->and($proposals[$in->id]->key ?? null)->toBe('transfer:'.$this->posta->id);
    });
});

test('when the text names another account of the family', function (): void {
    insideBianchi(function (): void {
        $libretto = bankProposalsAccount('Libretto postale', '7788');
        $byName = bankProposalsRow('POSTAGIRO DA LIBRETTO POSTALE', '500.00', '2026-11-10');
        $byDigits = bankProposalsRow('GIRO A RAPPORTO 7788', '-80.00', '2026-11-11', ['counterparty' => 'Rapporto']);

        $proposals = bankProposalsFor([$byName, $byDigits]);

        expect($proposals[$byName->id]->key ?? null)->toBe('transfer:'.$libretto->id)
            ->and($proposals[$byDigits->id]->key ?? null)->toBe('transfer:'.$libretto->id);
    });
});

test('a movement of a group gets no proposal', function (): void {
    insideBianchi(function (): void {
        bankProposalsExpense('Luce', 72.40, '2026-11-05');
        $first = bankProposalsRow('SDD ENEL ENERGIA', '-72.40', '2026-11-05', ['counterparty' => 'Enel Energia']);
        $second = bankProposalsRow('SDD ENEL ENERGIA', '-68.10', '2026-10-05', ['counterparty' => 'Enel Energia']);

        expect(bankProposalsFor([$first, $second]))->toBe([]);
    });
});

test('uses a fixed number of queries for a block of 50', function (): void {
    insideBianchi(function (): void {
        $buddy = bankProposalsAccount('Buddy Bank', '2222', connected: true);
        $rows = [];

        foreach (range(1, 50) as $i) {
            $day = CarbonImmutable::parse('2026-11-19')->subDays($i % 40)->toDateString();
            $amount = 10 + $i;
            bankProposalsExpense('Spesa '.$i, $amount, $day);
            bankProposalsPlanned('Fissa '.$i, $amount + 0.5, '2026-01-'.str_pad((string) (1 + $i % 27), 2, '0', STR_PAD_LEFT));
            $rows[] = bankProposalsRow('PAGAMENTO NUMERO '.$i, $i % 5 === 0 ? $amount.'.50' : '-'.$amount, $day, ['counterparty' => 'Negozio '.$i, ...($i % 7 === 0 ? ['financial_account_id' => $buddy->id] : [])]);
        }

        $count = static function (array $block): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            bankProposalsFor($block);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };
        // A first call reads once what the services keep for the request (the features of the family)
        bankProposalsFor(array_slice($rows, 0, 1));
        $five = $count(array_slice($rows, 0, 5));
        $fifty = $count($rows);

        expect($fifty)->toBe($five)->toBeLessThan(20)
            ->and(count(bankProposalsFor($rows)))->toBeGreaterThan(20);
    });
});

test('the list to sort carries the proposal of a lone movement, the list All none', function (): void {
    config(['bank_feed.provider' => 'fake']);
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $expense = insideBianchi(function (): Expense {
        bankProposalsRow('PAGAMENTO POS IPMK - SCHIO', '-23.90', '2026-11-15');

        return bankProposalsExpense('Spesa Famila', 23.90, '2026-11-15');
    });

    $this->actingAs($this->marco)->get(route('budget.bank.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('rows.0.proposal.key', 'expense:'.$expense->id)
        ->where('rows.0.proposal.type', 'movement')
        ->where('rows.0.proposal.title', 'Spesa Famila'));
    $this->actingAs($this->marco)->get(route('budget.bank.index', ['show' => 'all']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('rows.0.proposal', null));
});
