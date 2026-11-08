<?php

declare(strict_types=1);

namespace App\Ai\Agents\Concerns;

use Illuminate\Support\Facades\Config;
use Laravel\Ai\Enums\Lab;

/**
 * The options of the service that every agent of the document reader sends: for Anthropic a low effort and the
 * fallback of the server, nothing for the others. The class using it has a `$model` property.
 */
trait HasReaderOptions
{
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
