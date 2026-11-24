<?php

declare(strict_types=1);

use App\Actions\Families\ChangeFeatures;
use App\Actions\Loans\CreateLoanPlans;
use App\Enums\ExceptionType;
use App\Enums\LoanStatus;
use App\Models\Family;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Services\CardBudgets;
use App\Services\Deadlines;
use App\Services\FamilyFeatures;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00', 'Europe/Rome'));
    $this->marco = registerFamily();
    $this->family = Family::query()->whereKey($this->marco->family_id)->sole();
    $this->family->forceFill(['budget_enabled' => false])->save();
    resolve(FamilyFeatures::class)->forget($this->family);
    // The mortgage of 240 instalments from 01/01/2025, saved while the Budget is off: no planned expense
    $this->loan = insideFamilyOf($this->marco, fn (): Loan => Loan::query()->create([
        'name' => 'Mutuo casa',
        'status' => LoanStatus::Active,
        'start_date' => '2025-01-01',
        'installments_count' => 240,
        'installment_amount' => 650,
    ]));
});

afterEach(fn () => $this->travelBack());

/**
 * The planned expenses of the family of an account.
 *
 * @param  User  $user  the account
 * @return list<PlannedExpense>
 */
function createLoanPlansOf(User $user): array
{
    return insideFamilyOf($user, fn (): array => PlannedExpense::query()->with('exceptions')->orderBy('id')->get()->all());
}

/**
 * Turns the Budget on for the family of the test, through the settings action.
 */
function createLoanPlansTurnOn(object $test, bool $on = true): void
{
    resolve(ChangeFeatures::class)->handle($test->family->fresh(), budget: $on, mail: false);
}

test('it creates the planned expense of a loan without one, from today on and with no late instalment', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-01-10 10:00', 'Europe/Rome'));

    $created = insideFamilyOf($this->marco, fn (): int => resolve(CreateLoanPlans::class)->handle(CarbonImmutable::today('Europe/Rome')));

    $plans = createLoanPlansOf($this->marco);
    expect($created)->toBe(1)
        ->and($plans)->toHaveCount(1)
        ->and($plans[0]->card_id)->toBe($this->loan->id)
        ->and($plans[0]->is_auto_created)->toBeTrue()
        ->and((float) $plans[0]->amount)->toBe(650.0)
        ->and($plans[0]->start_date?->toDateString())->toBe('2027-02-01')
        ->and($plans[0]->end_date?->toDateString())->toBe('2044-12-01');

    $late = insideFamilyOf($this->marco, fn (): array => resolve(Deadlines::class)->between($plans, CarbonImmutable::parse('2025-01-01'), CarbonImmutable::parse('2027-01-09')));
    expect($late)->toBe([]);

    // The figures of the loan still count the instalments from its own start
    expect(insideFamilyOf($this->marco, fn (): ?int => Loan::query()->findOrFail($this->loan->id)->installments_paid))->toBe(25);
});

test('a due date on the day itself is the first one', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-02-01 08:00', 'Europe/Rome'));

    insideFamilyOf($this->marco, fn (): int => resolve(CreateLoanPlans::class)->handle(CarbonImmutable::today('Europe/Rome')));

    expect(createLoanPlansOf($this->marco)[0]->start_date?->toDateString())->toBe('2027-02-01');
});

test('it keeps the final balloon payment as the exception of the last date', function (): void {
    insideFamilyOf($this->marco, fn (): bool => $this->loan->update(['final_installment_amount' => 5000]));

    insideFamilyOf($this->marco, fn (): int => resolve(CreateLoanPlans::class)->handle(CarbonImmutable::today('Europe/Rome')));

    $exception = createLoanPlansOf($this->marco)[0]->exceptions->sole();
    expect($exception->type)->toBe(ExceptionType::AmountOverride)
        ->and($exception->occurrence_date?->toDateString())->toBe('2044-12-01')
        ->and((float) $exception->amount_override)->toBe(5000.0);
});

test('it skips a loan paid off, one with all its instalments past and one that already has its planned expense', function (): void {
    insideFamilyOf($this->marco, function (): void {
        Loan::query()->create(['name' => 'Estinto', 'status' => LoanStatus::PaidOff, 'start_date' => '2025-01-01', 'installments_count' => 240, 'installment_amount' => 100]);
        Loan::query()->create(['name' => 'Finito', 'status' => LoanStatus::Active, 'start_date' => '2020-01-01', 'installments_count' => 12, 'installment_amount' => 100]);
    });

    expect(insideFamilyOf($this->marco, fn (): int => resolve(CreateLoanPlans::class)->handle(CarbonImmutable::today('Europe/Rome'))))->toBe(1)
        ->and(insideFamilyOf($this->marco, fn (): int => resolve(CreateLoanPlans::class)->handle(CarbonImmutable::today('Europe/Rome'))))->toBe(0)
        ->and(createLoanPlansOf($this->marco))->toHaveCount(1);
});

