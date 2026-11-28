<?php

declare(strict_types=1);

use App\Actions\Banks\SyncBankConnection;
use App\Contracts\BankFeed;
use App\Data\ReminderNoticeData;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\File;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Services\Banks\FakeBankFeed;
use App\Services\Deadlines;
use App\Services\Reminders\NoticeList;
use App\Support\Banks\BankEntry;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Tests\Support\EnelBill;

/**
 * The choices of the dual-fuel bill as the page sends them before the deadline: both supplies with their utility,
 * their expense to pay and their consumption.
 *
 * @return array<string, mixed>
 */
function dualFuelBankChoices(): array
{
    $supply = ['movement' => true, 'record' => true, 'consumption' => true, 'due' => true, 'targets' => ['movement' => 'auto', 'record' => 'auto']];

    return ['movement' => false, 'event' => false, 'records' => [], 'policy' => 'auto', 'card_type' => null, 'supplies' => [0 => $supply, 1 => $supply]];
}

/**
 * Whether the due date of a planned expense of the Bianchi is paid.
 */
function dualFuelBankPaid(PlannedExpense $planned, string $due): bool
{
    return resolve(Deadlines::class)->find($planned->refresh(), CarbonImmutable::parse($due, 'Europe/Rome'))->isPaid();
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->withHeader('Accept-Language', 'it');
    Http::preventStrayRequests();
    $this->feed = new FakeBankFeed;
    app()->instance(BankFeed::class, $this->feed);
    $this->seed(BianchiFamilySeeder::class);
    $this->bianchi = Family::query()->where('name', 'Bianchi')->sole();
    EnelBill::bianchi();
    [$this->luce, $this->gas, $this->account] = insideBianchi(function (): array {
        $account = FinancialAccount::query()->where('name', 'Conto BPER')->sole();
        $plans = PlannedExpense::query()->whereIn('title', ['Luce', 'Gas'])->orderBy('id')->get()->keyBy('title');
        foreach ($plans as $plan) {
            $plan->update(['bank_match' => 'ENEL ENERGIA', 'financial_account_id' => $account->id]);
        }

        return [$plans['Luce'], $plans['Gas'], $account];
    });
});

afterEach(function (): void {
    CurrentFamily::clear();
});

test('a member confirms the dual-fuel bill to pay, the bank debit pays both due dates, and «It was not this» takes them back', function (): void {
    $laura = User::query()->where('email', 'laura.bianchi@example.com')->sole();
    $marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $file = File::factory()->for($this->bianchi)->read(EnelBill::answer())->create(['to_sort' => true]);

    // 10/11: Laura confirms it before the deadline, as «To pay»
    $this->actingAs($laura)->post(route('import.store'), ['files' => [[
        'file_id' => $file->id, 'document' => ['title' => 'Bolletta luce e gas novembre 2026', 'status' => 'active', 'links' => []], 'also' => dualFuelBankChoices(),
    ]]])->assertSessionHasNoErrors()->assertRedirect();

    $document = insideBianchi(function () use ($file): Document {
        $document = Document::query()->where('file_id', $file->id)->sole();

        expect(Expense::query()->whereIn('planned_expense_id', [$this->luce->id, $this->gas->id])->whereDate('date', '>=', '2026-11-01')->count())->toBe(0)
            ->and(Document::query()->waitingFor([$this->luce->id])->pluck('id')->all())->toContain($document->id)
            ->and(Document::query()->waitingFor([$this->gas->id])->pluck('id')->all())->toContain($document->id);

        return $document;
    });

    // 21/11: the bank gives the one debit of the bill on Conto BPER
    $this->travelTo(CarbonImmutable::parse('2026-11-21 13:00', 'Europe/Rome'));
    $transaction = insideBianchi(function (): BankTransaction {
        $this->feed->gives([new BankEntry('enel-dual', CarbonImmutable::parse('2026-11-21'), null, -165.65, 'EUR', 'ADDEBITO SDD ENEL ENERGIA SPA', null, true)]);
        resolve(SyncBankConnection::class)->handle(BankConnection::query()->where('id', $this->account->bank_connection_id)->sole());

        return BankTransaction::query()->where('provider_id', 'enel-dual')->sole();
    });

    insideBianchi(function () use ($document, $transaction, $marco, $laura): void {
        $payments = Expense::query()->whereIn('planned_expense_id', [$this->luce->id, $this->gas->id])->whereDate('date', '2026-11-21')->orderBy('planned_expense_id')->get();

        expect($payments->map(fn (Expense $expense): array => [$expense->planned_expense_id, $expense->occurrence_date?->toDateString(), (float) $expense->amount])->all())
            ->toEqualCanonicalizing([[$this->luce->id, '2026-11-23', 96.25], [$this->gas->id, '2026-11-30', 69.40]])
            ->and($payments->pluck('bank_transaction_id')->unique()->all())->toBe([$transaction->id])
            ->and($payments->map(fn (Expense $expense): array => $expense->documents()->pluck('documents.id')->all())->all())->toBe([[$document->id], [$document->id]])
            ->and(dualFuelBankPaid($this->luce, '2026-11-23'))->toBeTrue()
            ->and(dualFuelBankPaid($this->gas, '2026-11-30'))->toBeTrue()
            ->and(ReminderNotice::query()->where('bank_transaction_id', $transaction->id)->pluck('user_id')->sort()->values()->all())
            ->toBe(collect([$marco->id, $laura->id])->sort()->values()->all());

        foreach ([$marco, $laura] as $user) {
            $rows = array_values(array_filter(
                resolve(NoticeList::class)->latest($user->id, CarbonImmutable::now('Europe/Rome'))['unread'],
                static fn (ReminderNoticeData $row): bool => str_contains($row->title, 'Luce e Gas'),
            ));
            expect($rows)->toHaveCount(1)
                ->and($rows[0]->title)->toBe('Pagata dalla banca · Luce e Gas novembre 2026');
        }
    });

    // «It was not this»: both payments go, both due dates are open again, the bill waits on both
    $this->actingAs($marco)->delete("/budget/bank/{$transaction->id}/payment")->assertRedirect(route('budget.bank.index'));

    insideBianchi(function () use ($document): void {
        expect(Expense::query()->whereIn('planned_expense_id', [$this->luce->id, $this->gas->id])->whereDate('date', '2026-11-21')->count())->toBe(0)
            ->and(dualFuelBankPaid($this->luce, '2026-11-23'))->toBeFalse()
            ->and(dualFuelBankPaid($this->gas, '2026-11-30'))->toBeFalse()
            ->and(Document::query()->waitingFor([$this->luce->id])->pluck('id')->all())->toContain($document->id)
            ->and(Document::query()->waitingFor([$this->gas->id])->pluck('id')->all())->toContain($document->id);
    });
});
