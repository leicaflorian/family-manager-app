<?php

declare(strict_types=1);

use App\Actions\Banks\WriteBankMatch;
use App\Contracts\DocumentReader;
use App\Data\Banks\BankRuleSuggestion;
use App\Enums\ReadingPurpose;
use App\Exceptions\Reading\ReadingFailed;
use App\Models\AiReading;
use App\Models\Family;
use App\Models\Person;
use App\Models\User;
use App\Services\Reading\ReadingAllowance;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeDocumentReader;

/**
 * Binds a fake reader that suggests a rule, and signs in an account of a new family.
 *
 * @param  string  $pattern  the rule the model writes
 * @return array{0: Family, 1: FakeDocumentReader}
 */
function bankWriteSetup(string $pattern): array
{
    $family = Family::factory()->create();
    test()->actingAs(User::factory()->for($family)->create());
    $reader = (new FakeDocumentReader)->suggestsRule(new BankRuleSuggestion($pattern, 'Enel bills.'));
    app()->instance(DocumentReader::class, $reader);

    return [$family, $reader];
}

/**
 * Writes the rule of some examples in a family.
 *
 * @param  list<string>  $examples  the texts of the transactions
 */
function bankWriteRun(Family $family, array $examples): BankRuleSuggestion
{
    return CurrentFamily::using($family->id, fn (): BankRuleSuggestion => resolve(WriteBankMatch::class)->handle($examples, 'it'));
}

test('the rule of the model is returned when it matches every example', function (): void {
    [$family] = bankWriteSetup('ADDEBITO.*ENEL');

    $suggestion = bankWriteRun($family, ['Addebito Enel Energia fattura 123', 'ADDEBITO ENEL ENERGIA fattura 456']);

    expect($suggestion->pattern)->toBe('ADDEBITO.*ENEL')->and($suggestion->explanation)->toBe('Enel bills.');
});

test('a rule that misses an example falls back to the words of the first one', function (): void {
    [$family] = bankWriteSetup('GAS');

    $suggestion = bankWriteRun($family, ['Addebito Enel Energia fattura 123', 'Pagamento Hera luce']);

    expect($suggestion->pattern)->toBe('ADDEBITO.*ENEL.*ENERGIA.*FATTURA')
        ->and($suggestion->explanation)->toBe('Riconosce i movimenti con queste parole: ADDEBITO ENEL ENERGIA FATTURA');
});

test('a broken rule falls back too', function (): void {
    [$family] = bankWriteSetup('(ENEL');

    $suggestion = bankWriteRun($family, ['Addebito Enel Energia']);

    expect($suggestion->pattern)->toBe('ADDEBITO.*ENEL.*ENERGIA')
        ->and($suggestion->explanation)->toBe('Riconosce i movimenti con queste parole: ADDEBITO ENEL ENERGIA');
});

test('a rule that matches everything falls back too', function (): void {
    [$family] = bankWriteSetup('.*');

    expect(bankWriteRun($family, ['Addebito Enel Energia'])->pattern)->toBe('ADDEBITO.*ENEL.*ENERGIA');
});

test('with the reading off the action refuses with the message of the reading', function (): void {
    $family = Family::factory()->create();
    app()->instance(DocumentReader::class, (new FakeDocumentReader)->off());

    $refused = null;

    try {
        bankWriteRun($family, ['Addebito Enel']);
    } catch (ValidationException $e) {
        $refused = $e->validator->errors()->first('examples');
    }

    expect($refused)->toBe('La lettura automatica non è attiva.');
});

test('the call is recorded as bank_rule with the account and counts in the month', function (): void {
    [$family, $reader] = bankWriteSetup('ENEL');
    $user = auth()->user();

    bankWriteRun($family, ['Addebito Enel Energia']);

    $row = AiReading::query()->withoutGlobalScopes()->sole();
    expect($reader->rules())->toBe(1)
        ->and($row->purpose)->toBe(ReadingPurpose::BankRule)
        ->and($row->user_id)->toBe($user->id)
        ->and($row->family_id)->toBe($family->id)
        ->and($row->file_id)->toBeNull();

    config(['document_reader.monthly_limit' => 1]);
    expect(CurrentFamily::using($family->id, fn (): int => resolve(ReadingAllowance::class)->left(CarbonImmutable::now('Europe/Rome'))))->toBe(0);
});

