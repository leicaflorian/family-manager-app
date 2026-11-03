<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Ai\Agents\DocumentReaderAgent;
use App\Contracts\DocumentReader;
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

/**
 * Reads a document with the service and the model of `config/document_reader.php`, through the Laravel AI SDK.
 */
final class AiDocumentReader implements DocumentReader
{
    /** The statuses with which a service refuses the key. */
    private const array KEY_REFUSED = [401, 403];

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
     *
     * @param  ReadingSource  $source  the file as the model reads it
     * @param  ReadingContext  $context  the lists the model picks from and the notes of the file
     * @param  string  $locale  the language of who uploaded the file (`it` or `en`)
     *
     * @throws ReadingFailed when the service gives no extraction: `retry` only when it is busy or cannot be reached
     */
    public function read(ReadingSource $source, ReadingContext $context, string $locale): Reading
    {
        $provider = Config::string('document_reader.provider');
        $model = Config::string('document_reader.model');
        $instructions = ReadingPrompt::instructions($context, $locale);
        $text = $source->text();

        $response = $this->ask(
            new DocumentReaderAgent($model),
            $text === null ? $instructions : "{$text}\n\n{$instructions}",
            $text === null ? [$source->attachment()] : [],
            $provider,
            $model,
        );

        $finish = $response->steps->last();

        match ($finish instanceof Step ? $finish->finishReason : null) {
            FinishReason::ContentFilter => throw ReadingFailed::refused(),
            FinishReason::Length => throw ReadingFailed::cutShort(),
            default => null,
        };

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
     * Sends the request, with the errors of the service as failures of the reading.
     *
     * @param  DocumentReaderAgent  $agent  the model and its options
     * @param  string  $message  the text of the message
     * @param  list<Base64Document|Base64Image>  $attachments  the file, unless it is a text
     * @param  string  $provider  the service
     * @param  string  $model  the model
     *
     * @throws ReadingFailed when the service does not answer
     */
    private function ask(DocumentReaderAgent $agent, string $message, array $attachments, string $provider, string $model): AgentResponse
    {
        try {
            return $agent->prompt($message, attachments: $attachments, provider: $provider, model: $model);
        } catch (RateLimitedException|ProviderOverloadedException $e) {
            throw ReadingFailed::busy($e);
        } catch (ProviderConnectionException $e) {
            throw ReadingFailed::unreachable($e);
        } catch (RequestException $e) {
            throw in_array($e->response->status(), self::KEY_REFUSED, true) ? ReadingFailed::invalidKey($e) : ReadingFailed::serviceError($e);
        } catch (AiException $e) {
            throw ReadingFailed::serviceError($e);
        }
    }
}
