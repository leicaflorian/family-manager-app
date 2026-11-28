<?php

declare(strict_types=1);

namespace App\Support\Banks;

use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;

/**
 * The text of a bank transaction as it may go to an AI model: IBANs, amounts and the names of the people of the family
 * are replaced by a placeholder. Mandate and creditor codes (`MAN.…`, `CID.…`) and the names of shops stay, since they
 * are the words a filter is made of. Only what is sent is masked: matching and the fallback read the bank's text.
 */
final class BankTextMask
{
    /** What an IBAN becomes. */
    public const string IBAN = 'IBAN';

    /** What an amount becomes. */
    public const string AMOUNT = 'IMPORTO';

    /** What a name of a person of the family becomes. */
    public const string PERSON = 'PERSONA';

    /** A name shorter than this is not masked: it would hide ordinary words («AL», «DI»). */
    private const int MIN_NAME = 3;

    /** An IBAN, not glued to a letter, a digit or a dot (so the codes after `MAN.` and `CID.` stay). */
    private const string IBAN_PATTERN = '/(?<![A-Z0-9.])[A-Z]{2}\d{2}[A-Z0-9]{11,30}(?![A-Z0-9])/';

    /** An amount with two decimals and thousands separators, with `EUR` before or after. */
    private const string AMOUNT_PATTERN = '/(?<![A-Z0-9.,])(?:EUR ?)?\d{1,3}(?:[.,]?\d{3})*[.,]\d{2}(?: ?EUR)?(?![0-9A-Z])/';

    /**
     * The text with IBANs, amounts and the names given replaced.
     *
     * @param  string  $normalized  the text, already normalised (`BankMatch::normalize()`)
     * @param  list<string>  $names  the first and last names to hide, as they are written
     */
    public static function forModel(string $normalized, array $names): string
    {
        $text = (string) preg_replace(self::IBAN_PATTERN, self::IBAN, $normalized);
        $text = (string) preg_replace(self::AMOUNT_PATTERN, self::AMOUNT, $text);

        foreach (self::words($names) as $name) {
            $text = (string) preg_replace('/(?<![A-Z0-9])'.preg_quote($name, '/').'(?![A-Z0-9])/i', self::PERSON, $text);
        }

        return $text;
    }

    /**
     * The first and last names of the people of the current family and of its accounts.
     *
     * @return list<string>
     */
    public static function familyNames(): array
    {
        $family = CurrentFamily::id();

        if ($family === null) {
            return [];
        }

        $names = [];

        foreach (Person::query()->get(['first_name', 'last_name']) as $person) {
            $names[] = $person->first_name;
            $names[] = $person->last_name;
        }

        foreach (User::query()->inFamily($family)->get(['name']) as $user) {
            $names[] = $user->name;
        }

        return $names;
    }

    /**
     * The single words of the names, normalised, long enough and without repeats, the longest first (so a name is
     * replaced before a shorter one inside it).
     *
     * @param  list<string>  $names  the names, one or more words each
     * @return list<string>
     */
    private static function words(array $names): array
    {
        $words = [];

        foreach ($names as $name) {
            foreach (explode(' ', BankMatch::normalize($name)) as $word) {
                if (mb_strlen($word) >= self::MIN_NAME) {
                    $words[$word] = true;
                }
            }
        }

        $words = array_map(strval(...), array_keys($words));
        usort($words, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $words;
    }
}
