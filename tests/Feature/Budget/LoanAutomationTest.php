<?php

declare(strict_types=1);

use App\Enums\ExceptionType;
use App\Enums\IntervalUnit;
use App\Enums\LoanStatus;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\Loan;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule as ConsoleSchedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    registerFamily();
    $this->travelTo(CarbonImmutable::parse('2026-10-26 10:00:00', 'Europe/Rome'));
});

/** The id of the Bianchi family. */
function loanAutoFamily(): int
{
    return Family::query()->where('name', 'Bianchi')->sole()->id;
}

/**
 * The *Finanziamento Panda* of the Bianchi: 8.000,00 € financed, 48 instalments of 189,00 € from 10/03/2024.
 *
 * @param  array<string, mixed>  $attributes
 */
function loanAutoPanda(array $attributes = []): Loan
{
    return Loan::query()->create([
        'name' => 'Finanziamento Panda',
        'status' => LoanStatus::Active,
        'total_amount' => 8000,
        'installments_count' => 48,
        'installment_amount' => 189,
        'start_date' => '2024-03-10',
        ...$attributes,
    ]);
}

/** The planned expense of the instalment of a loan. */
function loanAutoExpense(Loan $loan): PlannedExpense
{
    return PlannedExpense::query()->where('loan_id', $loan->id)->sole();
}

it('works out the figures when a loan is saved', function (): void {
    insideBianchi(function (): void {
        $loan = loanAutoPanda()->fresh();

        expect($loan?->end_date?->toDateString())->toBe('2028-02-10')
            ->and($loan?->total_to_pay)->toBe('9072.00')
            ->and($loan?->cost)->toBe('1072.00')
            ->and($loan?->installments_paid)->toBe(32)
            ->and($loan?->installments_left)->toBe(16)
            ->and($loan?->residual_amount)->toBe('3024.00');
    });
});

it('creates the planned expense of the instalment', function (): void {
    insideBianchi(function (): void {
        $category = ExpenseCategory::factory()->create(['family_id' => loanAutoFamily()]);
        $older = Loan::factory()->create(['family_id' => loanAutoFamily(), 'name' => 'Mutuo casa']);
        PlannedExpense::factory()->create(['family_id' => loanAutoFamily(), 'loan_id' => $older->id, 'category_id' => $category->id]);
        $default = FinancialAccount::factory()->create(['family_id' => loanAutoFamily(), 'is_default' => true]);
        $findomestic = Organization::factory()->create(['family_id' => loanAutoFamily()]);
        $marco = Person::factory()->create(['family_id' => loanAutoFamily()]);

        $panda = loanAutoPanda(['organization_id' => $findomestic->id, 'person_id' => $marco->id]);
        $expense = loanAutoExpense($panda);

        expect($expense->title)->toBe('Finanziamento Panda')
            ->and($expense->amount)->toBe('189.00')
            ->and($expense->is_recurring)->toBeTrue()
            ->and($expense->interval)->toBe(1)
            ->and($expense->interval_unit)->toBe(IntervalUnit::Month)
            ->and($expense->start_date?->toDateString())->toBe('2024-03-10')
            ->and($expense->end_date?->toDateString())->toBe('2028-02-10')
            ->and($expense->organization_id)->toBe($findomestic->id)
            ->and($expense->financial_account_id)->toBe($default->id)
            ->and($expense->person_id)->toBe($marco->id)
            ->and($expense->is_auto_created)->toBeTrue()
            ->and($expense->category_id)->toBe($category->id)
            ->and($expense->exceptions)->toBeEmpty();

        $own = FinancialAccount::factory()->create(['family_id' => loanAutoFamily()]);
        $balloon = loanAutoPanda(['name' => 'Finanziamento Tipo', 'financial_account_id' => $own->id, 'final_installment_amount' => 2000]);
        $withBalloon = loanAutoExpense($balloon);

        expect($withBalloon->financial_account_id)->toBe($own->id)
            ->and($withBalloon->exceptions)->toHaveCount(1)
            ->and($withBalloon->exceptions[0]->type)->toBe(ExceptionType::AmountOverride)
            ->and($withBalloon->exceptions[0]->occurrence_date?->toDateString())->toBe('2028-02-10')
            ->and($withBalloon->exceptions[0]->amount_override)->toBe('2000.00')
            ->and($balloon->fresh()?->total_to_pay)->toBe('10883.00');
    });
});

it('follows the loan only where the planned expense still had the old value', function (): void {
    insideBianchi(function (): void {
        $first = FinancialAccount::factory()->create(['family_id' => loanAutoFamily()]);
        $second = FinancialAccount::factory()->create(['family_id' => loanAutoFamily()]);
        $byHand = FinancialAccount::factory()->create(['family_id' => loanAutoFamily()]);
        $panda = loanAutoPanda(['financial_account_id' => $first->id]);
        loanAutoExpense($panda)->update(['financial_account_id' => $byHand->id]);

        $panda->fresh()?->update(['installment_amount' => 195, 'financial_account_id' => $second->id]);

        $expense = loanAutoExpense($panda);
        expect($expense->amount)->toBe('195.00')
            ->and($expense->financial_account_id)->toBe($byHand->id)
            ->and($panda->fresh()?->total_to_pay)->toBe('9360.00');
    });
});

