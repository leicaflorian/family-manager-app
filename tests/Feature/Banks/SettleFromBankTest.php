<?php

declare(strict_types=1);

use App\Actions\Banks\SettleFromBank;
use App\Actions\Budget\RecordPayment;
use App\Enums\BankMatchSource;
use App\Enums\Role;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Services\Banks\BankMatcher;
use App\Services\Deadlines;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-05 07:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->marco = registerFamily();
    $this->marco->forceFill(['locale' => 'it'])->save();
    $this->laura = User::factory()->create(['family_id' => $this->marco->family_id, 'role' => Role::Member, 'locale' => 'en']);
    $this->bancoposta = FinancialAccount::factory()->create(['family_id' => $this->marco->family_id, 'name' => 'Conto BancoPosta']);
});

/**
 * A planned expense of the Bianchi on the BancoPosta account, monthly from a day.
 *
 * @param  array<string, mixed>  $attributes  more columns
 */
function bankSettlePlanned(string $title, string $rule, float $amount, string $start, array $attributes = []): PlannedExpense
{
    return PlannedExpense::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'title' => $title,
        'bank_match' => $rule,
        'amount' => $amount,
        'fee_amount' => null,
        'is_estimated' => false,
        'start_date' => $start,
        'financial_account_id' => FinancialAccount::query()->where('name', 'Conto BancoPosta')->sole()->id,
        ...$attributes,
    ]);
}

/**
 * A booked transaction of the Bianchi on the BancoPosta account.
 *
 * @param  array<string, mixed>  $attributes  more columns
 */
function bankSettleTransaction(string $text, string $amount, string $day, array $attributes = []): BankTransaction
{
    return BankTransaction::factory()->create([
        'family_id' => Family::query()->where('name', 'Bianchi')->sole()->id,
        'financial_account_id' => FinancialAccount::query()->where('name', 'Conto BancoPosta')->sole()->id,
        'text' => $text,
        'amount' => $amount,
        'booked_on' => $day,
        'value_on' => $day,
        ...$attributes,
    ]);
}

/** Settles a transaction with the planned item the matcher finds, as the rule does. */
function bankSettleRun(BankTransaction $transaction, PlannedExpense|PlannedIncome|null $planned = null): Expense|Income|null
{
    $planned ??= resolve(BankMatcher::class)->planned($transaction);
    expect($planned)->not->toBeNull();

    return resolve(SettleFromBank::class)->handle($transaction, $planned, BankMatchSource::Rule);
}

test('the Edison debit of 72,40 € pays Luce of 05/11/2026 with the title Luce novembre 2026, the bank account and the booking date', function (): void {
    insideBianchi(function (): void {
        $luce = bankSettlePlanned('Luce', 'SDD.*EDISON ENERGIA', 72.0, '2026-01-05', ['financial_account_id' => null]);
        $transaction = bankSettleTransaction('ADDEBITO DIRETTO SDD EDISON ENERGIA SPA FATT. 0123456789', '-72.40', '2026-11-05');

        $expense = bankSettleRun($transaction, $luce);

        expect($expense)->toBeInstanceOf(Expense::class)
            ->and($expense->title)->toBe('Luce novembre 2026')
            ->and((float) $expense->amount)->toBe(72.4)
            ->and($expense->fee_amount)->toBeNull()
            ->and($expense->date?->toDateString())->toBe('2026-11-05')
            ->and($expense->occurrence_date?->toDateString())->toBe('2026-11-05')
            ->and($expense->planned_expense_id)->toBe($luce->id)
            ->and($expense->financial_account_id)->toBe($this->bancoposta->id);

        $transaction->refresh();
        expect($transaction->expense_id)->toBe($expense->id)
            ->and($transaction->income_id)->toBeNull()
            ->and($transaction->matched_by)->toBe(BankMatchSource::Rule)
            ->and(resolve(Deadlines::class)->find($luce, CarbonImmutable::parse('2026-11-05'))->isPaid())->toBeTrue();
    });
});

test('the rent of 651,00 € becomes 651,00 € of which 1,00 € of fee', function (): void {
    insideBianchi(function (): void {
        bankSettlePlanned('Affitto', 'BONIFICO.*ROSSI MARIO.*AFFITTO', 650.0, '2026-01-01', ['fee_amount' => 1.0]);

        $expense = bankSettleRun(bankSettleTransaction('BONIFICO A ROSSI MARIO CAUSALE AFFITTO NOVEMBRE', '-651.00', '2026-11-02'));

        expect($expense)->toBeInstanceOf(Expense::class)
            ->and($expense->title)->toBe('Affitto novembre 2026')
            ->and((float) $expense->amount)->toBe(651.0)
            ->and((float) $expense->fee_amount)->toBe(1.0);
    });
});

