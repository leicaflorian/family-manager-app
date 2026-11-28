<?php

declare(strict_types=1);

namespace App\Support\Banks;

use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;

/**
 * The text of a bank transaction as it may go to an AI model: IBANs, amounts, card numbers, other long numbers and the
 * names of the people of the family are replaced by a placeholder. Mandate and creditor codes (`MAN.…`, `CID.…`,
 * `ID CREDITORE …`), invoice numbers shorter than nine digits and the names of shops stay, since they are the words a
 * filter is made of. Only what is sent is masked: matching and the fallback read the bank's text.
 */
final class BankTextMask
{
    /** What an IBAN becomes. */
    public const string IBAN = 'IBAN';

    /** What an amount becomes. */
    public const string AMOUNT = 'IMPORTO';

    /** What a card number or its last digits become. */
    public const string CARD = 'CARTA';

    /** What another number of nine digits or more (an account, a phone) becomes. */
    public const string NUMBER = 'NUMERO';

    /** What a name of a person of the family becomes. */
    public const string PERSON = 'PERSONA';

    /** A name shorter than this is not masked: it would hide ordinary words («AL», «DI»). */
    private const int MIN_NAME = 3;

    /** The codes kept as they are, set aside before masking: the mandate, the creditor and the creditor id. */
    private const string KEPT_PATTERN = '/(?<![A-Z0-9])(?:MAN\.|CID\.|ID CREDITORE:? ?)\S+/';

    /** A number, with thousands separators and up to two decimals. */
    private const string NUMBER_PART = '\d+(?:[.,]\d{3})*(?:[.,]\d{1,2})?';

    /**
     * What is masked, in order, with what it becomes: IBANs (whole or in groups of four), then card numbers and their
     * tails, then amounts (next to a currency, or with two decimals), then any other run of nine digits or more. A
     * placeholder glued to a letter is set apart by a space.
     *
     * @var list<array{string, string}>
     */
    private const array MASKS = [
        ['/(?<![A-Z0-9])[A-Z]{2}\d{2}[A-Z0-9]{11,30}(?![A-Z0-9])/', self::IBAN],
        ['/(?<![A-Z0-9])[A-Z]{2}\d{2}(?: [A-Z0-9]{4}){3,7}(?: [A-Z0-9]{1,3})?(?![A-Z0-9])/', self::IBAN],
        ['/(?<![A-Z0-9*])(?:\*+|X{2,})\d{3,4}(?!\d)/', self::CARD],
        ['/(?<!\d)\d{13,19}(?!\d)/', self::CARD],
        ['/(?<![\d.,])(?:EURO? ?'.self::NUMBER_PART.'|'.self::NUMBER_PART.' ?EURO?(?![A-Z]))(?![\d.,]*\d)/', self::AMOUNT],
        ['/(?<![\d.,])\d{1,3}(?:[.,]?\d{3})*[.,]\d{2}(?![\d.,]*\d)/', self::AMOUNT],
        ['/(?<!\d)\d{9,}(?!\d)/', self::NUMBER],
    ];

    /**
     * The text with IBANs, amounts, card numbers, long numbers and the names given replaced.
     *
     * @param  string  $normalized  the text, already normalised (`BankMatch::normalize()`)
     * @param  list<string>  $names  the first and last names to hide, as they are written
     */
    public static function forModel(string $normalized, array $names): string
    {
        // the codes to keep step aside behind a marker with no letters nor digits, so no mask can touch them
        $kept = [];
        $text = (string) preg_replace_callback(self::KEPT_PATTERN, static function (array $found) use (&$kept): string {
            $kept[] = $found[0];

            return "\x00".str_repeat("\x01", count($kept))."\x00";
        }, $normalized);

        // each placeholder sits between an opening and a closing marker, so a later mask sees its edges and a letter
        // glued to it gets a space
        foreach (self::MASKS as [$pattern, $token]) {
            $text = (string) preg_replace($pattern, "\x02{$token}\x03", $text);
        }

        $text = str_replace(["\x02", "\x03"], '', (string) preg_replace(['/(?<=[A-Z])\x02/', '/\x03(?=[A-Z])/'], ' ', $text));

        foreach (self::words($names) as $name) {
            $text = (string) preg_replace('/(?<![A-Z0-9])'.preg_quote($name, '/').'(?![A-Z0-9])/i', self::PERSON, $text);
        }

        // longest markers first, so one marker is never found inside another
        for ($index = count($kept); $index >= 1; $index--) {
            $text = str_replace("\x00".str_repeat("\x01", $index)."\x00", $kept[$index - 1], $text);
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
