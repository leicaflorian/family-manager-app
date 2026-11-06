<?php

declare(strict_types=1);

namespace App\Support\Reading;

use Carbon\CarbonImmutable;

/**
 * Small pieces of text shared by the titles a reading proposes.
 */
final class ReadingText
{
    /**
     * The month name in the language of the title; the Italian ones are written in lowercase.
     *
     * @param  CarbonImmutable  $date  the day
     * @param  string  $locale  the language
     */
    public static function month(CarbonImmutable $date, string $locale): string
    {
        /** @var CarbonImmutable $localized `locale()` gives back the same kind of date */
        $localized = $date->locale($locale);
        $month = $localized->translatedFormat('F');

        return $locale === 'it' ? mb_strtolower($month) : $month;
    }
}
