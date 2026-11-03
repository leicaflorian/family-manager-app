<?php

declare(strict_types=1);

namespace App\Support\Reading;

/**
 * Compares organization names written in different ways: without case,
 * punctuation, spaces and legal form ("Edison Energia S.p.A." is "edisonenergia").
 */
final class OrganizationKey
{
    /** Legal forms at the end of a name, already without dots and spaces. */
    private const string LEGAL_FORMS = '/(spa|srl|srls|snc|sas|sapa|scarl|scrl)$/u';

    /**
     * The comparison key of a name.
     *
     * @param  string|null  $name  the name as printed or stored
     * @return string the key, empty for null or no letters; a name that is only a legal form keeps it
     */
    public static function of(?string $name): string
    {
        $plain = preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($name ?? '')) ?? '';
        $bare = preg_replace(self::LEGAL_FORMS, '', $plain) ?? $plain;

        return $bare === '' ? $plain : $bare;
    }
}
