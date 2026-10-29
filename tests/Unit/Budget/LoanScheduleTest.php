<?php

declare(strict_types=1);

use App\Enums\ExceptionType;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Services\LoanSchedule;
use App\Support\LoanFigures;
use App\Support\LoanTerms;
use App\Support\ScheduleException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The values of an invented loan as the form sends them: 48 monthly instalments of 250.00 from 5 January 2026,
 * 11000.00 financed. Named with a prefix because Pest test files share global functions.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function loanSchedValues(array $overrides = []): array
{
    return [
        'name' => 'Test car loan',
        'status' => 'active',
        'start_date' => '2026-01-05',
        'end_date' => null,
        'installments_count' => 48,
        'installment_amount' => 250,
        'final_installment_amount' => null,
        'total_amount' => 11000,
        'organization_id' => 3,
        'financial_account_id' => 2,
        'person_id' => 5,
        ...$overrides,
    ];
}

/**
 * The terms of the invented loan.
 *
 * @param  array<string, mixed>  $overrides
 */
function loanSchedTerms(array $overrides = []): LoanTerms
{
    return LoanTerms::fromForm(loanSchedValues($overrides));
}

/** An exception of the instalment of `$on`. */
function loanSchedException(string $on, ExceptionType $type, float|string|null $amount = null, ?string $date = null): ScheduleException
{
    return new ScheduleException(
        CarbonImmutable::parse($on),
        $type,
        $amount === null ? null : (float) $amount,
        $date === null ? null : CarbonImmutable::parse($date),
    );
}

/** The `derive` of the invented loan, with `today`. */
function loanSchedDerive(array $overrides = [], array $exceptions = [], string $today = '2026-04-10'): LoanFigures
{
    return (new LoanSchedule)->derive(loanSchedTerms($overrides), $exceptions, CarbonImmutable::parse($today));
}

/**
 * The invented loan as a model, never saved.
 *
 * @param  array<string, mixed>  $overrides
 */
function loanSchedModel(array $overrides = []): Loan
{
    return (new Loan)->forceFill(['id' => 7, ...loanSchedValues($overrides)]);
}

/** The dates of a list of instalments as text. */
function loanSchedDates(array $list): array
{
    return array_map(fn (array $i): ?string => $i['date']?->toDateString(), $list);
}

/** The amounts of a list of instalments. */
function loanSchedAmounts(array $list): array
{
    return array_map(fn (array $i): ?float => $i['amount'], $list);
}

describe('installmentsBetween', function (): void {
    $between = fn (string $a, string $b): ?int => (new LoanSchedule)->installmentsBetween(CarbonImmutable::parse($a), CarbonImmutable::parse($b));

    test('first date to last date of the fake loan is 48 instalments', function () use ($between): void {
        expect($between('2026-01-05', '2029-12-05'))->toBe(48);
    });

    test('one day before the last instalment date is 47', function () use ($between): void {
        expect($between('2026-01-05', '2029-12-04'))->toBe(47);
    });

    test('15 November to 15 October of the next year is 12', function () use ($between): void {
        expect($between('2026-11-15', '2027-10-15'))->toBe(12);
    });

    test('the same date is a single instalment', function () use ($between): void {
        expect($between('2026-01-05', '2026-01-05'))->toBe(1);
    });

    test('an end before the start is null', function () use ($between): void {
        expect($between('2026-01-05', '2026-01-04'))->toBeNull();
    });
});

