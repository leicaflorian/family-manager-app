<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Services\Reading\ReadingPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * The model that reads a document: the system text and the schema of the answer of `ReadingPrompt`, and for
 * Anthropic a low effort and the fallback of the server. The request gives up after 60 seconds.
 */
#[Timeout(60)]
final class DocumentReaderAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  string  $model  the model asked to read, whose options depend on it
     */
    public function __construct(private readonly string $model) {}

    /**
     * What the model is for and the rules of every field.
     */
    public function instructions(): string
    {
        return ReadingPrompt::SYSTEM;
    }

    /**
     * The schema of the answer.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ReadingPrompt::schema($schema);
    }

    /**
     * The options of the service. Anthropic gets a low effort (Haiku has none) and, on the models that accept it,
     * the fallback of the server to another model when the first one refuses; Gemini and Ollama get nothing.
     *
     * @param  Lab|string  $provider  the service
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        if (($provider instanceof Lab ? $provider->value : $provider) !== 'anthropic') {
            return [];
        }

        $options = str_starts_with($this->model, 'claude-haiku-') ? [] : ['output_config' => ['effort' => 'low']];

        return in_array($this->model, Config::array('document_reader.fallback_models'), true)
            ? [...$options, 'fallbacks' => 'default']
            : $options;
    }
}