test('a fee as big as the amount is left out', function (): void {
    insideBianchi(function (): void {
        bankSettlePlanned('Commissioni', 'CANONE CONTO', 0.0, '2026-01-01', ['fee_amount' => 3.0]);

        $expense = bankSettleRun(bankSettleTransaction('CANONE CONTO NOVEMBRE', '-3.00', '2026-11-01'));

        expect((float) $expense?->amount)->toBe(3.0)
            ->and($expense?->fee_amount)->toBeNull();
    });
});

test('a salary settles the planned income', function (): void {
    insideBianchi(function (): void {
        $salary = PlannedIncome::factory()->create([
            'family_id' => $this->marco->family_id,
            'title' => 'Stipendio di Marco',
            'bank_match' => 'STUDIO RINALDI.*(STIPENDIO|EMOLUMENTI)',
            'amount' => 1850,
            'is_estimated' => false,
            'start_date' => '2026-01-27',
            'financial_account_id' => null,
        ]);
        $transaction = bankSettleTransaction('BONIFICO DA STUDIO RINALDI EMOLUMENTI OTTOBRE', '1850.00', '2026-10-27');

        $income = bankSettleRun($transaction, $salary);

        expect($income)->toBeInstanceOf(Income::class)
            ->and($income->title)->toBe('Stipendio di Marco ottobre 2026')
            ->and((float) $income->amount)->toBe(1850.0)
            ->and($income->planned_income_id)->toBe($salary->id)
            ->and($income->financial_account_id)->toBe($this->bancoposta->id)
            ->and($transaction->refresh()->income_id)->toBe($income->id)
            ->and($transaction->expense_id)->toBeNull();
    });
});

test('the documents waiting on the planned expense move to the payment', function (): void {
    insideBianchi(function (): void {
        $luce = bankSettlePlanned('Luce', 'EDISON', 72.0, '2026-01-05');
        $bill = Document::factory()->create(['family_id' => $luce->family_id, 'planned_expense_id' => $luce->id, 'date' => '2026-10-28']);

        $expense = bankSettleRun(bankSettleTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05'));

        expect($expense?->documents()->pluck('documents.id')->all())->toBe([$bill->id]);
    });
});

test('a payment already recorded by hand is linked, not doubled', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-21 07:00', 'Europe/Rome'));

    insideBianchi(function (): void {
        $insurance = bankSettlePlanned('Assicurazione auto', 'UNIPOLSAI', 418.0, '2025-11-21', ['interval_unit' => 'year']);
        $deadline = resolve(Deadlines::class)->find($insurance, CarbonImmutable::parse('2026-11-21'));
        $byHand = resolve(RecordPayment::class)->handle($deadline, CarbonImmutable::parse('2026-11-20'), 418.0, null, null);
        $transaction = bankSettleTransaction('PAGAMENTO POS UNIPOLSAI', '-418.00', '2026-11-21');

        $linked = bankSettleRun($transaction, $insurance);

        expect($linked?->is($byHand))->toBeTrue()
            ->and(Expense::query()->where('planned_expense_id', $insurance->id)->count())->toBe(1)
            ->and($transaction->refresh()->expense_id)->toBe($byHand->id)
            ->and($transaction->matched_by)->toBe(BankMatchSource::Duplicate)
            ->and(ReminderNotice::query()->count())->toBe(0);
    });
});

test('a due date paid in the meantime is linked, with no second expense and no exception', function (): void {
    insideBianchi(function (): void {
        $luce = bankSettlePlanned('Luce', 'EDISON', 72.0, '2026-11-05');
        $transaction = bankSettleTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05');
        $planned = resolve(BankMatcher::class)->planned($transaction);
        expect($planned?->is($luce))->toBeTrue();

        // Somebody records the payment by hand, with another amount, after the rule chose Luce
        $byHand = resolve(RecordPayment::class)->handle(resolve(Deadlines::class)->find($luce, CarbonImmutable::parse('2026-11-05')), CarbonImmutable::parse('2026-11-04'), 70.0, null, null);

        expect(bankSettleRun($transaction, $planned)?->is($byHand))->toBeTrue()
            ->and(Expense::query()->where('planned_expense_id', $luce->id)->count())->toBe(1)
            ->and($transaction->refresh()->expense_id)->toBe($byHand->id)
            ->and($transaction->matched_by)->toBe(BankMatchSource::Duplicate)
            ->and(ReminderNotice::query()->count())->toBe(0);
    });
});

