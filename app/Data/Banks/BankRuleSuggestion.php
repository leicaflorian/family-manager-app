<?php

declare(strict_types=1);

namespace App\Data\Banks;

use Spatie\LaravelData\Data;

/**
 * A rule for recognising a bank transaction (*Come compare in banca*) with a sentence that says what it recognises.
 */
final class BankRuleSuggestion extends Data
{
    /**
     * @param  string  $pattern  the rule, a PCRE pattern without delimiters
     * @param  string  $explanation  what the rule recognises, in a simple sentence
     */
    public function __construct(
        public readonly string $pattern,
        public readonly string $explanation,
    ) {}
}