test('turning the Budget on creates it once, also when turned off and on again', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-01-10 10:00', 'Europe/Rome'));

    createLoanPlansTurnOn($this);
    createLoanPlansTurnOn($this);
    createLoanPlansTurnOn($this, false);
    createLoanPlansTurnOn($this);

    $plans = createLoanPlansOf($this->marco);
    expect($plans)->toHaveCount(1)
        ->and($plans[0]->start_date?->toDateString())->toBe('2027-02-01');
});

test('changing the loan afterwards does not bring its start back to the past', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-01-10 10:00', 'Europe/Rome'));
    createLoanPlansTurnOn($this);

    insideFamilyOf($this->marco, fn (): bool => Loan::query()->findOrFail($this->loan->id)->update(['installment_amount' => 700]));

    $plan = createLoanPlansOf($this->marco)[0];
    expect((float) $plan->amount)->toBe(700.0)
        ->and($plan->start_date?->toDateString())->toBe('2027-02-01');

    insideFamilyOf($this->marco, fn (): bool => Loan::query()->findOrFail($this->loan->id)->update(['start_date' => '2025-02-01']));

    expect(createLoanPlansOf($this->marco)[0]->start_date?->toDateString())->toBe('2027-02-01');
});

test('a loan on the 31st keeps its day every month when the first instalment falls on a short month', function (): void {
    $loan = insideFamilyOf($this->marco, fn (): Loan => Loan::query()->create([
        'name' => 'Prestito auto',
        'status' => LoanStatus::Active,
        'start_date' => '2025-01-31',
        'installments_count' => 36,
        'installment_amount' => 300,
        'final_installment_amount' => 3000,
    ]));
    $this->travelTo(CarbonImmutable::parse('2027-02-10 10:00', 'Europe/Rome'));

    insideFamilyOf($this->marco, fn (): int => resolve(CreateLoanPlans::class)->handle(CarbonImmutable::today('Europe/Rome')));

    $plans = array_values(array_filter(createLoanPlansOf($this->marco), fn (PlannedExpense $plan): bool => $plan->card_id === $loan->id));
    $dates = fn (CarbonImmutable $from, CarbonImmutable $to): array => array_map(
        fn ($deadline): array => [$deadline->occurrence->date->toDateString(), $deadline->occurrence->amount],
        insideFamilyOf($this->marco, fn (): array => resolve(Deadlines::class)->between($plans, $from, $to)),
    );

    // The instalment of February is due on the 28th, then the 31st again; nothing is late before today
    expect($dates(CarbonImmutable::parse('2025-01-01'), CarbonImmutable::parse('2027-02-09')))->toBe([])
        ->and($dates(CarbonImmutable::parse('2027-02-10'), CarbonImmutable::parse('2027-05-31')))->toBe([
            ['2027-02-28', 300.0], ['2027-03-31', 300.0], ['2027-04-30', 300.0], ['2027-05-31', 300.0],
        ])
        // The final balloon payment sits on the last instalment, a real due date of the series
        ->and($dates(CarbonImmutable::parse('2027-11-01'), CarbonImmutable::parse('2028-01-31')))->toBe([
            ['2027-11-30', 300.0], ['2027-12-31', 3000.0],
        ])
        // The figures of the loan count from its own start, untouched
        ->and(insideFamilyOf($this->marco, fn (): ?int => Loan::query()->findOrFail($loan->id)->installments_paid))->toBe(25);
});

test('the card numbers the instalments of a late planned expense from the start of the loan', function (string $today, int $expected): void {
    $loan = insideFamilyOf($this->marco, fn (): Loan => Loan::query()->create([
        'name' => 'Prestito auto',
        'status' => LoanStatus::Active,
        'start_date' => '2025-01-31',
        'installments_count' => 36,
        'installment_amount' => 300,
    ]));
    $this->travelTo(CarbonImmutable::parse($today.' 10:00', 'Europe/Rome'));
    createLoanPlansTurnOn($this);

    $budget = insideFamilyOf($this->marco, fn () => resolve(CardBudgets::class)->of(Loan::query()->findOrFail($loan->id), $this->marco, CarbonImmutable::today('Europe/Rome')));

    expect($budget?->installment_number)->toBe($expected)
        ->and($budget?->installments_count)->toBe(36);
})->with([
    // 28/02/2027, the instalment of its own before the monthly series, is the 26th of the loan
    'the instalment on a short month' => ['2027-02-10', 26],
    // 31/03/2027, the first of the monthly series, is the 27th
    'the first of the monthly series' => ['2027-03-01', 27],
]);

test('it reads only the loans of the current family', function (): void {
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    insideFamilyOf($rossi, fn (): Loan => Loan::query()->create(['name' => 'Altro', 'status' => LoanStatus::Active, 'start_date' => '2030-01-01', 'installments_count' => 12, 'installment_amount' => 100]));
    $before = count(createLoanPlansOf($rossi));

    insideFamilyOf($this->marco, fn (): int => resolve(CreateLoanPlans::class)->handle(CarbonImmutable::today('Europe/Rome')));

    expect(createLoanPlansOf($rossi))->toHaveCount($before);
});
