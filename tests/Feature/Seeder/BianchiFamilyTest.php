<?php

declare(strict_types=1);

use App\Enums\FinancialAccountType;
use App\Enums\MaintenanceType;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\HealthEvent;
use App\Models\Income;
use App\Models\Loan;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Deadlines;
use App\Support\CurrentFamily;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

test('the seeder creates the Bianchi family as on the canvas, in Italian', function (): void {
    $this->seed(BianchiFamilySeeder::class);

    insideBianchi(function (): void {
        $panda = Vehicle::query()->where('name', 'Fiat Panda')->sole();
        $document = Document::query()->where('title', 'Bolletta luce di ottobre')->sole();

        expect(Person::query()->count())->toBe(3)
            ->and(User::query()->where('family_id', CurrentFamily::id())->count())->toBe(2)
            ->and(Organization::query()->count())->toBe(22)
            ->and(Document::query()->count())->toBe(18)
            ->and($panda->documents()->pluck('title')->all())->toBe(['Polizza RC auto 2026'])
            ->and($document->category?->name)->toBe('Luce')
            ->and($document->organization?->name)->toBe('Edison')
            ->and($document->date?->format('d/m/Y'))->toBe('26/10/2026')
            ->and($document->linkables()->count())->toBe(1)
            ->and(Family::query()->sole()->isWelcomed())->toBeTrue();
        Storage::disk('local')->assertExists($document->file->path);
    });
});

test('run twice it duplicates nothing', function (): void {
    $this->seed(BianchiFamilySeeder::class);
    $this->seed(BianchiFamilySeeder::class);

    expect(Family::query()->count())->toBe(1)
        ->and(Document::withoutGlobalScopes()->count())->toBe(18);
});