test('November paid by hand with another amount is linked, and the open March is not paid with its debit', function (): void {
    insideBianchi(function (): void {
        $luce = bankSettlePlanned('Luce', 'EDISON', 72.0, '2026-01-05');
        $byHand = resolve(RecordPayment::class)->handle(resolve(Deadlines::class)->find($luce, CarbonImmutable::parse('2026-11-05')), CarbonImmutable::parse('2026-11-05'), 72.0, null, null);
        $transaction = bankSettleTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-06');

        expect(bankSettleRun($transaction, $luce)?->is($byHand))->toBeTrue()
            ->and(Expense::query()->where('planned_expense_id', $luce->id)->count())->toBe(1)
            ->and($transaction->refresh()->matched_by)->toBe(BankMatchSource::Duplicate)
            ->and(resolve(Deadlines::class)->find($luce, CarbonImmutable::parse('2026-03-05'))->isPaid())->toBeFalse()
            ->and(ReminderNotice::query()->count())->toBe(0);
    });
});

test('a paid due date whose payment already has a bank transaction leaves the transaction unsorted', function (): void {
    insideBianchi(function (): void {
        $luce = bankSettlePlanned('Luce', 'EDISON', 72.0, '2026-01-05');
        $first = bankSettleTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05');
        bankSettleRun($first, $luce);
        $second = bankSettleTransaction('SDD EDISON ENERGIA RETTIFICA', '-12.00', '2026-11-08');

        expect(bankSettleRun($second, $luce))->toBeNull()
            ->and($second->refresh()->expense_id)->toBeNull()
            ->and($second->matched_by)->toBeNull()
            ->and(Expense::query()->where('planned_expense_id', $luce->id)->count())->toBe(1);
    });
});

test('a payment written meanwhile without the lock is linked, whatever its amount', function (float $byHand): void {
    insideBianchi(function () use ($byHand): void {
        $luce = bankSettlePlanned('Luce', 'EDISON', 72.0, '2026-11-05');
        $transaction = bankSettleTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05');
        $paid = (object) ['done' => false];

        // The other payment lands after the due date was chosen, right before the payment starts writing (level 1 is
        // the transaction of the test, level 2 the one of the settling): the payment finds the due date paid
        DB::connection()->beforeStartingTransaction(function (Connection $connection) use ($luce, $byHand, $paid): void {
            if ($paid->done || $connection->transactionLevel() < 2) {
                return;
            }

            $paid->done = true;
            Expense::factory()->create([
                'family_id' => $luce->family_id, 'planned_expense_id' => $luce->id, 'occurrence_date' => '2026-11-05',
                'amount' => $byHand, 'date' => '2026-11-05',
            ]);
        });

        $result = bankSettleRun($transaction, $luce);

        expect($paid->done)->toBeTrue()
            ->and(Expense::query()->where('planned_expense_id', $luce->id)->count())->toBe(1)
            ->and($transaction->refresh()->matched_by)->toBe(BankMatchSource::Duplicate)
            ->and($result?->is(Expense::query()->where('planned_expense_id', $luce->id)->sole()))->toBeTrue()
            ->and(ReminderNotice::query()->count())->toBe(0);
    });
})->with([
    'with the same amount' => [72.40],
    'with another amount' => [70.00],
]);

test('a transaction settled twice in a row pays once', function (): void {
    insideBianchi(function (): void {
        $luce = bankSettlePlanned('Luce', 'EDISON', 72.0, '2026-01-05');
        $transaction = bankSettleTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05');

        $first = bankSettleRun($transaction, $luce);
        $second = bankSettleRun($transaction, $luce);

        expect($first)->toBeInstanceOf(Expense::class)
            ->and($second)->toBeNull()
            ->and(Expense::query()->where('planned_expense_id', $luce->id)->count())->toBe(1)
            ->and(ReminderNotice::query()->where('user_id', $this->marco->id)->count())->toBe(1);
    });
});

