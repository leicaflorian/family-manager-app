<?php

declare(strict_types=1);

use App\Contracts\DocumentReader;
use App\Data\Banks\BankRuleSuggestion;
use App\Enums\BankTransactionStatus;
use App\Enums\Role;
use App\Exceptions\Reading\ReadingFailed;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeDocumentReader;

beforeEach(function (): void {
    config(['bank_feed.provider' => 'fake']);
    $this->marco = registerFamily();
    $this->marco->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->actingAs($this->marco);
    app()->instance(DocumentReader::class, (new FakeDocumentReader)->suggestsRule(new BankRuleSuggestion('SCUOLA.*DUE', 'The school.')));
});

/**
 * A transaction of the family of an account, a number of days ago.
 *
 * @param  array<string, mixed>  $state  what to change in the transaction
 */
function bankMatchEndpointsRow(User $user, int $daysAgo, array $state = []): BankTransaction
{
    return insideFamilyOf($user, function () use ($user, $daysAgo, $state): BankTransaction {
        $day = CarbonImmutable::today('Europe/Rome')->subDays($daysAgo)->toDateString();

        return BankTransaction::factory()->create([
            'family_id' => $user->family_id,
            'financial_account_id' => FinancialAccount::query()->first()?->id ?? FinancialAccount::factory()->create(['family_id' => $user->family_id])->id,
            'booked_on' => $day,
            'value_on' => $day,
            'amount' => '-45.00',
            'text' => 'PAGAMENTO CARTA SCUOLA PASSO A DUE',
            ...$state,
        ]);
    });
}

test('the preview counts the booked transactions of the last 90 days in the right direction', function (): void {
    foreach ([5, 35, 65, 85, 88, 89] as $days) {
        bankMatchEndpointsRow($this->marco, $days);
    }
    // outside the window, still pending, incoming for an expense, another text
    bankMatchEndpointsRow($this->marco, 120);
    bankMatchEndpointsRow($this->marco, 2, ['booked_on' => null, 'status' => BankTransactionStatus::Pending]);
    bankMatchEndpointsRow($this->marco, 3, ['amount' => '45.00']);
    bankMatchEndpointsRow($this->marco, 4, ['text' => 'ESSELUNGA']);
    // a transaction already linked to an expense still counts
    bankMatchEndpointsRow($this->marco, 6, ['expense_id' => insideFamilyOf($this->marco, fn (): int => Expense::factory()->create(['family_id' => $this->marco->family_id])->id)]);

    $response = $this->getJson(route('budget.bank.matches', ['pattern' => 'scuola.*due', 'kind' => 'expense']))->assertOk();

    expect($response->json('count'))->toBe(7)
        ->and($response->json('rows'))->toHaveCount(5)
        // the five newest, oldest first
        ->and(collect($response->json('rows'))->pluck('booked_on')->all())->toBe(collect($response->json('rows'))->pluck('booked_on')->sort()->values()->all());

    $this->getJson(route('budget.bank.matches', ['pattern' => 'SCUOLA', 'kind' => 'income']))->assertOk()->assertJsonPath('count', 1);
});

test('a rule tied to an account looks only at that account', function (): void {
    $first = bankMatchEndpointsRow($this->marco, 5);
    $other = insideFamilyOf($this->marco, fn (): FinancialAccount => FinancialAccount::factory()->create(['family_id' => $this->marco->family_id]));
    bankMatchEndpointsRow($this->marco, 6, ['financial_account_id' => $other->id]);

    $this->getJson(route('budget.bank.matches', ['pattern' => 'SCUOLA', 'kind' => 'expense']))->assertJsonPath('count', 2);
    $this->getJson(route('budget.bank.matches', ['pattern' => 'SCUOLA', 'kind' => 'expense', 'account_id' => $other->id]))->assertJsonPath('count', 1);
    $this->getJson(route('budget.bank.matches', ['pattern' => 'SCUOLA', 'kind' => 'expense', 'account_id' => $first->financial_account_id]))->assertJsonPath('count', 1);
});

