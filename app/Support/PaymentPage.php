<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\MovementData;
use App\Data\PaymentYearData;

/**
 * A page of the payments of a card, grouped by year as the whole list: the
 * latest payments first, across the years, each year keeping the total and
 * the count of all its payments.
 */
final class PaymentPage
{
    /**
     * The payments of a page. A page past the last one (or below 1) is the first.
     *
     * @param  list<PaymentYearData>  $years  all the payments by year, the latest first
     * @param  int  $page  the page asked for, from 1
     * @param  int  $perPage  how many payments a page shows
     * @return array{years: list<PaymentYearData>, page: int, next: ?int}
     */
    public static function of(array $years, int $page, int $perPage): array
    {
        $count = array_sum(array_map(static fn (PaymentYearData $year): int => count($year->payments), $years));
        $page = $page < 1 || ($page - 1) * $perPage >= $count ? 1 : $page;
        $skip = ($page - 1) * $perPage;
        $left = $perPage;
        $shown = [];

        foreach ($years as $year) {
            $inYear = count($year->payments);

            if ($skip >= $inYear) {
                $skip -= $inYear;

                continue;
            }

            /** @var list<MovementData> $payments */
            $payments = array_slice($year->payments, $skip, $left);
            $shown[] = new PaymentYearData($year->year, $year->total, $year->count, $payments);
            $left -= count($payments);
            $skip = 0;

            if ($left === 0) {
                break;
            }
        }

        return ['years' => $shown, 'page' => $page, 'next' => $page * $perPage < $count ? $page + 1 : null];
    }
}
