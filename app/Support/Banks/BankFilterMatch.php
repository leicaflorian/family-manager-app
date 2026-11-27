<?php

declare(strict_types=1);

namespace App\Support\Banks;

use App\Models\BankTransaction;

/**
 * Whether the criteria of a bank filter take a transaction. The comparison is on the normalised text (upper case, no
 * accents, single spaces); criteria that are not safe take nothing.
 */
final class BankFilterMatch
{
    /**
     * Whether the transaction is taken: direction, account, amount range, no excluded word, and every word of the
     * filter (or the pattern of an advanced one).
     *
     * @param  BankFilterCriteria  $criteria  what the filter takes
     * @param  BankTransaction  $transaction  the transaction
     */
    public static function matches(BankFilterCriteria $criteria, BankTransaction $transaction): bool
    {
        if (! $criteria->isSafe() || $transaction->kind() !== $criteria->direction) {
            return false;
        }

        if (! RuleAccount::reads($criteria->accountId, $transaction->financial_account_id)) {
            return false;
        }

        $cents = self::cents(abs((float) $transaction->amount));

        if (($criteria->amountMin !== null && $cents < self::cents((float) $criteria->amountMin))
            || ($criteria->amountMax !== null && $cents > self::cents((float) $criteria->amountMax))) {
            return false;
        }

        $text = BankMatch::normalize((string) $transaction->text);

        if (self::found($criteria->excludes, $text) !== []) {
            return false;
        }

        if ($criteria->pattern !== null) {
            return BankMatch::matches($criteria->pattern, $text);
        }

        return count(self::found($criteria->contains, $text)) === count(self::usable($criteria->contains));
    }

    /**
     * The words of the filter that are in the text, normalised, to highlight them. Empty for criteria that are not safe.
     *
     * @param  BankFilterCriteria  $criteria  what the filter takes
     * @param  string  $text  the text of a transaction, as the bank gave it
     * @return list<string>
     */
    public static function hits(BankFilterCriteria $criteria, string $text): array
    {
        if (! $criteria->isSafe()) {
            return [];
        }

        return self::found($criteria->contains, BankMatch::normalize($text));
    }

    /**
     * The normalised words that are not empty.
     *
     * @param  list<string>  $words  words or phrases
     * @return list<string>
     */
    private static function usable(array $words): array
    {
        return array_values(array_filter(array_map(BankMatch::normalize(...), $words), static fn (string $word): bool => $word !== ''));
    }

    /**
     * The words (normalised) that are a substring of the normalised text.
     *
     * @param  list<string>  $words  words or phrases
     * @param  string  $text  the normalised text
     * @return list<string>
     */
    private static function found(array $words, string $text): array
    {
        return array_values(array_filter(self::usable($words), static fn (string $word): bool => str_contains($text, $word)));
    }

    /**
     * An amount in cents, rounded so that no float noise reaches a comparison.
     *
     * @param  float  $amount  the amount
     */
    private static function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