test('nothing but the example texts is sent', function (): void {
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(json_decode((string) file_get_contents(base_path('tests/Fixtures/ai/gemini-bank-rule.json')), true, flags: JSON_THROW_ON_ERROR))]);
    config([
        'document_reader.provider' => 'gemini',
        'document_reader.model' => 'gemini-3.8-flash',
        'ai.providers.gemini.key' => 'gemini-test-key',
    ]);
    $family = Family::factory()->create(['name' => 'Famiglia Zanzibar']);
    $user = User::factory()->for($family)->create(['name' => 'Giuseppina Rossetti']);
    CurrentFamily::using($family->id, fn () => Person::factory()->create(['first_name' => 'Ottavio', 'last_name' => 'Bellandi']));
    $this->actingAs($user);

    $suggestion = bankWriteRun($family, ['Addebito Enel Energia fattura 123', 'ADDEBITO  Enel Energia fattura 456']);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $body = $request->body();

        return str_contains($body, 'ADDEBITO ENEL ENERGIA FATTURA 123')
            && str_contains($body, 'ADDEBITO ENEL ENERGIA FATTURA 456')
            && ! str_contains($body, 'Zanzibar')
            && ! str_contains($body, 'Rossetti')
            && ! str_contains($body, 'Giuseppina')
            && ! str_contains($body, 'Bellandi');
    });
    expect($suggestion->pattern)->toBe('ADDEBITO.*ENEL ENERGIA')->and($suggestion->explanation)->toBe('Riconosce gli addebiti di Enel Energia.');
});

test('a rule that matches the first example but not the second falls back too', function (): void {
    [$family] = bankWriteSetup('ENEL');

    $suggestion = bankWriteRun($family, ['Addebito Enel', 'Pagamento Hera']);

    expect($suggestion->pattern)->toBe('ADDEBITO.*ENEL');
});

test('examples without usable words are refused and nothing is asked', function (array $examples): void {
    [$family, $reader] = bankWriteSetup('ENEL');
    $message = null;

    try {
        bankWriteRun($family, $examples);
    } catch (ValidationException $e) {
        $message = $e->validator->errors()->first('examples');
    }

    expect($message)->toBe('In questi testi non ci sono parole per riconoscere il pagamento. Scrivi la regola a mano.')
        ->and($reader->rules())->toBe(0)
        ->and(AiReading::query()->withoutGlobalScopes()->count())->toBe(0);
})->with([
    'numbers only' => [['12345 9876']],
    'short words only' => [['DA 12 AL']],
    'nothing' => [[]],
    'blank texts' => [['   ', '']],
]);

test('a first example without words refuses even when the model wrote a rule', function (): void {
    [$family] = bankWriteSetup('ENEL');

    expect(fn (): BankRuleSuggestion => bankWriteRun($family, ['12345 9876', 'Addebito Enel']))->toThrow(ValidationException::class);
});

test('an answer that was paid but is unusable falls back to the first example', function (string $factory): void {
    [$family, $reader] = bankWriteSetup('ENEL');
    $reader->fails(ReadingFailed::{$factory}());

    $suggestion = bankWriteRun($family, ['Addebito Enel Energia']);

    expect($suggestion->pattern)->toBe('ADDEBITO.*ENEL.*ENERGIA')
        ->and(AiReading::query()->withoutGlobalScopes()->sole()->purpose)->toBe(ReadingPurpose::BankRule);
})->with([
    'not valid' => 'invalidAnswer',
    'cut short' => 'cutShort',
]);

test('a failure with no answer is thrown and costs nothing', function (): void {
    [$family, $reader] = bankWriteSetup('ENEL');
    $reader->fails(ReadingFailed::unreachable(new RuntimeException('down')));

    expect(fn (): BankRuleSuggestion => bankWriteRun($family, ['Addebito Enel']))->toThrow(ReadingFailed::class)
        ->and(AiReading::query()->withoutGlobalScopes()->count())->toBe(0);
});