test('a broken rule answers 422 with the message', function (): void {
    $this->getJson(route('budget.bank.matches', ['pattern' => '.*', 'kind' => 'expense']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.pattern.0', 'This rule does not work. Try writing it with the AI.');
    $this->getJson(route('budget.bank.matches', ['pattern' => '(unclosed', 'kind' => 'expense']))->assertUnprocessable();
});

test('the examples are the last 30 transactions to sort in the right direction', function (): void {
    foreach (range(1, 32) as $days) {
        bankMatchEndpointsRow($this->marco, $days);
    }
    bankMatchEndpointsRow($this->marco, 1, ['amount' => '45.00']);
    bankMatchEndpointsRow($this->marco, 1, ['ignored_at' => now()]);
    bankMatchEndpointsRow($this->marco, 1, ['expense_id' => insideFamilyOf($this->marco, fn (): int => Expense::factory()->create(['family_id' => $this->marco->family_id])->id)]);

    $response = $this->getJson(route('budget.bank.examples', ['kind' => 'expense']))->assertOk();

    expect($response->json('rows'))->toHaveCount(30)
        ->and($response->json('rows.0.booked_on'))->toBe(CarbonImmutable::today('Europe/Rome')->subDay()->toDateString());
});

test('a member gets 403 on the three routes', function (): void {
    $member = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);
    $this->actingAs($member);

    $this->getJson(route('budget.bank.matches', ['pattern' => 'SCUOLA', 'kind' => 'expense']))->assertForbidden();
    $this->getJson(route('budget.bank.examples', ['kind' => 'expense']))->assertForbidden();
    $this->postJson(route('budget.bank.rule'), ['examples' => ['SCUOLA'], 'kind' => 'expense'])->assertForbidden();
});

test('the three routes answer 404 while the feed is off', function (): void {
    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();

    $this->getJson(route('budget.bank.matches', ['pattern' => 'SCUOLA', 'kind' => 'expense']))->assertNotFound();
    $this->getJson(route('budget.bank.examples', ['kind' => 'expense']))->assertNotFound();
    $this->postJson(route('budget.bank.rule'), ['examples' => ['SCUOLA'], 'kind' => 'expense'])->assertNotFound();
});

test('the rule endpoint accepts pasted texts or transactions of the family, never another family', function (): void {
    $mine = bankMatchEndpointsRow($this->marco, 5);
    bankMatchEndpointsRow($this->marco, 35);

    $this->postJson(route('budget.bank.rule'), ['examples' => ['PAGAMENTO SCUOLA PASSO A DUE'], 'kind' => 'expense'])
        ->assertOk()
        ->assertJsonPath('pattern', 'SCUOLA.*DUE')
        ->assertJsonPath('explanation', 'The school.')
        ->assertJsonPath('count', 2);

    $this->postJson(route('budget.bank.rule'), ['transactions' => [$mine->id], 'kind' => 'expense'])
        ->assertOk()
        ->assertJsonPath('pattern', 'SCUOLA.*DUE');

    $stranger = Family::factory()->create();
    $foreign = BankTransaction::factory()->create(['family_id' => $stranger->id, 'text' => 'SCUOLA ALTRUI']);

    $this->postJson(route('budget.bank.rule'), ['transactions' => [$mine->id, $foreign->id], 'kind' => 'expense'])->assertNotFound();
    $this->postJson(route('budget.bank.rule'), ['kind' => 'expense'])->assertUnprocessable();
    $this->postJson(route('budget.bank.rule'), ['examples' => [str_repeat('A', 501)], 'kind' => 'expense'])->assertUnprocessable();
    $this->postJson(route('budget.bank.rule'), ['examples' => array_fill(0, 6, 'SCUOLA'), 'kind' => 'expense'])->assertUnprocessable();
});

test('a reading that is off or fails is told in the answer, never a 500', function (): void {
    app()->instance(DocumentReader::class, (new FakeDocumentReader)->off());
    $this->postJson(route('budget.bank.rule'), ['examples' => ['SCUOLA'], 'kind' => 'expense'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.examples.0', 'Automatic reading is not on.');

    app()->instance(DocumentReader::class, (new FakeDocumentReader)->fails(ReadingFailed::busy(new RuntimeException('busy'))));
    $this->postJson(route('budget.bank.rule'), ['examples' => ['SCUOLA'], 'kind' => 'expense'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.examples.0', 'The AI service is busy, try again in a while.');
});

test('it is throttled', function (): void {
    foreach (range(1, 20) as $attempt) {
        $this->postJson(route('budget.bank.rule'), ['examples' => ['SCUOLA'], 'kind' => 'expense'])->assertOk();
    }

    $this->postJson(route('budget.bank.rule'), ['examples' => ['SCUOLA'], 'kind' => 'expense'])->assertStatus(429);
});

test('the form of a planned expense carries the state of the bank and the example of rule_from', function (): void {
    $example = bankMatchEndpointsRow($this->marco, 5);
    $foreign = BankTransaction::factory()->create(['family_id' => Family::factory()->create()->id]);

    $this->get(route('budget.planned.create', ['kind' => 'expense', 'rule_from' => $example->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('budget/EditPlanned')
        ->where('bank', ['on' => true, 'ai' => true])
        ->where('rule_example.id', $example->id)
        ->where('rule_example.text', 'PAGAMENTO CARTA SCUOLA PASSO A DUE'));

    $this->get(route('budget.planned.create', ['kind' => 'expense', 'rule_from' => $foreign->id]))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('rule_example', null));
    $this->get(route('budget.planned.create', ['kind' => 'expense', 'rule_from' => 'abc']))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('rule_example', null));
    $this->get(route('budget.planned.create', ['kind' => 'income']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('rule_example', null));

    app()->instance(DocumentReader::class, (new FakeDocumentReader)->off());
    $this->get(route('budget.planned.create', ['kind' => 'expense']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('bank', ['on' => true, 'ai' => false]));

    $this->marco->family->forceFill(['bank_feed_enabled' => false])->save();
    $this->get(route('budget.planned.create', ['kind' => 'expense', 'rule_from' => $example->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('bank', ['on' => false, 'ai' => false])
        ->where('rule_example', null));
});
