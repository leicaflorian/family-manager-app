<?php

declare(strict_types=1);

namespace App\Support\Banks;

use App\Models\BankTransaction;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * *It is not of this group*: how to change what a filter takes so that it leaves one movement out and still takes all
 * the others, without asking the database or the AI. In this order: a word only the movement taken out has goes to
 * *Does not contain*; else a word all the others have and it does not goes to *Contains*; else an amount range that
 * keeps all the others and leaves it out. A word has three letters or more and is not one of the words every payment
 * has (`BankText::isCommon()`). Every answer is checked on the movements before it is given.
 */
final class RefineBankFilter
{
    /**
     * The criteria that leave `$drop` out and keep every one of `$keep`, with what changed. Null when none of the
     * three ways is enough, when nothing is kept (the filter would take nothing) or when the filter does not take
     * `$drop` already (a list that changed meanwhile).
     *
     * @param  BankFilterCriteria  $criteria  what the filter takes now
     * @param  Collection<int, BankTransaction>  $keep  the movements it must still take
     * @param  BankTransaction  $drop  the movement taken out
     * @return array{criteria: BankFilterCriteria, change: string, value: string}|null `change` is `excludes`, `contains` or `amount`; `value` the word, or the amount of the new limit
     */
    public static function without(BankFilterCriteria $criteria, Collection $keep, BankTransaction $drop): ?array
    {
        // Nothing kept would leave a filter that takes nothing; a movement the filter already leaves needs no change
        if ($keep->isEmpty() || ! BankFilterMatch::matches(self::anyAccount($criteria), $drop)) {
            return null;
        }

        $kept = $keep->map(static fn (BankTransaction $row): string => BankMatch::normalize((string) $row->text))->values()->all();
        $dropped = BankMatch::normalize((string) $drop->text);

        foreach (self::words((string) $drop->text) as $word) {
            if (! array_any($kept, static fn (string $text): bool => str_contains($text, $word))) {
                $found = self::checked(self::with($criteria, excludes: [...$criteria->excludes, $word]), 'excludes', $word, $keep, $drop);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        $already = array_map(BankMatch::normalize(...), $criteria->contains);

        foreach (self::words((string) $keep->firstOrFail()->text) as $word) {
            if (! in_array($word, $already, true) && ! str_contains($dropped, $word)
                && array_all($kept, static fn (string $text): bool => str_contains($text, $word))) {
                $found = self::checked(self::with($criteria, contains: [...$criteria->contains, $word]), 'contains', $word, $keep, $drop);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return self::amount($criteria, $keep, $drop);
    }

    /**
     * The range of the amounts of the movements kept, open on the side of the one taken out: from the smallest kept
     * when it is smaller than all of them, up to the biggest kept when it is bigger. Null when it is in between.
     *
     * @param  BankFilterCriteria  $criteria  what the filter takes now
     * @param  Collection<int, BankTransaction>  $keep  the movements it must still take
     * @param  BankTransaction  $drop  the movement taken out
     * @return array{criteria: BankFilterCriteria, change: string, value: string}|null
     */
    private static function amount(BankFilterCriteria $criteria, Collection $keep, BankTransaction $drop): ?array
    {
        $cents = $keep->map(static fn (BankTransaction $row): int => Money::absCents($row->amount));
        $dropped = Money::absCents($drop->amount);
        $min = (int) $cents->min();
        $max = (int) $cents->max();

        if ($dropped < $min) {
            $limit = Money::decimal($min);

            return self::checked(self::with($criteria, amountMin: $limit), 'amount', $limit, $keep, $drop);
        }

        if ($dropped > $max) {
            $limit = Money::decimal($max);

            return self::checked(self::with($criteria, amountMax: $limit), 'amount', $limit, $keep, $drop);
        }

        return null;
    }

    /**
     * The answer, only when the new criteria are safe, take every movement kept and leave the one taken out. The
     * account is left out of the check: it does not change, the movements kept are taken by it already, and reading
     * whether it is linked to a bank would ask the database (`RuleAccount`).
     *
     * @param  BankFilterCriteria  $refined  the new criteria
     * @param  string  $change  what changed
     * @param  string  $value  the word or the amount
     * @param  Collection<int, BankTransaction>  $keep  the movements it must still take
     * @param  BankTransaction  $drop  the movement taken out
     * @return array{criteria: BankFilterCriteria, change: string, value: string}|null
     */
    private static function checked(BankFilterCriteria $refined, string $change, string $value, Collection $keep, BankTransaction $drop): ?array
    {
        $anyAccount = self::anyAccount($refined);
        $good = $refined->isSafe()
            && ! BankFilterMatch::matches($anyAccount, $drop)
            && $keep->every(static fn (BankTransaction $row): bool => BankFilterMatch::matches($anyAccount, $row));

        return $good ? ['criteria' => $refined, 'change' => $change, 'value' => $value] : null;
    }

    /**
     * The criteria on every account: the account does not change here, and reading whether it is linked to a bank
     * would ask the database (`RuleAccount`).
     *
     * @param  BankFilterCriteria  $criteria  the criteria
     */
    private static function anyAccount(BankFilterCriteria $criteria): BankFilterCriteria
    {
        return new BankFilterCriteria($criteria->contains, $criteria->excludes, $criteria->direction, null, $criteria->amountMin, $criteria->amountMax, $criteria->pattern);
    }

    /**
     * The criteria with some parts changed.
     *
     * @param  BankFilterCriteria  $criteria  the criteria of now
     * @param  list<string>|null  $contains  the new words to have
     * @param  list<string>|null  $excludes  the new words not to have
     * @param  numeric-string|null  $amountMin  the new smallest amount
     * @param  numeric-string|null  $amountMax  the new largest amount
     */
    private static function with(BankFilterCriteria $criteria, ?array $contains = null, ?array $excludes = null, ?string $amountMin = null, ?string $amountMax = null): BankFilterCriteria
    {
        return new BankFilterCriteria(
            contains: $contains ?? $criteria->contains,
            excludes: $excludes ?? $criteria->excludes,
            direction: $criteria->direction,
            accountId: $criteria->accountId,
            amountMin: $amountMin ?? $criteria->amountMin,
            amountMax: $amountMax ?? $criteria->amountMax,
            pattern: $criteria->pattern,
        );
    }

    /**
     * The words of a text a filter can use, in their order and once: letters only, three or more, none of the common
     * ones.
     *
     * @param  string  $text  the text of a movement, as the bank gave it
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $words = array_filter(
            explode(' ', BankMatch::signature($text)),
            static fn (string $word): bool => mb_strlen($word) >= BankFilterCriteria::MIN_WORD && ! BankText::isCommon($word),
        );

        return array_values(array_unique($words));
    }
}