describe('installments', function (): void {
    test('48 rows, the first on 2026-01-05 and the last on 2029-12-05, all of 250', function (): void {
        $list = (new LoanSchedule)->installments(loanSchedTerms(), []);

        expect($list)->toHaveCount(48)
            ->and($list[0]['date']?->toDateString())->toBe('2026-01-05')
            ->and($list[0]['amount'])->toBe(250.0)
            ->and($list[47]['date']?->toDateString())->toBe('2029-12-05')
            ->and(array_unique(loanSchedAmounts($list)))->toBe([250.0]);
    });

    test('a start on the 31st keeps the end of the month', function (): void {
        $list = (new LoanSchedule)->installments(loanSchedTerms(['start_date' => '2026-01-31', 'installments_count' => 3]), []);

        expect(loanSchedDates($list))->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
    });

    test('the final balloon payment is the last instalment', function (): void {
        $list = (new LoanSchedule)->installments(loanSchedTerms(['installments_count' => 3, 'final_installment_amount' => 1000]), []);

        expect(loanSchedAmounts($list))->toBe([250.0, 250.0, 1000.0]);
    });

    test('a date override moves its instalment', function (): void {
        $list = (new LoanSchedule)->installments(loanSchedTerms(['installments_count' => 3]), [
            loanSchedException('2026-02-05', ExceptionType::DateOverride, null, '2026-02-20'),
        ]);

        expect(loanSchedDates($list))->toBe(['2026-01-05', '2026-02-20', '2026-03-05'])
            ->and($list[1]['amount'])->toBe(250.0);
    });

    test('a skip exception leaves the instalment without a date, still to pay', function (): void {
        $list = (new LoanSchedule)->installments(loanSchedTerms(['installments_count' => 3]), [
            loanSchedException('2026-02-05', ExceptionType::Skip),
        ]);

        expect(loanSchedDates($list))->toBe(['2026-01-05', null, '2026-03-05'])
            ->and(loanSchedAmounts($list))->toBe([250.0, 250.0, 250.0]);
    });

    test('an amount override replaces the amount of its instalment, even the balloon one', function (): void {
        $list = (new LoanSchedule)->installments(loanSchedTerms(['installments_count' => 3, 'final_installment_amount' => 1000]), [
            loanSchedException('2026-01-05', ExceptionType::AmountOverride, '180.50'),
            loanSchedException('2026-03-05', ExceptionType::AmountOverride, 900),
        ]);

        expect(loanSchedAmounts($list))->toBe([180.5, 250.0, 900.0]);
    });

    test('an exception on a date that is not an instalment changes nothing', function (): void {
        $list = (new LoanSchedule)->installments(loanSchedTerms(['installments_count' => 2]), [
            loanSchedException('2026-01-06', ExceptionType::Skip),
        ]);

        expect(loanSchedDates($list))->toBe(['2026-01-05', '2026-02-05'])
            ->and(loanSchedAmounts($list))->toBe([250.0, 250.0]);
    });

    test('without start date or number of instalments there are none', function (): void {
        $schedule = new LoanSchedule;

        expect($schedule->installments(loanSchedTerms(['start_date' => null]), []))->toBe([])
            ->and($schedule->installments(loanSchedTerms(['installments_count' => null]), []))->toBe([])
            ->and($schedule->installments(loanSchedTerms(['installments_count' => 0]), []))->toBe([]);
    });

    test('the number of instalments is capped at 1200', function (): void {
        expect((new LoanSchedule)->installments(loanSchedTerms(['installments_count' => 5000]), []))->toHaveCount(1200);
    });

    test('without the instalment amount the amounts are null', function (): void {
        $list = (new LoanSchedule)->installments(loanSchedTerms(['installments_count' => 2, 'installment_amount' => null]), []);

        expect(loanSchedAmounts($list))->toBe([null, null]);
    });
});

