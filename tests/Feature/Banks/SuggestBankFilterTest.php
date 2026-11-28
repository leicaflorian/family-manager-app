<?php

declare(strict_types=1);

use App\Actions\Banks\SuggestBankFilter;
use App\Contracts\DocumentReader;
use App\Enums\ReadingPurpose;
use App\Enums\Role;
use App\Exceptions\Reading\ReadingFailed;
use App\Models\AiReading;
use App\Models\BankTransaction;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\Reading\BankFilterSuggestion;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeDocumentReader;

/**
 * Signs in an account of a new family and binds a fake reader that proposes this filter.
 *
 * @param  BankFilterSuggestion  $suggestion  what the model proposes
 * @return array{0: Family, 1: FakeDocumentReader}
 */
function suggestBankFilterSetup(BankFilterSuggestion $suggestion): array
{
    Http::preventStrayRequests();
    $family = Family::factory()->create();
    test()->actingAs(User::factory()->for($family)->create());
    $reader = (new FakeDocumentReader)->suggestsFilter($suggestion);
    app()->instance(DocumentReader::class, $reader);

    return [$family, $reader];
}

/**
 * A booked euro transaction of the family with this text.
 *
 * @param  Family  $family  the family
 * @param  string  $text  the text of the bank
 * @param  string  $amount  the amount with its sign
 */
function suggestBankFilterTransaction(Family $family, string $text, string $amount = '-42.00'): BankTransaction
{
    return BankTransaction::factory()->for($family)->create(['text' => $text, 'amount' => $amount, 'counterparty' => 'Ottavio Bellandi']);
}

/**
 * Asks the filter of the transactions in the family.
 *
 * @param  Family  $family  the family
 * @param  list<BankTransaction>  $keep  the transactions the filter must take
 * @param  list<BankTransaction>  $drop  the transactions it must leave
 * @param  string  $mode  `words` or `fill`
 */
function suggestBankFilterRun(Family $family, array $keep, array $drop = [], string $mode = 'words'): BankFilterSuggestion
{
    return CurrentFamily::using($family->id, fn (): BankFilterSuggestion => resolve(SuggestBankFilter::class)->handle($keep, $drop, $mode, 'it'));
}

/**
 * A suggestion of the model with these words and, for the fill mode, these names.
 *
 * @param  list<string>  $contains  the words
 * @param  list<string>  $excludes  the words to leave out
 * @param  string|null  $category  the category proposed
 * @param  string|null  $organization  the organization proposed
 */
function suggestBankFilterAnswer(array $contains, array $excludes = [], ?string $category = null, ?string $organization = null): BankFilterSuggestion
{
    return new BankFilterSuggestion($contains, $excludes, 'Bolletta luce', $category, $organization, 'Prende le bollette della luce.');
}

test('sends only normalised texts and the names of categories, organizations and planned items', function (): void {
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(json_decode((string) file_get_contents(base_path('tests/Fixtures/ai/gemini-bank-filter.json')), true, flags: JSON_THROW_ON_ERROR))]);
    config([
        'document_reader.provider' => 'gemini',
        'document_reader.model' => 'gemini-3.8-flash',
        'ai.providers.gemini.key' => 'gemini-test-key',
    ]);
    $family = Family::factory()->create(['name' => 'Famiglia Zanzibar']);
    $this->actingAs(User::factory()->for($family)->create(['name' => 'Giuseppina Rossetti']));
    Person::factory()->for($family)->create(['first_name' => 'Ottavio', 'last_name' => 'Bellandi']);
    PlannedExpense::factory()->for($family)->create(['title' => 'Mutuo casa']);
    PlannedIncome::factory()->for($family)->create(['title' => 'Pensione nonna']);
    $category = ExpenseCategory::factory()->for($family)->create(['name' => 'Rimborsi']);
    $organization = Organization::factory()->for($family)->create(['name' => 'Poste Italiane']);
    $keep = [
        suggestBankFilterTransaction($family, 'Accredito  cashback ScontiPoste 0001', '3.47'),
        suggestBankFilterTransaction($family, 'ACCREDITO CASHBACK scontiposte 0002', '3.47'),
    ];
    $drop = [suggestBankFilterTransaction($family, 'Accredito bonifico stipendio', '1834.56')];

    $suggestion = suggestBankFilterRun($family, $keep, $drop, 'fill');

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $body = $request->body();

        return str_contains($body, 'ACCREDITO CASHBACK SCONTIPOSTE 0001')
            && str_contains($body, 'ACCREDITO CASHBACK SCONTIPOSTE 0002')
            && str_contains($body, 'ACCREDITO BONIFICO STIPENDIO')
            && str_contains($body, 'Rimborsi')
            && str_contains($body, 'Poste Italiane')
            && str_contains($body, 'Mutuo casa')
            && str_contains($body, 'Pensione nonna')
            && ! str_contains($body, '3.47')
            && ! str_contains($body, '1834')
            && ! str_contains($body, 'Zanzibar')
            && ! str_contains($body, 'Giuseppina')
            && ! str_contains($body, 'Rossetti')
            && ! str_contains($body, 'Bellandi');
    });
    expect($suggestion->contains)->toBe(['CASHBACK', 'SCONTIPOSTE'])
        ->and($suggestion->title)->toBe('Cashback ScontiPoste')
        ->and($suggestion->category)->toBe('Rimborsi')
        ->and($suggestion->categoryId)->toBe($category->id)
        ->and($suggestion->organization)->toBe('Poste Italiane')
        ->and($suggestion->organizationId)->toBe($organization->id)
        ->and($suggestion->organizationIsNew())->toBeFalse()
        ->and($suggestion->explanation)->toBe('Prende i cashback di ScontiPoste.');
});

