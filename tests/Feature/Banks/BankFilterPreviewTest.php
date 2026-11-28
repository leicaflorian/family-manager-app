<?php

declare(strict_types=1);

use App\Enums\BankFilterAction;
use App\Enums\BankMatchSource;
use App\Enums\Role;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Support\PaymentTitle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-26 07:00', 'Europe/Rome'));
    config(['bank_feed.provider' => 'fake']);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->actingAs($this->marco);
});

/** The BancoPosta account of the Bianchi, made once. */
function bankFilterPreviewAccount(): FinancialAccount
{
    return insideBianchi(fn (): FinancialAccount => FinancialAccount::query()->where('name', 'Conto BancoPosta')->first()
        ?? FinancialAccount::factory()->create(['family_id' => test()->marco->family_id, 'name' => 'Conto BancoPosta', 'last_four_digits' => '3307']));
}

/**
 * A booked transaction of the Bianchi on BancoPosta.
 *
 * @param  array<string, mixed>  $state  more columns
 */
function bankFilterPreviewRow(string $text, string $amount, string $day, array $state = []): BankTransaction
{
    $account = bankFilterPreviewAccount();

    return insideBianchi(fn (): BankTransaction => BankTransaction::factory()->create([
        'family_id' => test()->marco->family_id,
        'financial_account_id' => $account->id,
        'text' => $text,
        'amount' => $amount,
        'booked_on' => $day,
        'value_on' => $day,
        ...$state,
    ]));
}

/**
 * Asks the preview of a filter that creates «Ricarica Satispay» for SATISPAY, with more fields.
 *
 * @param  array<string, mixed>  $fields  what to change in the form
 */
function bankFilterPreviewAsk(array $fields = []): TestResponse
{
    return test()->postJson(route('budget.bank.filters.preview'), [
        'name' => 'Satispay',
        'contains' => ['SATISPAY'],
        'excludes' => ['COMMISSIONI'],
        'direction' => 'expense',
        'fee_mode' => 'none',
        'action' => 'create',
        'title' => 'Ricarica Satispay',
        ...$fields,
    ]);
}

/**
 * An expense of the Bianchi recorded by hand.
 */
function bankFilterPreviewExpense(string $title, string $amount, string $day): Expense
{
    return insideBianchi(fn (): Expense => Expense::factory()->create([
        'family_id' => test()->marco->family_id, 'title' => $title, 'amount' => $amount, 'date' => $day,
    ]));
}

test('lists what the criteria take in the last 90 days with the words found', function (): void {
    $new = bankFilterPreviewRow('DOMICILIAZIONE SATISPAY EUROPE', '-10.00', '2026-11-24');
    $older = bankFilterPreviewRow('DOMICILIAZIONE SATISPAY EUROPE', '-20.00', '2026-09-10');
    bankFilterPreviewRow('DOMICILIAZIONE SATISPAY EUROPE', '-30.00', '2026-08-01');
    bankFilterPreviewRow('POS ESSELUNGA', '-40.00', '2026-11-20');
    bankFilterPreviewRow('ACCREDITO SATISPAY', '15.00', '2026-11-21');

    $response = bankFilterPreviewAsk()->assertOk();

    expect(array_column(array_column($response->json('rows'), 'transaction'), 'id'))->toBe([$new->id, $older->id])
        ->and($response->json('rows.0.hits'))->toBe(['SATISPAY'])
        ->and($response->json('rows.0.transaction.account'))->toBe('Conto BancoPosta ·· 3307')
        ->and($response->json('rows.0.fee'))->toBeNull()
        ->and($response->json('rows.0.taken_by'))->toBeNull();
});

test('says create for one and link for the one already recorded', function (): void {
    $recorded = bankFilterPreviewExpense('Ricarica Satispay', '10.00', '2026-11-23');
    bankFilterPreviewRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24');
    bankFilterPreviewRow('DOMICILIAZIONE SATISPAY', '-20.00', '2026-11-10');

    $response = bankFilterPreviewAsk(['title_with_month' => true])->assertOk();

    expect($response->json('rows.0.will'))->toBe('link')
        ->and($response->json('rows.0.will_title'))->toBe($recorded->title)
        ->and($response->json('rows.0.will_on'))->toBe('2026-11-23')
        ->and($response->json('rows.1.will'))->toBe('create')
        ->and($response->json('rows.1.will_title'))->toBe(PaymentTitle::for('Ricarica Satispay', CarbonImmutable::parse('2026-11-10')))
        ->and(Expense::query()->count())->toBe(1);
});

test('the summary counts 10 to create and 1 to link', function (): void {
    bankFilterPreviewExpense('Ricarica Satispay', '10.00', '2026-11-24');
    bankFilterPreviewRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24');

    foreach (range(1, 10) as $week) {
        bankFilterPreviewRow('DOMICILIAZIONE SATISPAY', '-20.00', CarbonImmutable::parse('2026-11-17')->subWeeks($week - 1)->toDateString());
    }

    $response = bankFilterPreviewAsk()->assertOk();

    expect($response->json('summary'))->toBe(['pay' => 0, 'create' => 10, 'link' => 1, 'transfer' => 0, 'ignore' => 0])
        ->and($response->json('conflicts'))->toBe(0)
        ->and(BankTransaction::query()->whereNotNull('expense_id')->count())->toBe(0);
});

