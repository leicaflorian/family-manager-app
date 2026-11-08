<?php

declare(strict_types=1);

use App\Ai\Agents\MailScreeningAgent;
use App\Contracts\DocumentReader;
use App\Data\Mail\MailScreeningAnswer;
use App\Data\Reading\Reading;
use App\Enums\ReadingPurpose;
use App\Exceptions\Reading\ReadingFailed;
use App\Models\AiReading;
use App\Models\Family;
use App\Models\File;
use App\Services\Reading\ReadingContext;
use App\Services\Reading\ReadingPrompt;
use App\Services\Reading\ReadingSource;
use App\Support\CurrentFamily;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->pdf = (string) file_get_contents(base_path('tests/Fixtures/one-page.pdf'));
    $this->context = new ReadingContext(
        expenseCategories: [],
        documentCategories: [],
        organizations: [['id' => 7, 'label' => 'Enel Energia']],
        people: [],
        fileName: 'bolletta.pdf',
        fileNote: null,
    );
});

/**
 * The file as the reader gets it: some bytes stored as a file of a family.
 */
function aiReaderSource(string $bytes, string $mime): ReadingSource
{
    $file = File::factory()->create(['mime_type' => $mime, 'size' => strlen($bytes)]);
    Storage::disk('local')->put($file->path, $bytes);

    return ReadingSource::of($file);
}

/**
 * A saved answer of an AI service, from `tests/Fixtures/ai`.
 *
 * @return array<string, mixed>
 */
function aiAnswer(string $name): array
{
    return json_decode((string) file_get_contents(base_path("tests/Fixtures/ai/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * The reader set up for a service and a model, with a key.
 */
function aiReader(string $provider, string $model): DocumentReader
{
    config([
        'document_reader.provider' => $provider,
        'document_reader.model' => $model,
        'ai.providers.gemini.key' => 'gemini-test-key',
        'ai.providers.anthropic.key' => 'anthropic-test-key',
    ]);

    return resolve(DocumentReader::class);
}

/**
 * The failure of a reading, or null when it worked.
 *
 * @param  callable(): mixed  $read
 */
function aiFailure(callable $read): ?ReadingFailed
{
    try {
        $read();
    } catch (ReadingFailed $failed) {
        return $failed;
    }

    return null;
}

test('gemini reads a pdf', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(aiAnswer('gemini-bill'))]);

    $reading = aiReader('gemini', 'gemini-3.8-flash')->read(aiReaderSource($this->pdf, 'application/pdf'), $this->context, 'it');

    Http::assertSent(function (Request $request): bool {
        $content = $request['input'][0]['content'];

        return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
            && $request->hasHeader('x-goog-api-key', 'gemini-test-key')
            && $request['model'] === 'gemini-3.8-flash'
            && $request['system_instruction'] === ReadingPrompt::SYSTEM
            && $request['response_format']['mime_type'] === 'application/json'
            && in_array('kind', $request['response_format']['schema']['required'], true)
            && $request['response_format']['schema']['properties']['kind']['enum'][0] === 'utility_bill'
            && $content[0] === ['type' => 'text', 'text' => ReadingPrompt::instructions($this->context, 'it')]
            && $content[1] === ['type' => 'document', 'mime_type' => 'application/pdf', 'data' => base64_encode($this->pdf)];
    });
    expect($reading->extraction->kind)->toBe('utility_bill')
        ->and($reading->extraction->total_amount)->toBe(84.2)
        ->and($reading->extraction->organization_id)->toBe(7)
        ->and($reading->provider)->toBe('gemini')
        ->and($reading->model)->toBe('gemini-3.8-flash')
        ->and($reading->inputTokens)->toBe(1830)
        // the 620 tokens of the reasoning are paid as output
        ->and($reading->outputTokens)->toBe(412 + 620)
        ->and($reading->cacheReadTokens)->toBe(0);
});

test('anthropic asks low effort and the fallback', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(aiAnswer('anthropic-bill'))]);
    $source = aiReaderSource($this->pdf, 'application/pdf');

    $reading = aiReader('anthropic', 'claude-opus-5-5')->read($source, $this->context, 'it');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.anthropic.com/v1/messages'
        && $request->hasHeader('x-api-key', 'anthropic-test-key')
        && $request->hasHeader('anthropic-beta', 'server-side-fallback-2026-07-01')
        && $request['model'] === 'claude-opus-5-5'
        && $request['system'] === ReadingPrompt::SYSTEM
        && $request['output_config']['effort'] === 'low'
        && $request['output_config']['format']['type'] === 'json_schema'
        && $request['fallbacks'] === 'default'
        && $request['messages'][0]['content'][0]['source'] === ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode($this->pdf)]);
    // the fallback of the server answered: the model is the one that read
    expect($reading->model)->toBe('claude-opus-5')
        ->and($reading->provider)->toBe('anthropic')
        ->and($reading->extraction->title)->toBe('Bolletta gas')
        ->and($reading->inputTokens)->toBe(1500 + 200)
        ->and($reading->outputTokens)->toBe(380)
        ->and($reading->cacheReadTokens)->toBe(200);

    aiReader('anthropic', 'claude-haiku-4-5')->read($source, $this->context, 'it');

    // Haiku has no effort and is not a model of the fallback
    Http::assertSent(fn (Request $request): bool => $request['model'] === 'claude-haiku-4-5'
        && ! isset($request['output_config']['effort'])
        && $request['output_config']['format']['type'] === 'json_schema'
        && ! isset($request['fallbacks']));
});

