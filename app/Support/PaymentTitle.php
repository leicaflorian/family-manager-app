<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The title of the expense or income that settles a due date: the title of the
 * planned item and the month and year of the due date, not of the payment.
 */
final class PaymentTitle
{
    /**
     * «Retta danza novembre 2026».
     *
     * @param  string  $title  The title of the planned item.
     * @param  CarbonImmutable  $originalDate  The original date of the due date.
     */
    public static function for(string $title, CarbonImmutable $originalDate): string
    {
        $month = $originalDate->translatedFormat('F');

        return $title.' '.(app()->getLocale() === 'it' ? mb_strtolower($month) : $month).' '.$originalDate->format('Y');
    }
}
