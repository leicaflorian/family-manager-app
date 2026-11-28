<?php

declare(strict_types=1);

namespace App\Support\Banks;

/**
 * What can be read in the text of a bank transaction beyond its words: the mandate and the creditor of a SEPA direct
 * debit (`MAN.…`, `CID.…`), whether it is the commission of one, and the words the system proposes for a filter.
 */
final class BankText
{
    /** The most words `words()` proposes. */
    private const int WORDS = 4;

    /** The words every direct debit, transfer or card payment has, which tell nothing about who it is for. */
    private const array COMMON = ['DOMICILIAZIONE', 'ADDEBITO', 'DIRETTO', 'SEPA', 'BONIFICO', 'PAGAMENTO', 'POS', 'COMMISSIONI'];

    /**
     * The mandate of a direct debit: what follows `MAN.` up to the next space. Null when the text has none.
     *
     * @param  string  $text  the text of a transaction, as the bank gave it
     */
    public static function mandate(string $text): ?string
    {
        return self::code('MAN', $text);
    }

    /**
     * The creditor of a direct debit: what follows `CID.` up to the next space. Null when the text has none.
     *
     * @param  string  $text  the text of a transaction, as the bank gave it
     */
    public static function creditor(string $text): ?string
    {
        return self::code('CID', $text);
    }

    /**
     * Whether the text is the commission of a direct debit: it starts with `COMMISSIONI` or `COMMISSIONE`.
     *
     * @param  string  $text  the text of a transaction, as the bank gave it
     */
    public static function isFee(string $text): bool
    {
        $normalized = BankMatch::normalize($text);

        return str_starts_with($normalized, 'COMMISSIONI') || str_starts_with($normalized, 'COMMISSIONE');
    }

    /**
     * Whether a word is one of those every direct debit, transfer or card payment has, which tell nothing about who it
     * is for (`DOMICILIAZIONE`, `SEPA`, `POS`…).
     *
     * @param  string  $word  a word, normalised
     */
    public static function isCommon(string $word): bool
    {
        return in_array($word, self::COMMON, true);
    }

    /**
     * The words proposed for a filter of this text: `MAN.<mandate>` when there is a mandate, else `CID.<creditor>`,
     * else the words of its signature without the common ones, at most four.
     *
     * @param  string  $text  the text of a transaction, as the bank gave it
     * @return list<string>
     */
    public static function words(string $text): array
    {
        if (($mandate = self::mandate($text)) !== null) {
            return ['MAN.'.$mandate];
        }

        if (($creditor = self::creditor($text)) !== null) {
            return ['CID.'.$creditor];
        }

        $words = array_filter(
            explode(' ', BankMatch::signature($text)),
            static fn (string $word): bool => $word !== '' && ! self::isCommon($word),
        );

        return array_slice(array_values($words), 0, self::WORDS);
    }

    /**
     * The code after a prefix such as `MAN.`, which must start a word.
     *
     * @param  string  $prefix  `MAN` or `CID`
     * @param  string  $text  the text of a transaction
     */
    private static function code(string $prefix, string $text): ?string
    {
        return preg_match('/(?:^| )'.$prefix.'\.(\S+)/', BankMatch::normalize($text), $found) === 1 ? $found[1] : null;
    }
}