test('ollama reads a photo', function (): void {
    config(['ai.providers.ollama.url' => 'http://192.168.1.20:11434']);
    Http::fake(['192.168.1.20:11434/*' => Http::response(aiAnswer('ollama-bill'))]);
    $photo = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($photo);
    $source = aiReaderSource((string) ob_get_clean(), 'image/jpeg');

    $reading = aiReader('ollama', 'qwen3-vl:8b')->read($source, $this->context, 'en');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://192.168.1.20:11434/api/chat'
        && $request['model'] === 'qwen3-vl:8b'
        && str_starts_with($request['messages'][0]['content'], ReadingPrompt::SYSTEM)
        && $request['messages'][1]['content'] === ReadingPrompt::instructions($this->context, 'en')
        && $request['messages'][1]['images'] === [$source->attachment()->base64]
        && $request['format']['type'] === 'object'
        && in_array('kind', $request['format']['required'], true));
    expect($reading->extraction->kind)->toBe('utility_bill')
        ->and($reading->provider)->toBe('ollama')
        ->and($reading->model)->toBe('qwen3-vl:8b')
        ->and($reading->inputTokens)->toBe(950)
        ->and($reading->outputTokens)->toBe(260);
});

test('a refusal is not retried', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(aiAnswer('anthropic-refusal'))]);

    $failed = aiFailure(fn (): Reading => aiReader('anthropic', 'claude-opus-5-5')->read(aiReaderSource($this->pdf, 'application/pdf'), $this->context, 'it'));

    expect($failed?->getMessage())->toBe('The model refused to read the document.')
        ->and($failed?->retry)->toBeFalse();
    Http::assertSentCount(1);
});

test('a cut answer is not retried', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(aiAnswer('gemini-truncated'))]);

    $failed = aiFailure(fn (): Reading => aiReader('gemini', 'gemini-3.8-flash')->read(aiReaderSource($this->pdf, 'application/pdf'), $this->context, 'it'));

    expect($failed?->getMessage())->toBe('The answer of the model was cut short.')
        ->and($failed?->retry)->toBeFalse();
    Http::assertSentCount(1);
});

test('a busy service is retried', function (string $provider, string $model, ?int $status, string $message): void {
    // no status: the service cannot be reached at all
    Http::fake(['*' => $status === null
        ? Http::failedConnection()
        : Http::response(['type' => 'error', 'error' => ['code' => $status, 'type' => 'overloaded_error', 'message' => 'Overloaded']], $status)]);

    $failed = aiFailure(fn (): Reading => aiReader($provider, $model)->read(aiReaderSource($this->pdf, 'application/pdf'), $this->context, 'it'));

    expect($failed?->getMessage())->toBe($message)
        ->and($failed?->retry)->toBeTrue();
})->with([
    'too many requests' => ['gemini', 'gemini-3.8-flash', 429, 'The AI service is busy, try again in a while.'],
    'overloaded' => ['anthropic', 'claude-opus-5-5', 529, 'The AI service is busy, try again in a while.'],
    'no connection' => ['gemini', 'gemini-3.8-flash', null, 'The AI service cannot be reached, try again in a while.'],
]);

