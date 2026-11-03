<?php

declare(strict_types=1);

namespace App\Data\Reading;

/**
 * A reading that worked: what the model read, who read it and the tokens it cost.
 */
final readonly class Reading
{
    /**
     * @param  Extraction  $extraction  what the model read from the paper
     * @param  string  $provider  the service: `gemini`, `anthropic` or `ollama`
     * @param  string  $model  the model that answered, also when the service fell back to another one
     * @param  int  $inputTokens  the tokens sent, those read from the cache included
     * @param  int  $outputTokens  the tokens of the answer, those of the reasoning included
     * @param  int  $cacheReadTokens  the tokens sent that the service read from its cache
     */
    public function __construct(
        public Extraction $extraction,
        public string $provider,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $cacheReadTokens,
    ) {}
}