describe('totals', function (): void {
    test('the fake loan: 48 x 250 = 12000 to pay, cost 12000 - 11000 = 1000', function (): void {
        $figures = loanSchedDerive();

        expect([$figures->totalToPay, $figures->cost])->toBe([12000.0, 1000.0]);
    });

    test('12 instalments of 99.90 for 1100 financed: 1198.80 and 98.80', function (): void {
        $figures = loanSchedDerive(['installments_count' => 12, 'installment_amount' => 99.9, 'total_amount' => 1100]);

        expect([$figures->totalToPay, $figures->cost])->toBe([1198.8, 98.8]);
    });

    test('with a balloon payment: 47 x 250 + 1000 = 12750, cost 1750', function (): void {
        $figures = loanSchedDerive(['final_installment_amount' => 1000]);

        expect([$figures->totalToPay, $figures->cost])->toBe([12750.0, 1750.0]);
    });

    test('at zero interest the cost is 0', function (): void {
        $figures = loanSchedDerive(['total_amount' => 12000]);

        expect([$figures->totalToPay, $figures->cost])->toBe([12000.0, 0.0]);
    });

    test('values arriving as strings are read as numbers', function (): void {
        $figures = loanSchedDerive(['installments_count' => '48', 'installment_amount' => '250.00', 'total_amount' => '11000.00']);

        expect([$figures->totalToPay, $figures->cost])->toBe([12000.0, 1000.0]);
    });

    test('without the amount financed the cost is null but the total is there', function (): void {
        $figures = loanSchedDerive(['total_amount' => null]);

        expect([$figures->totalToPay, $figures->cost])->toBe([12000.0, null]);
    });

    test('without the instalment amount both are null', function (): void {
        $figures = loanSchedDerive(['installment_amount' => null]);

        expect([$figures->totalToPay, $figures->cost])->toBe([null, null]);
    });

    test('without the number of instalments both are null', function (): void {
        foreach ([null, 0] as $count) {
            $figures = loanSchedDerive(['installments_count' => $count]);

            expect([$figures->totalToPay, $figures->cost])->toBe([null, null]);
        }
    });

    test('an amount override counts in the total: 47 x 250 + 100 = 11850, cost 850', function (): void {
        $figures = loanSchedDerive([], [loanSchedException('2026-06-05', ExceptionType::AmountOverride, 100)]);

        expect([$figures->totalToPay, $figures->cost])->toBe([11850.0, 850.0]);
    });

    test('a skipped or moved instalment keeps its amount in the total', function (): void {
        $figures = loanSchedDerive([], [
            loanSchedException('2026-03-05', ExceptionType::Skip),
            loanSchedException('2026-04-05', ExceptionType::DateOverride, null, '2026-04-20'),
        ]);

        expect([$figures->totalToPay, $figures->cost])->toBe([12000.0, 1000.0]);
    });

    test('an amount override on the balloon payment replaces it: 47 x 250 + 500 = 12250', function (): void {
        $figures = loanSchedDerive(['final_installment_amount' => 1000], [loanSchedException('2029-12-05', ExceptionType::AmountOverride, 500)]);

        expect($figures->totalToPay)->toBe(12250.0);
    });

    test('without the start date there are no instalments to sum: the total is the instalment by the count', function (): void {
        $figures = loanSchedDerive(['start_date' => null]);

        expect([$figures->totalToPay, $figures->cost])->toBe([12000.0, 1000.0]);
    });
});

