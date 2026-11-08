<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Actions\Mail\RecordMailFilterCall;
use App\Ai\Agents\DocumentReaderAgent;
use App\Ai\Agents\MailScreeningAgent;
use App\Contracts\DocumentReader;
use App\Data\Mail\MailScreeningAnswer;
use App\Data\Reading\Reading;
use App\Exceptions\Reading\ReadingFailed;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Step;
use Laravel\Ai\Responses\StructuredAgentResponse;
use LogicException;

/**
 * Reads a document with the service and the model of `config/document_reader.php`, through the Laravel AI SDK.
 */
final readonly class AiDocumentReader implements DocumentReader
{
    /**
     * @param  RecordMailFilterCall  $recorder  writes the call of a screening in `ai_readings`
     */
    public function __construct(private RecordMailFilterCall $recorder) {}

    /** The statuses with which a service refuses the key. */
    private const array KEY_REFUSED = [401, 403];

    /** The reason Google gives, with a 400, for a key that is not valid. */
    private const string GOOGLE_KEY_INVALID = 'API_KEY_INVALID';

    /**
     * Whether the reading is on: Gemini and Anthropic need their key, Ollama its address. An empty value of the
     * `.env` counts as missing.
     */
    public function isActive(): bool
    {
        $provider = Config::get('document_reader.provider');

        return match ($provider) {
            'gemini', 'anthropic' => filled(Config::get("ai.providers.{$provider}.key")),
            'ollama' => filled(Config::get('ai.providers.ollama.url')),
            default => false,
        };
    }

    /**
     * Reads a file: a PDF or a photo goes as an attachment, a text inside the message, before the instructions.
     * Called only when `isActive()` is true.
     *
     * @param  ReadingSource  $source  the file as the model reads it
     * @param  ReadingContext  $context  the lists the model picks from and the notes of the file
     * @param  string  $locale  the language of who uploaded the file (`it` or `en`)
     *
     * @throws ReadingFailed when the service gives no extraction: `retry` only when it is busy or cannot be reached
     * @throws LogicException when the reading is off
     */
    public function read(ReadingSource $source, ReadingContext $context, string $locale): Reading
    {
        if (! $this->isActive()) {
            throw new LogicException('The reading is off: call read() only when isActive() is true.');
        }

        $provider = Config::string('document_reader.provider');
        $model = Config::string('document_reader.model');
        $instructions = ReadingPrompt::instructions($context, $locale);
        $text = $source->text();

        // Ollama takes only photos: its pages as photos are for later, and the SDK would throw on a document
        if ($provider === 'ollama' && $source->mediaType === 'application/pdf') {
            throw ReadingFailed::pdfNotSupported();
        }

        $response = $this->ask(
            new DocumentReaderAgent($model),
            $text === null ? $instructions : "{$text}\n\n{$instructions}",
            $text === null ? [$source->attachment()] : [],
            $provider,
            $model,
        );

        $this->assertComplete($response);

        $answer = $response instanceof StructuredAgentResponse && $response->structured !== [] ? $response->structured : $response->text;

        return new Reading(
            extraction: ReadingPrompt::parse($answer, $context),
            provider: $provider,
            // after a fallback of the server, the model that answered is not the one asked
            model: filled($response->meta->model) ? $response->meta->model : $model,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
            cacheReadTokens: $response->usage->cacheReadInputTokens ?? 0,
        );
    }

    /**
     * Asks the model whether a mail is worth reading, from the sender and the subject. The call is recorded right
     * after the answer, before anything else can fail. Called only when `isActive()` is true.
     *
     * @param  string  $sender  the sender, as the mail shows it
     * @param  string  $subject  the subject
     * @param  string  $locale  the language of the reason (`it` or `en`)
     *
     * @throws ReadingFailed when the service does not answer
     * @throws LogicException when the reading is off
     */
    public function screen(string $sender, string $subject, string $locale): MailScreeningAnswer
    {
        if (! $this->isActive()) {
            throw new LogicException('The reading is off: call screen() only when isActive() is true.');
        }

        $provider = Config::string('document_reader.provider');
        $model = Config::string('document_reader.model');

        $response = $this->ask(new MailScreeningAgent($model), MailScreeningAgent::request($sender, $subject, $locale), [], $provider, $model);

        // the call is paid even when refused or cut: it is written first, so it counts against the readings of the month
        $this->recorder->handle(
            $provider,
            filled($response->meta->model) ? $response->meta->model : $model,
            $response->usage->inputTokens,
            $response->usage->outputTokens,
            $response->usage->cacheReadInputTokens ?? 0,
        );

        $this->assertComplete($response);

        return MailScreeningAgent::parse($response instanceof StructuredAgentResponse && $response->structured !== [] ? $response->structured : $response->text);
    }

    /**
     * Sends the request, with the errors of the service as failures of the reading.
     *
     * @param  DocumentReaderAgent|MailScreeningAgent  $agent  the model and its options
     * @param  string  $message  the text of the message
     * @param  list<Base64Document|Base64Image>  $attachments  the file, unless it is a text
     * @param  string  $provider  the service
     * @param  string  $model  the model
     *
     * @throws ReadingFailed when the service does not answer
     */
    private function ask(DocumentReaderAgent|MailScreeningAgent $agent, string $message, array $attachments, string $provider, string $model): AgentResponse
    {
        try {
            return $agent->prompt($message, attachments: $attachments, provider: $provider, model: $model);
        } catch (RateLimitedException|ProviderOverloadedException $e) {
            throw ReadingFailed::busy($e);
        } catch (ProviderConnectionException $e) {
            throw ReadingFailed::unreachable($e);
        } catch (RequestException $e) {
            throw $this->refusesKey($e) ? ReadingFailed::invalidKey($e) : ReadingFailed::serviceError($e);
        } catch (AiException $e) {
            throw ReadingFailed::serviceError($e);
        }
    }

    /**
     * Checks that the model finished its answer.
     *
     * @param  AgentResponse  $response  the answer of the service
     *
     * @throws ReadingFailed when the model refused or the answer was cut short
     */
    private function assertComplete(AgentResponse $response): void
    {
        $finish = $response->steps->last();

        match ($finish instanceof Step ? $finish->finishReason : null) {
            FinishReason::ContentFilter => throw ReadingFailed::refused(),
            FinishReason::Length => throw ReadingFailed::cutShort(),
            default => null,
        };
    }

    /**
     * Whether the service refused the key: a 401 or a 403, or the 400 with which Google answers a wrong key.
     *
     * @param  RequestException  $e  the error answer of the service
     */
    private function refusesKey(RequestException $e): bool
    {
        $status = $e->response->status();
        $details = $e->response->json('error.details');
        $reasons = is_array($details) ? array_column($details, 'reason') : [];

        return in_array($status, self::KEY_REFUSED, true)
            || ($status === 400 && in_array(self::GOOGLE_KEY_INVALID, $reasons, true));
    }
}
