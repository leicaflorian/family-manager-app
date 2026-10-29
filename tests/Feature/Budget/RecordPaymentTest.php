<?php

declare(strict_types=1);

use App\Actions\Budget\RecordPayment;
use App\Enums\IncomeType;
use App\Models\Document;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Deadlines;
use App\Support\CurrentFamily;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake('local');
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->seed(BianchiFamilySeeder::class);
});

/**
 * The due date of a planned item on a day, found by its title.
 */
function paymentDeadline(string $kind, string $title, string $day): Deadline
{
    $planned = $kind === 'income'
        ? PlannedIncome::query()->where('title', $title)->sole()
        : PlannedExpense::query()->where('title', $title)->sole();

    return resolve(Deadlines::class)->find($planned, CarbonImmutable::parse($day));
}

/** Records a payment from the default values of a test. */
function recordIt(Deadline $deadline, string $date, float $amount, ?int $account = null, ?UploadedFile $receipt = null): Expense|Income
{
    return resolve(RecordPayment::class)->handle($deadline, CarbonImmutable::parse($date), $amount, $account, $receipt);
}

test('it records the expense of a due date', function (): void {
    insideBianchi(function (): void {
        $planned = PlannedExpense::query()->where('title', 'Assicurazione auto')->sole();
        $bper = FinancialAccount::query()->where('name', 'Conto BPER')->sole();

        $expense = recordIt(paymentDeadline('expense', 'Assicurazione auto', '2026-11-21'), '2026-10-26', 418.0, $bper->id);

        expect($expense)->toBeInstanceOf(Expense::class)
            ->and($expense->title)->toBe('Assicurazione auto novembre 2026')
            ->and((float) $expense->amount)->toBe(418.0)
            ->and($expense->date?->toDateString())->toBe('2026-10-26')
            ->and($expense->occurrence_date?->toDateString())->toBe('2026-11-21')
            ->and($expense->planned_expense_id)->toBe($planned->id)
            ->and($expense->category_id)->toBe($planned->category_id)
            ->and($expense->organization_id)->toBe($planned->organization_id)
            ->and($expense->person_id)->toBe($planned->person_id)
            ->and($expense->financial_account_id)->toBe($bper->id);
    });
});

test('it names the month of the due date, not of the payment', function (): void {
    insideBianchi(function (): void {
        $expense = recordIt(paymentDeadline('expense', 'Retta danza', '2026-12-05'), '2026-10-26', 60.0);

        expect($expense->title)->toBe('Retta danza dicembre 2026');
    });
});

test('it takes the account of the planned expense, then the default one, when none is chosen', function (): void {
    insideBianchi(function (): void {
        $laura = FinancialAccount::query()->where('name', 'Carta Laura')->sole();

        expect(recordIt(paymentDeadline('expense', 'Retta danza', '2026-12-05'), '2026-10-26', 60.0)->financial_account_id)->toBe($laura->id);

        PlannedExpense::query()->where('title', 'Assicurazione auto')->update(['financial_account_id' => null]);
        $default = FinancialAccount::query()->where('is_default', true)->sole();

        expect(recordIt(paymentDeadline('expense', 'Assicurazione auto', '2026-11-21'), '2026-10-26', 418.0)->financial_account_id)->toBe($default->id);
    });
});

test('it moves the documents of the planned expense to the expense', function (): void {
    insideBianchi(function (): void {
        $planned = PlannedExpense::query()->where('title', 'Retta danza')->sole();
        $near = Document::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $planned->id, 'date' => '2026-12-02']);
        $old = Document::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $planned->id, 'date' => '2025-12-02']);
        $taken = Document::factory()->create(['family_id' => CurrentFamily::id(), 'planned_expense_id' => $planned->id, 'date' => '2026-12-03']);
        Expense::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Other'])->documents()->attach($taken);

        $expense = recordIt(paymentDeadline('expense', 'Retta danza', '2026-12-05'), '2026-10-26', 60.0);

        expect($expense->documents()->pluck('documents.id')->all())->toBe([$near->id])
            ->and($old->expenses()->count())->toBe(0)
            ->and($taken->expenses()->count())->toBe(1);
    });
});

test('it turns the receipt into a linked document', function (): void {
    insideBianchi(function (): void {
        $expense = recordIt(paymentDeadline('expense', 'Assicurazione auto', '2026-11-21'), '2026-10-26', 418.0, null, UploadedFile::fake()->create('ricevuta.pdf', 10, 'application/pdf'));

        $document = $expense->documents()->sole();

        expect($document->title)->toBe('Assicurazione auto novembre 2026')
            ->and($document->date?->toDateString())->toBe('2026-10-26');
        Storage::disk('local')->assertExists($document->file->path);
    });
});

test('it records an income with its type and employment', function (): void {
    insideBianchi(function (): void {
        $planned = PlannedIncome::query()->where('title', 'Stipendio di Marco')->sole();

        $income = recordIt(paymentDeadline('income', 'Stipendio di Marco', '2026-10-27'), '2026-10-27', 1720.0, null, UploadedFile::fake()->create('busta.pdf', 10, 'application/pdf'));

        expect($income)->toBeInstanceOf(Income::class)
            ->and($income->title)->toBe('Stipendio di Marco ottobre 2026')
            ->and((float) $income->amount)->toBe(1720.0)
            ->and($income->type)->toBe(IncomeType::Salary)
            ->and($income->planned_income_id)->toBe($planned->id)
            ->and($income->occurrence_date?->toDateString())->toBe('2026-10-27')
            ->and($income->employment_id)->toBe($planned->employment_id)
            ->and($income->organization_id)->toBe($planned->organization_id)
            ->and($income->person_id)->toBe($planned->person_id)
            ->and($income->documents()->count())->toBe(1);
    });
});

test('it refuses to pay a due date twice', function (): void {
    insideBianchi(function (): void {
        $deadline = paymentDeadline('expense', 'Assicurazione auto', '2026-11-21');
        recordIt($deadline, '2026-10-26', 418.0);

        expect(fn (): Expense|Income => recordIt($deadline, '2026-10-26', 418.0))->toThrow(ValidationException::class, 'Questa scadenza è già pagata.')
            ->and(Expense::query()->where('title', 'Assicurazione auto novembre 2026')->count())->toBe(1);
    });
});
