<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | The app only reads documents (see config/document_reader.php), which
    | always names its provider, so these defaults are never relied on.
    |
    */

    'default' => 'gemini',
    'default_for_images' => 'gemini',
    'default_for_audio' => 'gemini',
    'default_for_transcription' => 'gemini',
    'default_for_embeddings' => 'gemini',
    'default_for_reranking' => 'gemini',
    'default_for_classification' => 'gemini',

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
            'individually' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Only the three services the document reader can use: Gemini, Anthropic
    | and Ollama (a model on the owner's own machine).
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'anthropic_beta' => env('ANTHROPIC_BETA', 'server-side-fallback-2026-07-01'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],
    ],

];
