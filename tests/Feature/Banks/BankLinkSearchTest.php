<?php

declare(strict_types=1);

use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-05 07:00', 'Europe/Rome'));
    config(['bank_feed.provider' => 'fake']);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
});

/**
 * The family id of the Bianchi.
 */
function bankLinkSearchFamily(): int
{
    return Family::query()->where('name', 'Bianchi')->sole()->id;
}

/**
 * A booked transaction of the Bianchi still to sort.
 *
 * @param  array<string, mixed>  $attributes  what differs from a debit of 20,00 € on the 3rd
 */
function bankLinkSearchTransaction(array $attributes = []): BankTransaction
{
    return insideBianchi(fn (): BankTransaction => BankTransaction::factory()->create([
        'family_id' => bankLinkSearchFamily(),
        'amount' => '-20.00', 'booked_on' => '2026-11-03', 'value_on' => '2026-11-03', ...$attributes,
    ]));
}

/**
 * Asks the search for a transaction.
 *
 * @return TestResponse<JsonResponse>
 */
function bankLinkSearchAsk(mixed $test, BankTransaction $transaction, string $query = ''): TestResponse
{
    return $test->actingAs($test->marco)->getJson(route('budget.bank.candidates', ['transaction' => $transaction->id, 'q' => $query]));
}

test('finds a planned income whose closest due date is already paid by hand', function (): void {
    $transaction = bankLinkSearchTransaction(['amount' => '189.20', 'booked_on' => '2026-11-17', 'value_on' => '2026-11-17']);
    insideBianchi(function (): void {
        $planned = PlannedIncome::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Assegno Unico', 'amount' => 189.2, 'start_date' => '2026-01-18']);
        Income::factory()->create(['family_id' => bankLinkSearchFamily(), 'planned_income_id' => $planned->id, 'amount' => 189.2, 'date' => '2026-11-18', 'occurrence_date' => '2026-11-18']);
    });

    bankLinkSearchAsk($this, $transaction, 'assegno')->assertOk()
        ->assertJsonCount(1, 'planned')
        ->assertJsonPath('planned.0.title', 'Assegno Unico')
        ->assertJsonPath('planned.0.kind', 'income')
        ->assertJsonPath('planned.0.due_on', '2026-11-18')
        ->assertJsonPath('planned.0.paid_on', '2026-11-18')
        ->assertJsonPath('planned.0.ended', false);
});

test('finds an ended planned expense with a far unpaid due date', function (): void {
    $transaction = bankLinkSearchTransaction(['amount' => '-418.00', 'booked_on' => '2026-08-20', 'value_on' => '2026-08-20']);
    insideBianchi(fn (): PlannedExpense => PlannedExpense::factory()->create([
        'family_id' => bankLinkSearchFamily(), 'title' => 'Assicurazione auto', 'amount' => 418, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31',
    ]));

    bankLinkSearchAsk($this, $transaction, 'assicurazione')->assertOk()
        ->assertJsonCount(1, 'planned')
        ->assertJsonPath('planned.0.title', 'Assicurazione auto')
        ->assertJsonPath('planned.0.due_on', '2026-08-01')
        ->assertJsonPath('planned.0.paid_on', null)
        ->assertJsonPath('planned.0.ended', true);
});

test('finds a recorded single expense of years ago by a word of its title', function (): void {
    $transaction = bankLinkSearchTransaction();
    insideBianchi(function (): void {
        Expense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Tagliando Panda', 'amount' => 180, 'date' => '2022-03-10']);
        Expense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Pizza', 'amount' => 20, 'date' => '2026-10-30']);
    });

    bankLinkSearchAsk($this, $transaction, 'tagliando panda')->assertOk()
        ->assertJsonCount(1, 'recorded')
        ->assertJsonPath('recorded.0.title', 'Tagliando Panda')
        ->assertJsonPath('recorded.0.kind', 'expense')
        ->assertJsonPath('recorded.0.date', '2022-03-10')
        ->assertJsonPath('recorded.0.amount', 180);
});

test('finds recorded movements by the amount written the Italian way', function (): void {
    $transaction = bankLinkSearchTransaction();
    insideBianchi(function (): void {
        Expense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Libri', 'amount' => 23.90, 'date' => '2026-05-01']);
        Expense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Cena', 'amount' => 45, 'date' => '2026-05-02']);
    });

    bankLinkSearchAsk($this, $transaction, '23,90')->assertOk()
        ->assertJsonCount(1, 'recorded')
        ->assertJsonPath('recorded.0.title', 'Libri');
});

test('never offers a movement already linked to another bank transaction', function (): void {
    $transaction = bankLinkSearchTransaction();
    insideBianchi(function (): void {
        $taken = Expense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Tagliando preso', 'amount' => 20, 'date' => '2026-10-30']);
        BankTransaction::factory()->create(['family_id' => bankLinkSearchFamily(), 'expense_id' => $taken->id]);
        Expense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Tagliando libero', 'amount' => 20, 'date' => '2026-10-30']);
    });

    bankLinkSearchAsk($this, $transaction, 'tagliando')->assertOk()
        ->assertJsonCount(1, 'recorded')
        ->assertJsonPath('recorded.0.title', 'Tagliando libero');
});

test('the empty search gives the near due dates and the same amount of the last 30 days', function (): void {
    $transaction = bankLinkSearchTransaction();
    insideBianchi(function (): void {
        PlannedExpense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Luce', 'amount' => 20, 'start_date' => '2026-01-05']);
        PlannedExpense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Bollo', 'amount' => 20, 'start_date' => '2027-06-01']);
        Expense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Recent same', 'amount' => 20, 'date' => '2026-10-20']);
        Expense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Old same', 'amount' => 20, 'date' => '2026-09-01']);
        Expense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Recent other', 'amount' => 30, 'date' => '2026-10-20']);
    });

    $response = bankLinkSearchAsk($this, $transaction)->assertOk();

    expect(collect($response->json('planned'))->pluck('title')->all())->toBe(['Luce'])
        ->and(collect($response->json('recorded'))->pluck('title')->all())->toBe(['Recent same']);
});

test('searches only the kind of the transaction', function (): void {
    $transaction = bankLinkSearchTransaction();
    insideBianchi(function (): void {
        PlannedExpense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Assegno spese', 'start_date' => '2026-01-05']);
        PlannedIncome::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Assegno entrata', 'start_date' => '2026-01-05']);
        Expense::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Assegno pagato', 'date' => '2026-10-30']);
        Income::factory()->create(['family_id' => bankLinkSearchFamily(), 'title' => 'Assegno ricevuto', 'date' => '2026-10-30']);
    });

    $response = bankLinkSearchAsk($this, $transaction, 'assegno')->assertOk();

    expect(collect($response->json('planned'))->pluck('title')->all())->toBe(['Assegno spese'])
        ->and(collect($response->json('recorded'))->pluck('title')->all())->toBe(['Assegno pagato']);
});

test("another family's transaction gives 404", function (): void {
    $foreign = BankTransaction::factory()->create(['family_id' => Family::factory()->create()->id]);

    bankLinkSearchAsk($this, $foreign, 'assegno')->assertNotFound();
});
