<?php

declare(strict_types=1);

namespace App\Support\Banks;

use Illuminate\Support\Str;

/**
 * The rule *How it appears at the bank* of a planned expense or income: a regular expression, written without
 * delimiters, that is looked for in the text of a bank transaction. Both sides are normalised first (upper case, no
 * accents, single spaces), and a rule that could match everything or run forever is refused before it is saved.
 */
final class BankMatch
{
    /** The longest rule that is saved. */
    public const int MAX_LENGTH = 200;

    /** The backtracking the engine may spend on one text, so that a bad rule gives up instead of hanging. */
    private const string BACKTRACK_LIMIT = '100000';

    /** The words that a signature keeps are at least this long. */
    private const int MIN_WORD = 3;

    /** The most stress texts `valid()` tries. */
    private const int PROBES = 20;

    /** Unrelated texts that no useful rule matches all of. */
    private const array SAMPLES = ['X', '12345', 'PAGAMENTO POS ESSELUNGA'];

    /** How many words `fromText()` keeps. */
    private const int WORDS = 4;

    /**
     * Upper case, without accents, with repeated spaces reduced to one.
     *
     * @param  string  $text  the text of a transaction or a rule
     */
    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', Str::upper(Str::ascii($text))));
    }

    /**
     * Whether a rule can be saved: not empty, at most 200 characters, it compiles, it does not match the empty text
     * (so it cannot match every transaction) and it does not fail on a long text built to make it backtrack.
     *
     * @param  string  $pattern  the rule, without delimiters
     */
    public static function valid(string $pattern): bool
    {
        if ($pattern === '' || mb_strlen($pattern) > self::MAX_LENGTH) {
            return false;
        }

        // A rule that finds itself in the empty text or in unrelated samples would settle every transaction
        if (self::run($pattern, '') !== false) {
            return false;
        }

        $found = array_filter(self::SAMPLES, static fn (string $sample): bool => self::run($pattern, $sample) === true);

        if (count($found) === count(self::SAMPLES)) {
            return false;
        }

        // The engine must not give up on a long text of the characters the rule is made of
        foreach (self::stressTexts($pattern) as $stress) {
            if (self::run($pattern, $stress, true) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * The long texts that try to make the rule backtrack: one for `A`, `0` and a space, and one for each distinct
     * letter or digit the rule contains (at most twenty in all), each followed by `!`.
     *
     * @param  string  $pattern  the rule, without delimiters
     * @return list<string>
     */
    private static function stressTexts(string $pattern): array
    {
        preg_match_all('/[A-Za-z0-9]/', Str::upper($pattern), $letters);
        $chars = array_slice(array_values(array_unique(['A', '0', ' ', ...$letters[0]])), 0, self::PROBES);

        return array_map(static fn (string $char): string => str_repeat($char, 500).'!', $chars);
    }

    /**
     * Whether the rule is found in the text.
     *
     * @param  string  $pattern  the rule, without delimiters
     * @param  string  $text  the text of the transaction, as the bank gave it
     */
    public static function matches(string $pattern, string $text): bool
    {
        return self::run($pattern, self::normalize($text)) === true;
    }

    /**
     * The text without numbers and without the words shorter than three letters: what stays the same from one
     * payment of the same thing to the next.
     *
     * @param  string  $text  the text of a transaction
     */
    public static function signature(string $text): string
    {
        $words = array_filter(
            explode(' ', (string) preg_replace('/[^A-Z ]+/', ' ', self::normalize($text))),
            static fn (string $word): bool => strlen($word) >= self::MIN_WORD,
        );

        return implode(' ', $words);
    }

    /**
     * A rule written from a transaction: the first four words of its signature, escaped and joined by `.*`.
     *
     * @param  string  $text  the text of a transaction
     */
    public static function fromText(string $text): string
    {
        $words = array_slice(explode(' ', self::signature($text)), 0, self::WORDS);

        return implode('.*', array_map(static fn (string $word): string => preg_quote($word, '~'), array_filter($words)));
    }

    /**
     * Runs the rule on a text with a low backtracking limit.
     *
     * @param  string  $pattern  the rule, without delimiters
     * @param  string  $text  the text, already normalised
     * @param  bool  $stress  turn off the engine's shortcuts (a required character that is missing from the text ends the search at once), so a rule that backtracks really does
     * @return bool|null whether it was found, null when the rule does not compile or the engine gave up
     */
    private static function run(string $pattern, string $text, bool $stress = false): ?bool
    {
        // A tilde is the delimiter: escape the ones the rule does not already escape
        // Accents are dropped from the rule as from the text; no upper case, it would turn \d into \D
        // Accents are dropped from the rule as from the text; no upper case, it would turn \d into \D
        $escaped = (string) preg_replace_callback('/\\\\.|~/su', static fn (array $found): string => $found[0] === '~' ? '\~' : $found[0], Str::ascii($pattern));

        $backtrack = ini_set('pcre.backtrack_limit', self::BACKTRACK_LIMIT);
        $jit = ini_set('pcre.jit', '0');

        try {
            $found = @preg_match('~'.($stress ? '(*NO_START_OPT)' : '').$escaped.'~iu', $text);

            return $found === false || preg_last_error() !== PREG_NO_ERROR ? null : $found === 1;
        } finally {
            ini_set('pcre.backtrack_limit', $backtrack === false ? '1000000' : $backtrack);
            ini_set('pcre.jit', $jit === false ? '1' : $jit);
        }
    }
}
