<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\HasReaderOptions;
use App\Services\Reading\ReadingPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * The model that reads a document: the system text and the schema of the answer of `ReadingPrompt`, and for
 * Anthropic a low effort and the fallback of the server. The request gives up after 60 seconds.
 */
#[Timeout(60)]
final class DocumentReaderAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use HasReaderOptions;
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
}
