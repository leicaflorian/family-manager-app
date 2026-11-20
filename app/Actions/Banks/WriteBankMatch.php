<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Contracts\DocumentReader;
use App\Data\Banks\BankRuleSuggestion;
use App\Exceptions\Reading\ReadingFailed;
use App\Services\Reading\ReadingAvailability;
use App\Support\Banks\BankMatch;
use Illuminate\Validation\ValidationException;

/**
 * Writes the rule of a bank payment (*Come compare in banca*) from the texts of some of its transactions: the AI
 * writes it, and when its rule is not safe or misses an example the words of the first example take its place.
 */
final readonly class WriteBankMatch
{
    /**
     * @param  DocumentReader  $reader  the model that writes the rule
     * @param  ReadingAvailability  $availability  says whether a reading can be asked now
     */
    public function __construct(private DocumentReader $reader, private ReadingAvailability $availability) {}

    /**
     * The rule for the examples. Only their normalised texts go to the model: no amount, no name.
     *
     * @param  list<string>  $examples  the texts of the transactions, as the bank gave them
     * @param  string  $locale  the language of the explanation (`it` or `en`)
     *
     * @throws ValidationException when the reading is off or the month is used up
     * @throws ReadingFailed when the service gives no answer
     */
    public function handle(array $examples, string $locale): BankRuleSuggestion
    {
        $unavailable = $this->availability->unavailable($locale);

        if ($unavailable !== null) {
            throw ValidationException::withMessages(['examples' => $unavailable]);
        }

        $texts = array_values(array_filter(array_map(BankMatch::normalize(...), $examples), static fn (string $text): bool => $text !== ''));
        $suggestion = $this->reader->bankRule($texts, $locale);

        if ($this->recognisesAll($suggestion->pattern, $texts)) {
            return $suggestion;
        }

        return $this->fallback($texts[0] ?? '', $locale);
    }

    /**
     * Whether the rule is safe and finds every example.
     *
     * @param  string  $pattern  the rule
     * @param  list<string>  $texts  the normalised examples
     */
    private function recognisesAll(string $pattern, array $texts): bool
    {
        if ($texts === [] || ! BankMatch::valid($pattern)) {
            return false;
        }

        return array_all($texts, static fn (string $text): bool => BankMatch::matches($pattern, $text));
    }

    /**
     * The rule made of the words of a text, with a sentence that lists them.
     *
     * @param  string  $text  the first example
     * @param  string  $locale  the language of the sentence
     */
    private function fallback(string $text, string $locale): BankRuleSuggestion
    {
        $pattern = BankMatch::fromText($text);
        $words = stripslashes(str_replace('.*', ' ', $pattern));

        return new BankRuleSuggestion($pattern, __('Recognizes the transactions with these words: :words', ['words' => $words], $locale));
    }
}