describe('derive', function (): void {
    test('on 2026-04-10: 4 paid, 44 left, residual 44 x 250 = 11000, last date 2029-12-05', function (): void {
        $figures = loanSchedDerive();

        expect($figures->endDate?->toDateString())->toBe('2029-12-05')
            ->and($figures->installmentsCount)->toBe(48)
            ->and($figures->totalToPay)->toBe(12000.0)
            ->and($figures->cost)->toBe(1000.0)
            ->and($figures->installmentsPaid)->toBe(4)
            ->and($figures->installmentsLeft)->toBe(44)
            ->and($figures->residual)->toBe(11000.0);
    });

    test('an instalment due today counts as paid: the calendar decides, not the payments', function (): void {
        $figures = loanSchedDerive(today: '2026-04-05');

        expect($figures->installmentsPaid)->toBe(4)
            ->and($figures->installmentsLeft)->toBe(44)
            ->and(loanSchedDerive(today: '2026-04-04')->installmentsPaid)->toBe(3);
    });

    test('before the first instalment nothing is paid and the residual is the total', function (): void {
        $figures = loanSchedDerive(today: '2026-01-04');

        expect([$figures->installmentsPaid, $figures->installmentsLeft, $figures->residual])->toBe([0, 48, 12000.0]);
    });

    test('after the last instalment everything is paid and nothing is left', function (): void {
        $figures = loanSchedDerive(today: '2029-12-05');

        expect([$figures->installmentsPaid, $figures->installmentsLeft, $figures->residual])->toBe([48, 0, 0.0]);
    });

    test('an end date written by hand stays', function (): void {
        $figures = loanSchedDerive(['end_date' => '2030-01-05']);

        expect($figures->endDate?->toDateString())->toBe('2030-01-05')
            ->and($figures->installmentsLeft)->toBe(44);
    });

    test('the number of instalments is worked out from the two dates when empty', function (): void {
        $figures = loanSchedDerive(['installments_count' => null, 'end_date' => '2029-12-05']);

        expect($figures->installmentsCount)->toBe(48)
            ->and($figures->totalToPay)->toBe(12000.0)
            ->and($figures->cost)->toBe(1000.0)
            ->and($figures->installmentsPaid)->toBe(4)
            ->and($figures->installmentsLeft)->toBe(44)
            ->and($figures->residual)->toBe(11000.0);
    });

    test('the balloon payment is in the residual: 42 x 250 + 1000 = 11500 on 2026-05-10', function (): void {
        $figures = loanSchedDerive(['final_installment_amount' => 1000], today: '2026-05-10');

        expect($figures->installmentsPaid)->toBe(5)
            ->and($figures->installmentsLeft)->toBe(43)
            ->and($figures->residual)->toBe(11500.0)
            ->and($figures->totalToPay)->toBe(12750.0);
    });

    test('a skipped instalment is still to pay: 3 paid, 45 left, residual 45 x 250 = 11250', function (): void {
        $figures = loanSchedDerive([], [loanSchedException('2026-02-05', ExceptionType::Skip)]);

        expect($figures->installmentsPaid)->toBe(3)
            ->and($figures->installmentsLeft)->toBe(45)
            ->and($figures->residual)->toBe(11250.0);
    });

    test('an instalment moved after today is still to pay', function (): void {
        $figures = loanSchedDerive([], [loanSchedException('2026-02-05', ExceptionType::DateOverride, null, '2026-05-01')]);

        expect($figures->installmentsPaid)->toBe(3)
            ->and($figures->installmentsLeft)->toBe(45);
    });

    test('a different amount on a left instalment changes the residual: 43 x 250 + 100 = 10850', function (): void {
        $figures = loanSchedDerive([], [loanSchedException('2026-06-05', ExceptionType::AmountOverride, 100)]);

        expect($figures->installmentsLeft)->toBe(44)
            ->and($figures->residual)->toBe(10850.0)
            ->and($figures->totalToPay)->toBe(11850.0)
            ->and($figures->cost)->toBe(850.0);
    });

    test('a paid off loan has everything paid and nothing left', function (): void {
        $figures = loanSchedDerive(['status' => 'paid_off']);

        expect([$figures->installmentsPaid, $figures->installmentsLeft, $figures->residual])->toBe([48, 0, 0.0]);
    });

    test('without the instalment amount the residual is null', function (): void {
        $figures = loanSchedDerive(['installment_amount' => null, 'installments_count' => 3], today: '2026-01-01');

        expect($figures->endDate?->toDateString())->toBe('2026-03-05')
            ->and($figures->totalToPay)->toBeNull()
            ->and($figures->cost)->toBeNull()
            ->and($figures->installmentsPaid)->toBe(0)
            ->and($figures->installmentsLeft)->toBe(3)
            ->and($figures->residual)->toBeNull();
    });

    test('without start date or count the progress is null', function (): void {
        $figures = loanSchedDerive(['start_date' => null, 'installments_count' => null]);

        expect($figures->endDate)->toBeNull()
            ->and($figures->totalToPay)->toBeNull()
            ->and($figures->cost)->toBeNull()
            ->and($figures->installmentsPaid)->toBeNull()
            ->and($figures->installmentsLeft)->toBeNull()
            ->and($figures->residual)->toBeNull();
    });

    test('Finanziamento Panda: 8000 financed, 48 x 189 from 10/03/2024', function (): void {
        $figures = loanSchedDerive([
            'name' => 'Finanziamento Panda', 'start_date' => '2024-03-10', 'installments_count' => 48,
            'installment_amount' => 189, 'total_amount' => 8000,
        ], today: '2026-10-26');

        expect($figures->endDate?->toDateString())->toBe('2028-02-10')
            ->and($figures->totalToPay)->toBe(9072.0)
            ->and($figures->cost)->toBe(1072.0)
            ->and($figures->installmentsPaid)->toBe(32)
            ->and($figures->residual)->toBe(3024.0);
    });

    test('the instalment of the 10th counts as paid on 2026-11-10 (33 paid)', function (): void {
        $figures = loanSchedDerive([
            'start_date' => '2024-03-10', 'installments_count' => 48, 'installment_amount' => 189, 'total_amount' => 8000,
        ], today: '2026-11-10');

        expect($figures->installmentsPaid)->toBe(33);
    });
});