test('the texts reach the model without IBAN, amounts and the names of the family', function (): void {
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(json_decode((string) file_get_contents(base_path('tests/Fixtures/ai/gemini-bank-filter.json')), true, flags: JSON_THROW_ON_ERROR))]);
    config([
        'document_reader.provider' => 'gemini',
        'document_reader.model' => 'gemini-3.8-flash',
        'ai.providers.gemini.key' => 'gemini-test-key',
    ]);
    $family = Family::factory()->create();
    $this->actingAs(User::factory()->for($family)->create(['name' => 'Giuseppina Rossetti']));
    Person::factory()->for($family)->create(['first_name' => 'Ottavio', 'last_name' => 'Bellandi']);
    $kept = suggestBankFilterTransaction($family, 'Bonifico a Ottavio Bellandi IT60X0542811101000000123456 42,00 EUR Netflix Rossetti carta ****1234 spese 42 EUR');

    $suggestion = suggestBankFilterRun($family, [$kept]);

    Http::assertSent(function (Request $request): bool {
        $body = $request->body();

        return str_contains($body, 'BONIFICO A PERSONA PERSONA IBAN IMPORTO NETFLIX PERSONA CARTA CARTA SPESE IMPORTO')
            && ! str_contains($body, '1234')
            && ! str_contains($body, '42 EUR')
            && ! str_contains($body, 'IT60X0542811101000000123456')
            && ! str_contains($body, '42,00')
            && ! str_contains($body, 'OTTAVIO')
            && ! str_contains($body, 'BELLANDI')
            && ! str_contains($body, 'ROSSETTI');
    });
    // the fallback still reads the text as the bank gave it
    expect($suggestion->contains)->toBe(['OTTAVIO', 'BELLANDI', 'EUR', 'NETFLIX']);
});

test('an organization of the family is found even when it was not among the names sent', function (): void {
    [$family] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL'], [], null, 'zeta energia'));
    Organization::factory()->for($family)->count(200)->sequence(fn ($sequence): array => ['name' => sprintf('Aaa %03d', $sequence->index)])->create();
    $zeta = Organization::factory()->for($family)->create(['name' => 'Zeta Energia']);

    $suggestion = suggestBankFilterRun($family, [suggestBankFilterTransaction($family, 'Addebito Enel Energia')], [], 'fill');

    expect($suggestion->organizationId)->toBe($zeta->id)
        ->and($suggestion->organization)->toBe('Zeta Energia')
        ->and($suggestion->organizationIsNew())->toBeFalse();
});

test('the words mode sends no names and gives only the words', function (): void {
    [$family, $reader] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL'], [], 'Casa', 'Enel'));
    ExpenseCategory::factory()->for($family)->create(['name' => 'Casa']);

    $suggestion = suggestBankFilterRun($family, [suggestBankFilterTransaction($family, 'Addebito Enel Energia 01')]);

    expect($reader->filterRequests()[0]['names'])->toBe(['categories' => [], 'organizations' => [], 'planned' => []])
        ->and($suggestion->contains)->toBe(['ENEL'])
        ->and($suggestion->title)->toBeNull()
        ->and($suggestion->category)->toBeNull()
        ->and($suggestion->organization)->toBeNull();
});