test('a wrong key is not retried', function (int $status): void {
    Http::fake(['*' => Http::response(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']], $status)]);

    $failed = aiFailure(fn (): Reading => aiReader('anthropic', 'claude-opus-5-5')->read(aiReaderSource($this->pdf, 'application/pdf'), $this->context, 'it'));

    expect($failed?->getMessage())->toBe('The key of the AI service is not valid.')
        ->and($failed?->retry)->toBeFalse();
})->with([401, 403]);

test('a wrong gemini key is not retried', function (): void {
    // Google answers a wrong key with 400 and the reason in the details, not with 401
    Http::fake(['*' => Http::response(aiAnswer('gemini-invalid-key'), 400)]);

    $failed = aiFailure(fn (): Reading => aiReader('gemini', 'gemini-3.8-flash')->read(aiReaderSource($this->pdf, 'application/pdf'), $this->context, 'it'));

    expect($failed?->getMessage())->toBe('The key of the AI service is not valid.')
        ->and($failed?->retry)->toBeFalse();
});

test('another error of the service is not retried', function (int $status): void {
    Http::fake(['*' => Http::response(['error' => ['code' => $status, 'message' => 'Something went wrong.', 'status' => 'INVALID_ARGUMENT']], $status)]);

    $failed = aiFailure(fn (): Reading => aiReader('gemini', 'gemini-3.8-flash')->read(aiReaderSource($this->pdf, 'application/pdf'), $this->context, 'it'));

    expect($failed?->getMessage())->toBe('The AI service answered with an error.')
        ->and($failed?->retry)->toBeFalse();
})->with([400, 500]);

test('ollama does not get a pdf', function (): void {
    config(['ai.providers.ollama.url' => 'http://192.168.1.20:11434']);
    Http::fake();

    $failed = aiFailure(fn (): Reading => aiReader('ollama', 'qwen3-vl:8b')->read(aiReaderSource($this->pdf, 'application/pdf'), $this->context, 'it'));

    expect($failed?->getMessage())->toBe('This AI service cannot read PDF files.')
        ->and($failed?->retry)->toBeFalse();
    Http::assertNothingSent();
});

test('the reader reads only when it is on', function (): void {
    Http::fake();
    config(['document_reader.provider' => null]);

    expect(fn (): Reading => resolve(DocumentReader::class)->read(aiReaderSource($this->pdf, 'application/pdf'), $this->context, 'it'))
        ->toThrow(LogicException::class, 'The reading is off: call read() only when isActive() is true.');
    Http::assertNothingSent();
});

test('without a key the reader is off', function (array $settings, bool $active): void {
    config([
        'document_reader.provider' => null,
        'ai.providers.gemini.key' => null,
        'ai.providers.anthropic.key' => null,
        'ai.providers.ollama.url' => null,
        ...$settings,
    ]);

    expect(resolve(DocumentReader::class)->isActive())->toBe($active);
})->with([
    'no service' => [['ai.providers.gemini.key' => 'a-key'], false],
    'empty service' => [['document_reader.provider' => '', 'ai.providers.gemini.key' => 'a-key'], false],
    'unknown service' => [['document_reader.provider' => 'openai', 'ai.providers.gemini.key' => 'a-key'], false],
    'gemini without key' => [['document_reader.provider' => 'gemini'], false],
    'gemini with an empty key' => [['document_reader.provider' => 'gemini', 'ai.providers.gemini.key' => ''], false],
    'gemini with a key' => [['document_reader.provider' => 'gemini', 'ai.providers.gemini.key' => 'a-key'], true],
    'anthropic with an empty key' => [['document_reader.provider' => 'anthropic', 'ai.providers.anthropic.key' => ''], false],
    'anthropic with a key' => [['document_reader.provider' => 'anthropic', 'ai.providers.anthropic.key' => 'a-key'], true],
    'ollama with an empty address' => [['document_reader.provider' => 'ollama', 'ai.providers.ollama.url' => ''], false],
    'ollama with an address' => [['document_reader.provider' => 'ollama', 'ai.providers.ollama.url' => 'http://localhost:11434'], true],
]);

test('a text notice is sent as text', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(aiAnswer('gemini-bill'))]);
    $notice = "Gentile cliente,\nla bolletta Enel di ottobre di 84,20 € è disponibile nella tua area clienti.";

    aiReader('gemini', 'gemini-3.8-flash')->read(aiReaderSource($notice, 'text/plain'), $this->context, 'it');

    Http::assertSent(fn (Request $request): bool => $request['input'][0]['content'] === [
        ['type' => 'text', 'text' => $notice."\n\n".ReadingPrompt::instructions($this->context, 'it')],
    ]);
});

test('gemini screens a mail from the sender and the subject and the call is recorded', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(aiAnswer('gemini-screen'))]);
    $family = Family::factory()->create();

    $answer = CurrentFamily::using($family->id, fn (): MailScreeningAnswer => aiReader('gemini', 'gemini-3.8-flash')->screen('Promo <promo@offerte-energia.it>', 'Nuove offerte luce e gas', 'it'));

    Http::assertSent(function (Request $request): bool {
        $content = $request['input'][0]['content'];

        return $request['system_instruction'] === MailScreeningAgent::SYSTEM
            && $request['response_format']['schema']['required'] === ['verdict', 'reason']
            && $content[0] === ['type' => 'text', 'text' => MailScreeningAgent::request('Promo <promo@offerte-energia.it>', 'Nuove offerte luce e gas', 'it')]
            && count($content) === 1;
    });
    $row = AiReading::query()->withoutGlobalScopes()->sole();
    expect($answer->verdict)->toBe('discard')
        ->and($answer->reason)->toBe('offerta commerciale')
        ->and($row->purpose)->toBe(ReadingPurpose::MailFilter)
        ->and($row->family_id)->toBe($family->id)
        ->and($row->file_id)->toBeNull()
        ->and($row->user_id)->toBeNull()
        ->and($row->provider)->toBe('gemini')
        ->and($row->input_tokens)->toBe(210)
        ->and($row->output_tokens)->toBe(20 + 35);
});

test('a screening that the service refuses is a failure', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(aiAnswer('anthropic-refusal'))]);

    $failed = aiFailure(fn (): MailScreeningAnswer => aiReader('anthropic', 'claude-opus-5-5')->screen('a@b.it', 'x', 'it'));

    expect($failed?->getMessage())->toBe('The model refused to read the document.');
    expect(AiReading::query()->withoutGlobalScopes()->count())->toBe(0);
});