describe('shown (the form worked out while typing)', function (): void {
    $june = [loanSchedException('2026-06-15', ExceptionType::AmountOverride, 40)];
    $shown = function (array $overrides, array $exceptions): LoanFigures {
        $values = ['start_date' => '2026-01-15', 'end_date' => null, 'installments_count' => 12, 'installment_amount' => 100,
            'final_installment_amount' => null, 'total_amount' => 1100, 'status' => 'active', ...$overrides];

        return (new LoanSchedule)->derive(LoanTerms::fromForm($values), $exceptions, CarbonImmutable::parse('2026-10-13'));
    };

    test('without exceptions: 12 x 100 = 1200 to pay, cost 100', function () use ($shown): void {
        $figures = $shown([], []);

        expect([$figures->totalToPay, $figures->cost])->toBe([1200.0, 100.0]);
    });

    test('an amount exception counts: 11 x 100 + 40 = 1140, cost 40', function () use ($shown, $june): void {
        $figures = $shown([], $june);

        expect([$figures->totalToPay, $figures->cost])->toBe([1140.0, 40.0]);
    });

    test('the instalment typed in the form applies to the others: 11 x 110 + 40 = 1250, cost 150', function () use ($shown, $june): void {
        $figures = $shown(['installment_amount' => 110], $june);

        expect([$figures->totalToPay, $figures->cost])->toBe([1250.0, 150.0]);
    });

    test('with 5 instalments the exception of June is on no instalment: 500', function () use ($shown, $june): void {
        expect($shown(['installments_count' => 5], $june)->totalToPay)->toBe(500.0);
    });

    test('a start moved to the 20th leaves the exception of the 15th on no instalment: 1200', function () use ($shown, $june): void {
        expect($shown(['start_date' => '2026-01-20'], $june)->totalToPay)->toBe(1200.0);
    });

    test('without the number of instalments it comes from the two dates', function () use ($shown, $june): void {
        expect($shown(['installments_count' => null, 'end_date' => '2026-12-15'], $june)->totalToPay)->toBe(1140.0);
    });

    test('without the start date: the instalment by the count, the balloon payment last: 11 x 100 + 500', function () use ($shown, $june): void {
        expect($shown(['start_date' => null, 'final_installment_amount' => 500], $june)->totalToPay)->toBe(1600.0);
    });

    test('values arriving as text are read as numbers', function () use ($shown, $june): void {
        $figures = $shown(['installment_amount' => '100.00', 'installments_count' => '12', 'total_amount' => '1100.00'], $june);

        expect([$figures->totalToPay, $figures->cost])->toBe([1140.0, 40.0]);
    });

    test('without the instalment amount both are empty', function () use ($shown, $june): void {
        $figures = $shown(['installment_amount' => null], $june);

        expect([$figures->totalToPay, $figures->cost])->toBe([null, null]);
    });

    test('without the amount financed the cost is empty but the total is there', function () use ($shown, $june): void {
        $figures = $shown(['total_amount' => null], $june);

        expect([$figures->totalToPay, $figures->cost])->toBe([1140.0, null]);
    });

    test('a total below the amount financed gives a negative cost', function () use ($shown, $june): void {
        expect($shown(['total_amount' => 1300], $june)->cost)->toBe(-160.0);
    });

    test('a new, empty form shows nothing', function (): void {
        $figures = (new LoanSchedule)->derive(LoanTerms::fromForm([]), [], CarbonImmutable::parse('2026-10-13'));

        expect([$figures->totalToPay, $figures->cost])->toBe([null, null]);
    });
});

