<?php

declare(strict_types=1);

/*
| Reading uploaded documents with an AI service. The services themselves
| (keys, addresses) are in config/ai.php.
*/

$flash = [
    ['input' => 0.75, 'output' => 3.75],
    ['input' => 1.5, 'output' => 7.5, 'from' => '2027-01-01'],
];

return [

    // gemini, anthropic, ollama; empty = the reading is off
    'provider' => env('DOCUMENT_READER_PROVIDER'),

    'model' => env('DOCUMENT_READER_MODEL', 'gemini-3.8-flash'),

    // readings per month for the whole app
    'monthly_limit' => (int) env('DOCUMENT_READER_MONTHLY_LIMIT', 1000),

    // largest file sent to the service
    'max_bytes' => 20 * 1024 * 1024,

    // largest text of a file sent to the service
    'max_text_bytes' => 100 * 1024,

    // models tried in this order when the service refuses the first one
    'fallback_models' => [
        'claude-fable-5-1',
        'claude-opus-5-5',
        'claude-opus-5',
        'claude-sonnet-5-5',
    ],

    /*
    | Dollars per million tokens, from the official price lists (end of
    | September 2026). `from`: the price applies to readings made on or after
    | that day; the last matching entry wins.
    */
    'prices' => [
        'claude-haiku-4-5' => [['input' => 1, 'output' => 5]],
        'claude-sonnet-5-5' => [['input' => 2, 'output' => 10]],
        'claude-opus-5-5' => [['input' => 4, 'output' => 20]],
        'claude-opus-5' => [['input' => 5, 'output' => 25]],
        'claude-fable-5-1' => [['input' => 10, 'output' => 50]],
        'gemini-3.1-flash-lite' => [['input' => 0.25, 'output' => 1.5]],
        'gemini-3.5-flash-lite' => [['input' => 0.3, 'output' => 2.5]],
        'gemini-3.5-flash' => [['input' => 1.5, 'output' => 9]],
        'gemini-3.6-flash' => $flash,
        'gemini-3.7-flash' => $flash,
        'gemini-3.8-flash' => $flash,
        'gemini-3.1-pro-preview' => [['input' => 2, 'output' => 12]],
    ],

];