test('a suggestion that takes every kept movement and no dropped one is kept', function (): void {
    [$family] = suggestBankFilterSetup(suggestBankFilterAnswer(['enel'], ['gas']));
    $keep = [suggestBankFilterTransaction($family, 'Addebito Enel Energia luce 01')];
    $drop = [suggestBankFilterTransaction($family, 'Addebito Enel Energia gas 02')];

    $suggestion = suggestBankFilterRun($family, $keep, $drop);

    expect($suggestion->contains)->toBe(['ENEL'])
        ->and($suggestion->excludes)->toBe(['GAS'])
        ->and($suggestion->explanation)->toBe('Prende le bollette della luce.');
});

test('a suggestion that misses a kept movement is replaced by the words of the first example', function (): void {
    [$family] = suggestBankFilterSetup(suggestBankFilterAnswer(['GAS']));
    $keep = [
        suggestBankFilterTransaction($family, 'Addebito Enel Energia fattura 123'),
        suggestBankFilterTransaction($family, 'Addebito Enel Energia fattura 456'),
    ];

    $suggestion = suggestBankFilterRun($family, $keep);

    expect($suggestion->contains)->toBe(['ENEL', 'ENERGIA', 'FATTURA'])
        ->and($suggestion->excludes)->toBe([])
        ->and($suggestion->explanation)->toBe('Riconosce i movimenti con queste parole: ENEL ENERGIA FATTURA');
});

test('one that takes a dropped movement too', function (): void {
    [$family] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL']));
    $keep = [suggestBankFilterTransaction($family, 'Addebito Enel Energia luce 123')];
    $drop = [suggestBankFilterTransaction($family, 'Addebito Enel Energia gas 456')];

    expect(suggestBankFilterRun($family, $keep, $drop)->contains)->toBe(['ENEL', 'ENERGIA', 'LUCE']);
});

test('one with only short words is replaced too', function (): void {
    [$family] = suggestBankFilterSetup(suggestBankFilterAnswer(['EN', '12']));

    expect(suggestBankFilterRun($family, [suggestBankFilterTransaction($family, 'Addebito Enel Energia 12')])->contains)->toBe(['ENEL', 'ENERGIA']);
});

test('a category that is not of the family is dropped', function (): void {
    [$family] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL'], [], 'Rimborsi'));
    ExpenseCategory::factory()->for($family)->create(['name' => 'Casa']);
    ExpenseCategory::factory()->for(Family::factory()->create())->create(['name' => 'Rimborsi']);

    $suggestion = suggestBankFilterRun($family, [suggestBankFilterTransaction($family, 'Addebito Enel Energia')], [], 'fill');

    expect($suggestion->category)->toBeNull()
        ->and($suggestion->categoryId)->toBeNull()
        ->and($suggestion->title)->toBe('Bolletta luce');
});

test('a category of the family is found whatever the case', function (): void {
    [$family] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL'], [], 'CASA'));
    $category = ExpenseCategory::factory()->for($family)->create(['name' => 'Casa']);

    $suggestion = suggestBankFilterRun($family, [suggestBankFilterTransaction($family, 'Addebito Enel Energia')], [], 'fill');

    expect($suggestion->category)->toBe('Casa')->and($suggestion->categoryId)->toBe($category->id);
});

test('an unknown organization comes back as a new name to confirm', function (): void {
    [$family] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL'], [], null, 'Enel Energia'));
    Organization::factory()->for($family)->create(['name' => 'Hera']);
    Organization::factory()->for(Family::factory()->create())->create(['name' => 'Enel Energia']);

    $suggestion = suggestBankFilterRun($family, [suggestBankFilterTransaction($family, 'Addebito Enel Energia')], [], 'fill');

    expect($suggestion->organization)->toBe('Enel Energia')
        ->and($suggestion->organizationId)->toBeNull()
        ->and($suggestion->organizationIsNew())->toBeTrue();
});