test('the Bianchi accounts sign in with the test password', function (): void {
    $this->seed(BianchiFamilySeeder::class);

    $this->post(route('login.store'), ['email' => 'laura.bianchi@example.com', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs(User::query()->where('email', 'laura.bianchi@example.com')->sole());
});

test('it creates the cards of the canvas', function (): void {
    $this->seed(BianchiFamilySeeder::class);

    insideBianchi(function (): void {
        $boiler = MaintenanceRecord::query()->where('title', 'Revisione caldaia')->sole();
        expect($boiler->date->toDateString())->toBe('2026-09-15')
            ->and($boiler->type)->toBe(MaintenanceType::BoilerService)
            ->and($boiler->home?->name)->toBe('Casa di via Roma')
            ->and($boiler->organization?->name)->toBe('Termoidraulica Rossi');

        $dentist = HealthEvent::query()->where('title', 'Dentista di Laura')->sole();
        expect($dentist->date->toDateString())->toBe('2026-10-08')
            ->and($dentist->next_checkup_date?->toDateString())->toBe('2027-04-08')
            ->and($dentist->organization?->name)->toBe('Studio Ferri')
            ->and($dentist->person->first_name)->toBe('Laura');

        $card = FinancialAccount::query()->where('name', 'Carta Laura')->sole();
        expect($card->type)->toBe(FinancialAccountType::Card)
            ->and($card->last_four_digits)->toBe('0937');

        $netflix = Subscription::query()->where('name', 'Netflix')->sole();
        expect($netflix->financial_account_id)->toBe($card->id)
            ->and($netflix->trial_end_date?->toDateString())->toBe('2026-11-04')
            ->and($netflix->activation_date?->toDateString())->toBe('2026-10-05')
            ->and($netflix->is_auto_renew)->toBeTrue();
    });
});

describe('the budget of the Bianchi family on 26/10/2026', function (): void {
    beforeEach(function (): void {
        Date::setTestNow(Date::parse('2026-10-26 10:00', 'Europe/Rome'));
        $this->seed(BianchiFamilySeeder::class);
    });

    afterEach(fn () => Date::setTestNow());

    it('has the expense categories with the colours of the areas', function (): void {
        insideBianchi(function (): void {
            $colours = ExpenseCategory::query()->whereNull('parent_id')->pluck('color', 'name')->all();

            expect($colours)->toMatchArray([
                'Casa e affitto' => '#F76B15',
                'Bollette e utenze' => '#F76B15',
                'Auto' => '#0797B9',
                'Polizze e assicurazioni' => '#8E4EC6',
                'Prestiti e finanziamenti' => '#3E63DD',
                'Lavoro' => '#6E9F18',
                'Salute' => '#C13FB8',
                'Scuola e attività' => '#E5A50A',
                'Spesa' => '#E35169',
                'Svago' => '#E35169',
                'Donazioni' => '#E35169',
                'Risparmi' => '#E35169',
                'Abbigliamento' => '#E35169',
            ])
                ->and(ExpenseCategory::query()->where('name', 'Luce')->sole()->color)->toBe('#F76B15')
                ->and(ExpenseCategory::query()->where('name', 'Sport')->sole()->parent?->name)->toBe('Scuola e attività');
        });
    });

    it('has the planned expenses and incomes of the canvas', function (): void {
        insideBianchi(function (): void {
            $today = CarbonImmutable::today('Europe/Rome');
            $active = PlannedExpense::query()->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $today))->get();
            // The card each planned expense is linked to; the groceries belong to no card.
            $cards = [
                'Gas' => 'utility_id', 'Acqua' => 'utility_id', 'Spesa' => null, 'Carburante' => 'vehicle_id',
                'Internet - Fastweb' => 'utility_id', 'Netflix' => 'subscription_id', 'Luce' => 'utility_id',
                'Finanziamento Panda' => 'loan_id', 'Mutuo casa' => 'loan_id', 'Assicurazione auto' => 'insurance_policy_id',
                'Retta danza' => 'enrollment_id', 'Bollo auto' => 'vehicle_id',
            ];

            expect($active)->toHaveCount(12)
                ->and($active->pluck('title')->sort()->values()->all())->toBe(collect(array_keys($cards))->sort()->values()->all());

            foreach ($cards as $title => $column) {
                expect($active->firstWhere('title', $title)?->getAttribute($column ?? 'id'))->not->toBeNull();
            }

            $incomes = PlannedIncome::query()->get();
            expect($incomes)->toHaveCount(2)
                ->and($incomes->pluck('employment_id')->filter()->count())->toBe(2)
                ->and(PlannedExpense::query()->where('title', 'Bollo auto')->sole()->amount)->toBe('198.00');
        });
    });

    it('has the movements of October 2026', function (): void {
        insideBianchi(function (): void {
            $expenses = Expense::query()->whereBetween('date', ['2026-10-01', '2026-10-31'])->get();
            $incomes = Income::query()->whereBetween('date', ['2026-10-01', '2026-10-31'])->get();
            $latest = $expenses->sortByDesc('date')->first();

            expect(round($incomes->sum(fn (Income $income): float => (float) $income->amount), 2))->toBe(1720.00)
                ->and(round($expenses->sum(fn (Expense $expense): float => (float) $expense->amount), 2))->toBe(1284.20)
                ->and($expenses)->toHaveCount(15)
                ->and($latest?->title)->toBe('Parcheggio')
                ->and($latest?->amount)->toBe('12.00')
                ->and($latest?->date?->toDateString())->toBe('2026-10-24')
                ->and($latest?->financialAccount?->name)->toBe('Conto BPER');
        });
    });

    it('plans 1.469,30 € for October 2026', function (): void {
        insideBianchi(function (): void {
            $due = resolve(Deadlines::class)->between(PlannedExpense::query()->get(), CarbonImmutable::parse('2026-10-01', 'Europe/Rome'), CarbonImmutable::parse('2026-10-31', 'Europe/Rome'));

            expect(round(array_sum(array_map(fn (Deadline $deadline): float => $deadline->occurrence->amount, $due)), 2))->toBe(1469.30)
                ->and(collect($due)->reject->isPaid()->map(fn ($deadline): string => $deadline->planned->title)->sort()->values()->all())->toBe(['Acqua', 'Gas', 'Luce']);
        });
    });

    it('has paid 642,10 € of electricity in 2026, in 9 bills', function (): void {
        insideBianchi(function (): void {
            $luce = PlannedExpense::query()->where('title', 'Luce')->sole();
            $bills = Expense::query()->where('planned_expense_id', $luce->id)->whereBetween('date', ['2026-01-01', '2026-12-31'])->get();

            expect($bills)->toHaveCount(9)
                ->and(round($bills->sum(fn (Expense $bill): float => (float) $bill->amount), 2))->toBe(642.10);
        });
    });

    it('has the dance fee of the canvas', function (): void {
        insideBianchi(function (): void {
            $today = CarbonImmutable::today('Europe/Rome');
            $deadlines = resolve(Deadlines::class);
            $fee = PlannedExpense::query()->where('title', 'Retta danza')->sole();
            $november = $deadlines->find($fee, CarbonImmutable::parse('2026-11-05', 'Europe/Rome'));

            expect($deadlines->yearTotal([$fee], $today))->toBe(465.00)
                ->and($november->isPaid())->toBeTrue()
                ->and($november->payment?->date?->toDateString())->toBe('2026-10-20')
                ->and($deadlines->next([$fee], $today)?->occurrence->originalDate->toDateString())->toBe('2026-12-05');
        });
    });

    it('has the late electricity bill of September', function (): void {
        insideBianchi(function (): void {
            $today = CarbonImmutable::today('Europe/Rome');
            $luce = PlannedExpense::query()->where('title', 'Luce')->sole();
            $due = resolve(Deadlines::class)->between([$luce], $today->subMonth(), $today->addMonth());
            $september = collect($due)->first(fn (Deadline $deadline): bool => $deadline->occurrence->originalDate->toDateString() === '2026-10-23');
            $following = collect($due)->first(fn (Deadline $deadline): bool => $deadline->occurrence->date->toDateString() > '2026-10-23');

            expect($september?->title)->toBe('Bolletta luce di settembre')
                ->and($september?->occurrence->amount)->toBe(71.30)
                ->and($september?->isPaid())->toBeFalse()
                ->and($september?->isLate($today))->toBeTrue()
                ->and($following?->occurrence->date->toDateString())->toBe('2026-11-06')
                ->and($following?->occurrence->amount)->toBe(62.40)
                ->and($following?->title)->toBe('Luce');
        });
    });

    it('has the Panda loan', function (): void {
        insideBianchi(function (): void {
            $panda = Loan::query()->where('name', 'Finanziamento Panda')->sole();

            expect($panda->installments_paid)->toBe(32)
                ->and($panda->residual_amount)->toBe('3024.00')
                ->and($panda->end_date?->toDateString())->toBe('2028-02-10')
                ->and($panda->total_to_pay)->toBe('9072.00');
        });
    });
});