describe('LoanTerms', function (): void {
    test('an empty or non positive value is null, a date with a time part is the day', function (): void {
        $terms = LoanTerms::fromForm([
            'installment_amount' => '', 'final_installment_amount' => 0, 'total_amount' => '-5',
            'installments_count' => 'abc', 'start_date' => '2026-01-05T00:00:00.000Z', 'end_date' => '',
        ]);

        expect($terms->installment)->toBeNull()
            ->and($terms->finalInstallment)->toBeNull()
            ->and($terms->financed)->toBeNull()
            ->and($terms->count)->toBeNull()
            ->and($terms->start?->toDateString())->toBe('2026-01-05')
            ->and($terms->end)->toBeNull()
            ->and($terms->status)->toBe(LoanStatus::Active);
    });

    test('of() reads a loan model', function (): void {
        $terms = LoanTerms::of(loanSchedModel(['installment_amount' => '250.00', 'status' => LoanStatus::PaidOff]));

        expect($terms->installment)->toBe(250.0)
            ->and($terms->count)->toBe(48)
            ->and($terms->status)->toBe(LoanStatus::PaidOff)
            ->and($terms->start?->toDateString())->toBe('2026-01-05');
    });
});

describe('balloon', function (): void {
    test('is the amount_override exception on the last date', function (): void {
        $terms = loanSchedTerms(['end_date' => '2029-12-05', 'final_installment_amount' => 1000]);

        expect((new LoanSchedule)->balloon($terms))->toBe(['occurrence_date' => '2029-12-05', 'amount_override' => 1000.0]);
    });

    test('is null without the final amount, the last date or the start date', function (): void {
        $schedule = new LoanSchedule;

        expect($schedule->balloon(loanSchedTerms(['end_date' => '2029-12-05'])))->toBeNull()
            ->and($schedule->balloon(loanSchedTerms(['final_installment_amount' => 1000])))->toBeNull()
            ->and($schedule->balloon(loanSchedTerms(['end_date' => '2029-12-05', 'final_installment_amount' => 1000, 'start_date' => null])))->toBeNull();
    });

    test('is null when the last date is not after the start', function (): void {
        expect((new LoanSchedule)->balloon(loanSchedTerms(['end_date' => '2026-01-05', 'final_installment_amount' => 1000])))->toBeNull();
    });
});

describe('plannedExpense', function (): void {
    test('the monthly expense of an active loan', function (): void {
        $expense = (new LoanSchedule)->plannedExpense(loanSchedModel(['end_date' => '2029-12-05']), 9, null);

        expect($expense)->toBe([
            'title' => 'Test car loan',
            'amount' => 250.0,
            'is_recurring' => true,
            'interval' => 1,
            'interval_unit' => 'month',
            'start_date' => '2026-01-05',
            'end_date' => '2029-12-05',
            'card_type' => 'loan',
            'card_id' => 7,
            'is_auto_created' => true,
            'category_id' => 9,
            'organization_id' => 3,
            'financial_account_id' => 2,
            'person_id' => 5,
        ]);
    });

    test('the balloon payment is not a column: balloon() gives its exception', function (): void {
        $expense = (new LoanSchedule)->plannedExpense(loanSchedModel(['end_date' => '2029-12-05', 'final_installment_amount' => 1000]), 9, null);

        expect($expense)->not->toHaveKey('planned_expense_exceptions')->not->toHaveKey('exceptions');
    });

    test('the optional parts are null when the loan has none, the account is the default one', function (): void {
        $loan = loanSchedModel(['organization_id' => null, 'financial_account_id' => null, 'person_id' => null]);

        expect((new LoanSchedule)->plannedExpense($loan, null, 4))->toBe([
            'title' => 'Test car loan',
            'amount' => 250.0,
            'is_recurring' => true,
            'interval' => 1,
            'interval_unit' => 'month',
            'start_date' => '2026-01-05',
            'end_date' => null,
            'card_type' => 'loan',
            'card_id' => 7,
            'is_auto_created' => true,
            'category_id' => null,
            'organization_id' => null,
            'financial_account_id' => 4,
            'person_id' => null,
        ]);
    });

    test('is null when the loan is not active, or has no instalment amount or start date', function (): void {
        $schedule = new LoanSchedule;

        expect($schedule->plannedExpense(loanSchedModel(['status' => 'paid_off']), 9, null))->toBeNull()
            ->and($schedule->plannedExpense(loanSchedModel(['installment_amount' => null]), 9, null))->toBeNull()
            ->and($schedule->plannedExpense(loanSchedModel(['installment_amount' => 0]), 9, null))->toBeNull()
            ->and($schedule->plannedExpense(loanSchedModel(['start_date' => null]), 9, null))->toBeNull();
    });
});

