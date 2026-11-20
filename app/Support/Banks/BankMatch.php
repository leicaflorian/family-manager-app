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

        $empty = self::run($pattern, '');
        $stress = self::run($pattern, str_repeat('A', 500).'!');

        return $empty === false && $stress !== null;
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
     * @return bool|null whether it was found, null when the rule does not compile or the engine gave up
     */
    private static function run(string $pattern, string $text): ?bool
    {
        // A tilde is the delimiter: escape the ones the rule does not already escape
        $escaped = (string) preg_replace_callback('/\\\\.|~/su', static fn (array $found): string => $found[0] === '~' ? '\~' : $found[0], $pattern);

        $backtrack = ini_set('pcre.backtrack_limit', self::BACKTRACK_LIMIT);
        $jit = ini_set('pcre.jit', '0');

        try {
            $found = @preg_match('~'.$escaped.'~iu', $text);

            return $found === false || preg_last_error() !== PREG_NO_ERROR ? null : $found === 1;
        } finally {
            ini_set('pcre.backtrack_limit', $backtrack === false ? '1000000' : $backtrack);
            ini_set('pcre.jit', $jit === false ? '1' : $jit);
        }
    }
}