test('records the reading and its cost once', function (): void {
    [$family, $reader] = suggestBankFilterSetup(suggestBankFilterAnswer(['GAS']));
    $user = auth()->user();

    // the answer misses the example: the words of the example take its place, and nothing is asked again
    suggestBankFilterRun($family, [suggestBankFilterTransaction($family, 'Addebito Enel Energia')]);

    $row = AiReading::query()->withoutGlobalScopes()->sole();
    expect($reader->filters())->toBe(1)
        ->and($row->purpose)->toBe(ReadingPurpose::BankFilter)
        ->and($row->user_id)->toBe($user->id)
        ->and($row->family_id)->toBe($family->id)
        ->and($row->file_id)->toBeNull()
        ->and($row->cost_usd)->not->toBeNull();
});

test('an answer that was paid but is unusable gives the words of the first example without a second call', function (): void {
    [$family, $reader] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL']));
    $reader->fails(ReadingFailed::invalidAnswer());

    $suggestion = suggestBankFilterRun($family, [suggestBankFilterTransaction($family, 'Addebito Enel Energia')]);

    expect($suggestion->contains)->toBe(['ENEL', 'ENERGIA'])
        ->and($reader->filters())->toBe(1)
        ->and(AiReading::query()->withoutGlobalScopes()->sole()->purpose)->toBe(ReadingPurpose::BankFilter);
});

