<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Contracts\DocumentReader;
use App\Data\Banks\BankRuleSuggestion;
use App\Exceptions\Reading\ReadingFailed;
use App\Services\Reading\ReadingAvailability;
use App\Support\Banks\BankMatch;
use App\Support\Banks\BankTextMask;
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
     * The rule for the examples. Only their normalised texts go to the model, with IBANs, amounts and the names of the
     * family masked (`BankTextMask`); the rule is checked on the texts as the bank gave them.
     *
     * @param  list<string>  $examples  the texts of the transactions, as the bank gave them
     * @param  string  $locale  the language of the explanation (`it` or `en`)
     *
     * @throws ValidationException when the reading is off or the month is used up, or the first example has no words to make a rule of
     * @throws ReadingFailed when the service gives no answer at all (an answer that comes but is unusable gives the words of the first example)
     */
    public function handle(array $examples, string $locale): BankRuleSuggestion
    {
        $this->availability->ensure($locale, 'examples');

        $texts = array_values(array_filter(array_map(BankMatch::normalize(...), $examples), static fn (string $text): bool => $text !== ''));
        $fallback = $this->fallback($texts[0] ?? '', $locale);

        // no words to recognise the payment by: nothing is asked, so nothing is paid
        if (! $fallback instanceof BankRuleSuggestion) {
            throw ValidationException::withMessages(['examples' => __('These texts have no words to recognize the payment by. Write the rule by hand.', [], $locale)]);
        }

        try {
            $people = BankTextMask::familyNames();
            $suggestion = $this->reader->bankRule(array_map(static fn (string $text): string => BankTextMask::forModel($text, $people), $texts), $locale);
        } catch (ReadingFailed $failed) {
            // the answer came (and was paid for) but cannot be used: a new call would pay twice
            return $failed->paid ? $fallback : throw $failed;
        }

        return $this->recognisesAll($suggestion->pattern, $texts) ? $suggestion : $fallback;
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
     * The rule made of the words of a text, with a sentence that lists them; null when it would not be a safe rule
     * that finds the text.
     *
     * @param  string  $text  the first example
     * @param  string  $locale  the language of the sentence
     */
    private function fallback(string $text, string $locale): ?BankRuleSuggestion
    {
        $pattern = BankMatch::fromText($text);

        if (! $this->recognisesAll($pattern, [$text])) {
            return null;
        }

        $words = stripslashes(str_replace('.*', ' ', $pattern));

        return new BankRuleSuggestion($pattern, __('Recognizes the transactions with these words: :words', ['words' => $words], $locale));
    }
}