test('a transaction another filter takes is a conflict and names it', function (): void {
    insideBianchi(fn (): BankFilter => BankFilter::factory()->create([
        'family_id' => $this->marco->family_id, 'name' => 'Satispay ricariche', 'contains' => ['SATISPAY'],
    ]));
    bankFilterPreviewRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24');
    bankFilterPreviewRow('DOMICILIAZIONE SATISPAY', '-20.00', '2026-11-17', ['ignored_at' => now()]);

    $response = bankFilterPreviewAsk(['contains' => ['SATISPAY', 'DOMICILIAZIONE']])->assertOk();

    expect($response->json('rows.0.taken_by'))->toBe('Satispay ricariche')
        ->and($response->json('rows.1.taken_by'))->toBeNull()
        ->and($response->json('conflicts'))->toBe(1);
});

test('the filter being changed is not a conflict with itself', function (): void {
    $filter = insideBianchi(fn (): BankFilter => BankFilter::factory()->create([
        'family_id' => $this->marco->family_id, 'name' => 'Satispay', 'contains' => ['SATISPAY'],
    ]));
    bankFilterPreviewRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24');

    $response = bankFilterPreviewAsk(['filter' => $filter->id])->assertOk();

    expect($response->json('rows.0.taken_by'))->toBeNull()
        ->and($response->json('conflicts'))->toBe(0);
});

test('a linked one is shown and not counted', function (): void {
    $expense = bankFilterPreviewExpense('Ricarica Satispay', '10.00', '2026-11-24');
    $linked = bankFilterPreviewRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24', ['expense_id' => $expense->id, 'matched_by' => BankMatchSource::Duplicate]);
    bankFilterPreviewRow('DOMICILIAZIONE SATISPAY', '-20.00', '2026-11-17');

    $response = bankFilterPreviewAsk()->assertOk();

    expect($response->json('rows.0.transaction.id'))->toBe($linked->id)
        ->and($response->json('rows.0.will'))->toBe('linked')
        ->and($response->json('rows.0.transaction.link.title'))->toBe('Ricarica Satispay')
        ->and($response->json('summary'))->toBe(['pay' => 0, 'create' => 1, 'link' => 0, 'transfer' => 0, 'ignore' => 0]);
});

test('shows the fee found by the words of the filter', function (): void {
    bankFilterPreviewRow('DOMICILIAZIONE SATISPAY EUROPE', '-10.00', '2026-11-24');
    $fee = bankFilterPreviewRow('COMMISSIONI SATISPAY', '-0.40', '2026-11-25');

    $response = bankFilterPreviewAsk(['fee_mode' => 'words', 'fee_contains' => ['COMMISSIONI', 'SATISPAY']])->assertOk();

    expect($response->json('rows'))->toHaveCount(1)
        ->and($response->json('rows.0.fee.id'))->toBe($fee->id)
        ->and($response->json('rows.0.fee.amount'))->toBe(-0.4)
        ->and($response->json('rows.0.fee.booked_on'))->toBe('2026-11-25');
});

test('says pay with the due date for a filter that pays a planned expense', function (): void {
    $luce = insideBianchi(fn (): PlannedExpense => PlannedExpense::factory()->create([
        'family_id' => $this->marco->family_id, 'title' => 'Luce', 'amount' => 72, 'fee_amount' => null,
        'start_date' => '2026-01-05', 'financial_account_id' => null,
    ]));
    bankFilterPreviewRow('ADDEBITO SDD EDISON ENERGIA', '-72.40', '2026-11-05');

    $response = bankFilterPreviewAsk(['contains' => ['EDISON'], 'action' => 'pay', 'planned_id' => $luce->id])->assertOk();

    expect($response->json('rows.0.will'))->toBe('pay')
        ->and($response->json('rows.0.will_title'))->toBe('Luce')
        ->and($response->json('rows.0.will_on'))->toBe('2026-11-05')
        ->and($response->json('summary.pay'))->toBe(1)
        ->and(Expense::query()->count())->toBe(0);
});

test('without safe words the preview lists nothing', function (): void {
    bankFilterPreviewRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24');

    bankFilterPreviewAsk(['contains' => ['SA']])->assertOk()->assertJsonPath('rows', []);
});

test('a planned item of another family gives 404', function (): void {
    $rossi = registerFamily('Rossi', 'luca@example.com');
    $theirs = insideFamilyOf($rossi, fn (): PlannedExpense => PlannedExpense::factory()->create(['family_id' => $rossi->family_id]));

    bankFilterPreviewAsk(['action' => 'pay', 'planned_id' => $theirs->id])->assertNotFound();
});

test('a member sees the preview too', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);
    $this->actingAs($member);
    bankFilterPreviewRow('DOMICILIAZIONE SATISPAY', '-10.00', '2026-11-24');

    bankFilterPreviewAsk(['action' => BankFilterAction::Ignore->value])->assertOk()->assertJsonPath('rows.0.will', 'ignore');
});