test('a failure with no answer is thrown and costs nothing', function (): void {
    [$family, $reader] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL']));
    $reader->fails(ReadingFailed::unreachable(new RuntimeException('down')));

    expect(fn (): BankFilterSuggestion => suggestBankFilterRun($family, [suggestBankFilterTransaction($family, 'Addebito Enel')]))->toThrow(ReadingFailed::class)
        ->and(AiReading::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('refused while the reading is off or the month is used up', function (string $case, string $message): void {
    [$family, $reader] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL']));

    if ($case === 'off') {
        $reader->off();
    } else {
        config(['document_reader.monthly_limit' => 1]);
        AiReading::factory()->create(['family_id' => $family->id, 'purpose' => ReadingPurpose::Document]);
    }

    $refused = null;

    try {
        suggestBankFilterRun($family, [suggestBankFilterTransaction($family, 'Addebito Enel Energia')]);
    } catch (ValidationException $e) {
        $refused = $e->validator->errors()->first('keep');
    }

    expect($refused)->toStartWith($message)
        ->and($reader->filters())->toBe(0);
})->with([
    'off' => ['off', 'La lettura automatica non è attiva.'],
    'month used up' => ['used', 'Hai usato tutte le letture automatiche di'],
]);

/**
 * Turns the bank link on for the family and signs in an administrator of it.
 *
 * @param  Family  $family  the family
 */
function suggestBankFilterFeedOn(Family $family): void
{
    config(['bank_feed.provider' => 'fake']);
    $family->forceFill(['bank_feed_enabled' => true])->save();
    test()->actingAs(User::factory()->for($family)->create(['role' => Role::Admin]));
}

test('the route answers the suggestion as JSON with the checked names', function (): void {
    [$family] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL'], [], 'Bollette', 'Enel Energia'));
    suggestBankFilterFeedOn($family);
    $category = ExpenseCategory::factory()->for($family)->create(['name' => 'Bollette']);
    $keep = suggestBankFilterTransaction($family, 'Addebito Enel Energia');
    $drop = suggestBankFilterTransaction($family, 'Bonifico a Mario');

    $this->postJson(route('budget.bank.filters.suggest'), ['keep' => [$keep->id], 'drop' => [$drop->id], 'mode' => 'fill'])
        ->assertOk()
        ->assertJsonPath('contains', ['ENEL'])
        ->assertJsonPath('excludes', [])
        ->assertJsonPath('title', 'Bolletta luce')
        ->assertJsonPath('category', 'Bollette')
        ->assertJsonPath('category_id', $category->id)
        ->assertJsonPath('organization', 'Enel Energia')
        ->assertJsonPath('organization_id', null)
        ->assertJsonPath('organization_is_new', true)
        ->assertJsonPath('explanation', 'Prende le bollette della luce.');
});

test('the words mode gives no title, category or organization', function (): void {
    [$family, $reader] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL'], [], 'Bollette', 'Enel Energia'));
    suggestBankFilterFeedOn($family);
    $keep = suggestBankFilterTransaction($family, 'Addebito Enel Energia');

    $this->postJson(route('budget.bank.filters.suggest'), ['keep' => [$keep->id], 'mode' => 'words'])
        ->assertOk()
        ->assertJsonPath('title', null)
        ->assertJsonPath('category', null)
        ->assertJsonPath('organization_is_new', false);

    expect($reader->filters())->toBe(1);
});

test('a movement of another family answers 404, in keep or in drop, and nothing is asked', function (): void {
    [$family, $reader] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL']));
    suggestBankFilterFeedOn($family);
    $mine = suggestBankFilterTransaction($family, 'Addebito Enel Energia');
    $foreign = BankTransaction::factory()->for(Family::factory()->create())->create(['text' => 'Altrui']);

    $this->postJson(route('budget.bank.filters.suggest'), ['keep' => [$mine->id, $foreign->id], 'mode' => 'words'])->assertNotFound();
    $this->postJson(route('budget.bank.filters.suggest'), ['keep' => [$mine->id], 'drop' => [$foreign->id], 'mode' => 'words'])->assertNotFound();
    $this->postJson(route('budget.bank.filters.suggest'), ['keep' => [999999], 'mode' => 'words'])->assertNotFound();

    expect($reader->filters())->toBe(0);
});

test('an empty keep, a wrong mode or too many ids answer 422', function (): void {
    [$family, $reader] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL']));
    suggestBankFilterFeedOn($family);
    $mine = suggestBankFilterTransaction($family, 'Addebito Enel Energia');

    $this->postJson(route('budget.bank.filters.suggest'), ['keep' => [], 'mode' => 'words'])->assertUnprocessable()->assertJsonValidationErrors('keep');
    $this->postJson(route('budget.bank.filters.suggest'), ['mode' => 'words'])->assertUnprocessable()->assertJsonValidationErrors('keep');
    $this->postJson(route('budget.bank.filters.suggest'), ['keep' => [$mine->id], 'mode' => 'everything'])->assertUnprocessable()->assertJsonValidationErrors('mode');
    $this->postJson(route('budget.bank.filters.suggest'), ['keep' => [$mine->id], 'mode' => 'words'])->assertOk();
    $this->postJson(route('budget.bank.filters.suggest'), ['keep' => range(1, 51), 'mode' => 'words'])->assertUnprocessable()->assertJsonValidationErrors('keep');

    expect($reader->filters())->toBe(1);
});

test('the route is closed to a member, and 404 while the feed is off', function (): void {
    [$family] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL']));
    suggestBankFilterFeedOn($family);
    $mine = suggestBankFilterTransaction($family, 'Addebito Enel Energia');
    $body = ['keep' => [$mine->id], 'mode' => 'words'];

    $this->actingAs(User::factory()->for($family)->create(['role' => Role::Member]))
        ->postJson(route('budget.bank.filters.suggest'), $body)->assertForbidden();

    $this->actingAs(User::factory()->for($family)->create(['role' => Role::Admin]));
    $family->forceFill(['bank_feed_enabled' => false])->save();
    $this->postJson(route('budget.bank.filters.suggest'), $body)->assertNotFound();
});

test('a reading that is off, used up or without an answer is a 422 under keep, never a 500', function (): void {
    [$family, $reader] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL']));
    suggestBankFilterFeedOn($family);
    $mine = suggestBankFilterTransaction($family, 'Addebito Enel Energia');
    $body = ['keep' => [$mine->id], 'mode' => 'words'];

    $reader->off();
    $this->postJson(route('budget.bank.filters.suggest'), $body)
        ->assertUnprocessable()
        ->assertJsonPath('errors.keep.0', 'Automatic reading is not on.');

    app()->instance(DocumentReader::class, (new FakeDocumentReader)->suggestsFilter(suggestBankFilterAnswer(['ENEL']))->fails(ReadingFailed::busy(new RuntimeException('busy'))));
    $this->postJson(route('budget.bank.filters.suggest'), $body)
        ->assertUnprocessable()
        ->assertJsonPath('errors.keep.0', 'The AI service is busy, try again in a while.');
});

test('the route has its own throttle counter', function (): void {
    [$family] = suggestBankFilterSetup(suggestBankFilterAnswer(['ENEL']));
    suggestBankFilterFeedOn($family);
    $mine = suggestBankFilterTransaction($family, 'Addebito Enel Energia');
    $body = ['keep' => [$mine->id], 'mode' => 'words'];

    foreach (range(1, 20) as $attempt) {
        $this->postJson(route('budget.bank.filters.suggest'), $body)->assertOk();
    }

    $this->postJson(route('budget.bank.filters.suggest'), $body)->assertStatus(429);
    $this->postJson(route('budget.bank.filters.preview'), [])->assertOk();
});
