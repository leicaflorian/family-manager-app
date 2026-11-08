<?php

declare(strict_types=1);

namespace App\Support\Mail;

/**
 * How the address of a family for the bills is made: the family name, a dot and six random characters.
 */
final class MailAddress
{
    /** No i, l, o, 0, 1: they are mistaken for one another when the address is copied by hand. */
    public const string CHARACTERS = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** How many random characters the address has. */
    public const int RANDOM_LENGTH = 6;

    /** The longest name part of the address. */
    public const int MAX_LOCAL_LENGTH = 20;

    /** Used for a name with no letter the address can have. */
    public const string FALLBACK = 'famiglia';

    /** The letters that are not a letter plus an accent: transliteration leaves them out. */
    private const array PLAIN = ['ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ø' => 'o', 'đ' => 'd', 'ð' => 'd', 'ł' => 'l', 'þ' => 'th', 'ı' => 'i'];

    /**
     * The name of the family as the address has it: no accents, lower case, only letters and digits, at most 20.
     *
     * @param  string  $familyName  the name of the family
     */
    public static function localPart(string $familyName): string
    {
        $name = strtr(mb_strtolower($familyName), self::PLAIN);
        // Splits each accented letter into the letter and its accent: the accent is then dropped with the rest
        $decomposed = \Normalizer::normalize($name, \Normalizer::FORM_D);
        $plain = preg_replace('/[^a-z0-9]/', '', is_string($decomposed) ? $decomposed : $name);

        return substr(is_string($plain) ? $plain : '', 0, self::MAX_LOCAL_LENGTH) ?: self::FALLBACK;
    }

    /**
     * A new address: `<name>.<six random characters>@<domain>`.
     *
     * @param  string  $familyName  the name of the family
     * @param  string  $domain  the domain of the mail inbox
     */
    public static function make(string $familyName, string $domain): string
    {
        $random = '';

        for ($i = 0; $i < self::RANDOM_LENGTH; $i++) {
            $random .= self::CHARACTERS[random_int(0, strlen(self::CHARACTERS) - 1)];
        }

        return self::localPart($familyName).'.'.$random.'@'.$domain;
    }
}