test('a declined transaction or one put aside pays nothing', function (array $state): void {
    insideBianchi(function () use ($state): void {
        $luce = bankSettlePlanned('Luce', 'EDISON', 72.0, '2026-01-05');

        expect(bankSettleRun(bankSettleTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05', $state), $luce))->toBeNull()
            ->and(Expense::query()->count())->toBe(0);
    });
})->with([
    'declined' => [['declined_at' => '2026-11-05 08:00:00']],
    'ignored' => [['ignored_at' => '2026-11-05 08:00:00']],
]);

test('everyone in the family gets a bell notice, with the second line only when the amount is far from the planned one', function (): void {
    insideBianchi(function (): void {
        bankSettlePlanned('Luce', 'EDISON', 72.0, '2026-01-05');
        bankSettlePlanned('Gas', 'ENI PLENITUDE', 45.0, '2026-01-10');
        bankSettlePlanned('Acqua', 'ACQUEDOTTO', 30.0, '2026-01-15', ['is_estimated' => true]);

        $luce = bankSettleTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05');
        bankSettleRun($luce);
        bankSettleRun(bankSettleTransaction('SDD ENI PLENITUDE', '-72.40', '2026-11-10'));
        bankSettleRun(bankSettleTransaction('SDD ACQUEDOTTO', '-72.40', '2026-11-15'));

        $marco = ReminderNotice::query()->where('user_id', $this->marco->id)->orderBy('id')->get();
        $laura = ReminderNotice::query()->where('user_id', $this->laura->id)->orderBy('id')->get();

        expect($marco)->toHaveCount(3)
            ->and($laura)->toHaveCount(3)
            ->and($marco[0]->bank_transaction_id)->toBe($luce->id)
            ->and($marco[0]->reminder_id)->toBeNull()
            ->and($marco[0]->received_mail_id)->toBeNull()
            ->and($marco[0]->sent_at->toDateTimeString())->toBe(now()->toDateTimeString())
            ->and($marco[0]->read_at)->toBeNull()
            ->and($marco[0]->lines)->toBe(["72,40\u{A0}€ dal Conto BancoPosta"])
            ->and($laura[0]->lines)->toBe(["72,40\u{A0}€ from Conto BancoPosta"])
            ->and($marco[1]->lines)->toBe(["72,40\u{A0}€ dal Conto BancoPosta", "72,40\u{A0}€ invece di 45,00\u{A0}€"])
            ->and($laura[1]->lines)->toBe(["72,40\u{A0}€ from Conto BancoPosta", "72,40\u{A0}€ instead of 45,00\u{A0}€"])
            ->and($marco[2]->lines)->toBe(["72,40\u{A0}€ dal Conto BancoPosta"]);
    });
});

test('a person who assigns a declined transaction settles it, a rule still does not', function (): void {
    insideBianchi(function (): void {
        $luce = bankSettlePlanned('Luce', 'EDISON', 72.0, '2026-01-05');
        $transaction = bankSettleTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05', ['declined_at' => '2026-11-05 08:00:00']);

        expect(resolve(SettleFromBank::class)->handle($transaction, $luce, BankMatchSource::Rule))->toBeNull()
            ->and(resolve(SettleFromBank::class)->handle($transaction, $luce, BankMatchSource::Assigned))->toBeInstanceOf(Expense::class)
            ->and($transaction->refresh()->matched_by)->toBe(BankMatchSource::Assigned);
    });
});

test('a due date that is paid and moved away while the settling starts leaves the transaction to sort without an error', function (): void {
    insideBianchi(function (): void {
        $luce = bankSettlePlanned('Luce', 'EDISON', 72.0, '2026-11-05');
        $transaction = bankSettleTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05');
        $done = (object) ['done' => false];

        // Right before the payment writes, somebody pays the due date and moves the start of the planned item: the due date is not found any more
        DB::connection()->beforeStartingTransaction(function (Connection $connection) use ($luce, $done): void {
            if ($done->done || $connection->transactionLevel() < 2) {
                return;
            }

            $done->done = true;
            Expense::factory()->create(['family_id' => $luce->family_id, 'planned_expense_id' => $luce->id, 'occurrence_date' => '2026-11-05', 'amount' => 72.0, 'date' => '2026-11-05']);
            $luce->forceFill(['start_date' => '2027-01-05'])->save();
        });

        expect(resolve(SettleFromBank::class)->handle($transaction, $luce, BankMatchSource::Assigned))->toBeNull()
            ->and($done->done)->toBeTrue()
            ->and($transaction->refresh()->expense_id)->toBeNull();
    });
});

test('who assigns the transaction gets no bell notice, the others do', function (): void {
    $this->actingAs($this->marco);

    insideBianchi(function (): void {
        $luce = bankSettlePlanned('Luce', 'EDISON', 72.0, '2026-01-05');
        resolve(SettleFromBank::class)->handle(bankSettleTransaction('SDD EDISON ENERGIA', '-72.40', '2026-11-05'), $luce, BankMatchSource::Assigned);

        expect(ReminderNotice::query()->where('user_id', $this->marco->id)->count())->toBe(0)
            ->and(ReminderNotice::query()->where('user_id', $this->laura->id)->count())->toBe(1);
    });
});