describe('expenseChanges', function (): void {
    $expense = fn (array $overrides = []): PlannedExpense => (new PlannedExpense)->forceFill([
        'title' => 'Test car loan', 'amount' => 250, 'start_date' => '2026-01-05', 'end_date' => '2029-12-05',
        'organization_id' => 3, 'financial_account_id' => 2, 'person_id' => 5, ...$overrides,
    ]);
    $before = loanSchedValues(['end_date' => '2029-12-05']);
    $today = CarbonImmutable::parse('2026-04-10');
    $changes = fn (array $before, array $after, PlannedExpense $expense, ?int $default = null): array => (new LoanSchedule)
        ->expenseChanges($before, $after, $expense, CarbonImmutable::parse('2026-04-10'), $default);

    test('nothing changed on the loan: nothing to change', function () use ($before, $expense, $changes): void {
        expect($changes($before, $before, $expense()))->toBe([]);
    });

    test('a changed instalment goes on the expense when it still had the old one', function () use ($before, $expense, $changes): void {
        expect($changes($before, [...$before, 'installment_amount' => 300], $expense()))->toBe(['amount' => 300]);
    });

    test('a value corrected by hand on the expense stays', function () use ($before, $expense, $changes): void {
        expect($changes($before, [...$before, 'installment_amount' => 300], $expense(['amount' => 260])))->toBe([]);
    });

    test('the name, the last date and the start date follow the loan', function () use ($before, $expense, $changes): void {
        $after = [...$before, 'name' => 'New name', 'end_date' => '2030-01-05T00:00:00.000Z', 'start_date' => '2026-02-05'];

        expect($changes($before, $after, $expense()))->toBe([
            'title' => 'New name', 'start_date' => '2026-02-05', 'end_date' => '2030-01-05',
        ]);
    });

    test('an emptied amount or start date leaves the expense as it is, an emptied last date does not', function () use ($before, $expense, $changes): void {
        $after = [...$before, 'installment_amount' => null, 'start_date' => null, 'end_date' => null];

        expect($changes($before, $after, $expense()))->toBe(['end_date' => null]);
    });

    test('the account: a loan without one had the default, which follows the loan', function () use ($expense, $changes): void {
        $without = loanSchedValues(['end_date' => '2029-12-05', 'financial_account_id' => null]);
        $after = [...$without, 'financial_account_id' => 4];

        expect($changes($without, $after, $expense(), 2))->toBe(['financial_account_id' => 4])
            ->and($changes($without, $after, $expense(['financial_account_id' => 8]), 2))->toBe([]);
    });

    test('the holder follows the loan when the expense still had the old one', function () use ($before, $expense, $changes): void {
        expect($changes($before, [...$before, 'person_id' => 6], $expense()))->toBe(['person_id' => 6])
            ->and($changes($before, [...$before, 'person_id' => null], $expense()))->toBe(['person_id' => null]);
    });

    test('a holder changed by hand on the expense stays', function () use ($before, $expense, $changes): void {
        expect($changes($before, [...$before, 'person_id' => 6], $expense(['person_id' => 9])))->toBe([]);
    });

    test('a loan paid off before the last instalment ends the expense today', function () use ($before, $expense, $changes): void {
        expect($changes($before, [...$before, 'status' => 'paid_off'], $expense()))->toBe(['end_date' => '2026-04-10']);
    });

    test('a loan paid off after the end of the expense leaves its end date', function () use ($before, $expense, $changes): void {
        expect($changes($before, [...$before, 'status' => 'paid_off'], $expense(['end_date' => '2026-03-01'])))->toBe([]);
    });

    test('the status may arrive as the enum, as the model gives it', function () use ($before, $expense, $changes): void {
        $active = [...$before, 'status' => LoanStatus::Active];

        expect($changes($active, [...$before, 'status' => LoanStatus::PaidOff], $expense()))->toBe(['end_date' => '2026-04-10']);
    });
});