it('changes the final payment with the loan, and its total', function (): void {
    insideBianchi(function (): void {
        $loan = loanAutoPanda(['final_installment_amount' => 2000]);

        $loan->update(['final_installment_amount' => 2500]);

        $exceptions = loanAutoExpense($loan)->exceptions;
        expect($exceptions)->toHaveCount(1)
            ->and($exceptions[0]->amount_override)->toBe('2500.00')
            ->and($loan->total_to_pay)->toBe('11383.00')
            ->and($loan->fresh()?->total_to_pay)->toBe('11383.00');
    });
});

it('ends the planned expense today when the loan is paid off', function (): void {
    insideBianchi(function (): void {
        $panda = loanAutoPanda();

        $panda->update(['status' => LoanStatus::PaidOff]);

        expect(loanAutoExpense($panda)->end_date?->toDateString())->toBe('2026-10-26')
            ->and($panda->fresh()?->installments_left)->toBe(0)
            ->and($panda->fresh()?->residual_amount)->toBe('0.00');
    });
});

it('recalculates the loan when an exception of its planned expense changes', function (): void {
    insideBianchi(function (): void {
        $panda = loanAutoPanda();
        $expense = loanAutoExpense($panda);

        $exception = PlannedExpenseException::query()->create([
            'planned_expense_id' => $expense->id, 'type' => ExceptionType::AmountOverride,
            'occurrence_date' => '2027-01-10', 'amount_override' => 89,
        ]);

        expect($panda->fresh()?->total_to_pay)->toBe('8972.00')
            ->and($panda->fresh()?->residual_amount)->toBe('2924.00');

        $exception->delete();

        expect($panda->fresh()?->total_to_pay)->toBe('9072.00');

        PlannedExpenseException::query()->create([
            'planned_expense_id' => $expense->id, 'type' => ExceptionType::AmountOverride,
            'occurrence_date' => '2027-01-10', 'amount_override' => 89,
        ]);
        expect($panda->fresh()?->total_to_pay)->toBe('8972.00');

        // The exceptions go with the planned expense, in the database, without their own events.
        $expense->delete();

        expect($panda->fresh()?->total_to_pay)->toBe('9072.00');
    });
});

it('saves each side once', function (): void {
    insideBianchi(function (): void {
        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^(insert into|update|delete from) [`"]?(loans|planned_expenses)[`"]?\s/i', $query->sql, $match) === 1) {
                $writes[] = strtolower($match[2]);
            }
        });

        $panda = loanAutoPanda();

        expect(array_count_values($writes))->toBe(['loans' => 1, 'planned_expenses' => 1]);

        $writes = [];
        $panda->update(['installment_amount' => 195]);

        expect(array_count_values($writes))->toBe(['loans' => 1, 'planned_expenses' => 1])
            ->and(loanAutoExpense($panda)->amount)->toBe('195.00');

        $writes = [];
        $panda->update(['notes' => 'Rata il 10']);

        expect(array_count_values($writes))->toBe(['loans' => 1]);
    });
});

it('saves the loan once when the final payment or the dates change', function (): void {
    insideBianchi(function (): void {
        $loan = loanAutoPanda(['final_installment_amount' => 2000]);

        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^(insert into|update|delete from) [`"]?(loans|planned_expenses|planned_expense_exceptions)[`"]?\s/i', $query->sql, $match) === 1) {
                $writes[] = strtolower($match[2]);
            }
        });

        $loan->update(['final_installment_amount' => 2500]);

        expect(array_count_values($writes))->toBe(['loans' => 1, 'planned_expense_exceptions' => 1])
            ->and($loan->fresh()?->total_to_pay)->toBe('11383.00');

        $writes = [];
        $loan->update(['start_date' => '2024-04-10', 'end_date' => '2028-03-10']);

        $exceptions = loanAutoExpense($loan)->exceptions;
        expect(array_count_values($writes))->toBe(['loans' => 1, 'planned_expenses' => 1, 'planned_expense_exceptions' => 1])
            ->and($exceptions)->toHaveCount(1)
            ->and($exceptions[0]->occurrence_date?->toDateString())->toBe('2028-03-10')
            ->and($loan->fresh()?->total_to_pay)->toBe('11383.00');
    });
});

it('counts the instalment of the day after midnight', function (): void {
    $panda = insideBianchi(fn (): Loan => loanAutoPanda());
    $this->travelTo(CarbonImmutable::parse('2026-11-10 00:30:00', 'Europe/Rome'));

    $this->artisan('loans:refresh')->expectsOutputToContain('Loans changed: 1')->assertSuccessful();

    insideBianchi(function () use ($panda): void {
        expect($panda->fresh()?->installments_paid)->toBe(33)
            ->and($panda->fresh()?->installments_left)->toBe(15)
            ->and($panda->fresh()?->residual_amount)->toBe('2835.00');
    });

    $updatedAt = insideBianchi(fn (): ?string => $panda->fresh()?->updated_at?->toDateTimeString());
    $this->travelTo(CarbonImmutable::parse('2026-11-10 00:40:00', 'Europe/Rome'));

    $this->artisan('loans:refresh')->expectsOutputToContain('Loans changed: 0')->assertSuccessful();

    insideBianchi(function () use ($panda, $updatedAt): void {
        expect($panda->fresh()?->installments_paid)->toBe(33)
            ->and($panda->fresh()?->updated_at?->toDateTimeString())->toBe($updatedAt);
    });
});

it('is scheduled at 00:05 in Rome', function (): void {
    $events = collect(resolve(ConsoleSchedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'loans:refresh'));

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('5 0 * * *')
        ->and($events->first()?->timezone)->toBe('Europe/Rome');
});