describe('balloonChanges', function (): void {
    $terms = fn (array $overrides = []): LoanTerms => loanSchedTerms(['end_date' => '2029-12-05', ...$overrides]);
    $written = fn (array $overrides = []): PlannedExpenseException => (new PlannedExpenseException)->forceFill([
        'id' => 11, 'type' => 'amount_override', 'occurrence_date' => '2029-12-05', 'amount_override' => '1000.00', ...$overrides,
    ]);
    $of = fn (PlannedExpenseException ...$items): Collection => new Collection($items);
    $before = fn (): LoanTerms => loanSchedTerms(['end_date' => '2029-12-05', 'final_installment_amount' => 1000]);
    $change = fn (LoanTerms $before, LoanTerms $after, Collection $exceptions): array => (new LoanSchedule)->balloonChanges($before, $after, $exceptions);

    test('nothing changed: empty result', function () use ($before, $written, $of, $change): void {
        expect($change($before(), $before(), $of($written())))->toBe([]);
    });

    test('no balloon before or after: empty result', function () use ($terms, $of, $change): void {
        expect($change($terms(), $terms(['end_date' => '2030-01-05']), $of()))->toBe([]);
    });

    test('a balloon payment added later gets its exception', function () use ($terms, $of, $change): void {
        expect($change($terms(), $terms(['final_installment_amount' => 1000]), $of()))
            ->toBe(['create' => ['occurrence_date' => '2029-12-05', 'amount_override' => 1000.0]]);
    });

    test('a balloon payment added on a date that already has an exception creates nothing', function () use ($terms, $of, $change): void {
        $skip = (new PlannedExpenseException)->forceFill(['id' => 3, 'type' => 'skip', 'occurrence_date' => '2029-12-05']);

        expect($change($terms(), $terms(['final_installment_amount' => 1000]), $of($skip)))->toBe([]);
    });

    test('a changed amount updates the exception written by the system', function () use ($terms, $before, $written, $of, $change): void {
        expect($change($before(), $terms(['final_installment_amount' => 1200]), $of($written())))
            ->toBe(['update' => [11, ['occurrence_date' => '2029-12-05', 'amount_override' => 1200.0]]]);
    });

    test('a moved last date moves the exception', function () use ($terms, $before, $written, $of, $change): void {
        expect($change($before(), $terms(['end_date' => '2030-01-05', 'final_installment_amount' => 1000]), $of($written())))
            ->toBe(['update' => [11, ['occurrence_date' => '2030-01-05', 'amount_override' => 1000.0]]]);
    });

    test('a balloon payment taken away removes the exception', function () use ($terms, $before, $written, $of, $change): void {
        expect($change($before(), $terms(), $of($written())))->toBe(['remove' => 11]);
    });

    test('an exception changed by hand stays', function () use ($terms, $before, $written, $of, $change): void {
        $edited = $written(['amount_override' => '900.00']);

        expect($change($before(), $terms(['final_installment_amount' => 1200]), $of($edited)))->toBe([])
            ->and($change($before(), $terms(), $of($edited)))->toBe([]);
    });

    test('if the new last date already has an exception written by hand, ours goes', function () use ($terms, $before, $written, $of, $change): void {
        $other = (new PlannedExpenseException)->forceFill(['id' => 12, 'type' => 'skip', 'occurrence_date' => '2030-01-05']);

        expect($change($before(), $terms(['end_date' => '2030-01-05', 'final_installment_amount' => 1000]), $of($written(), $other)))
            ->toBe(['remove' => 11]);
    });
});
