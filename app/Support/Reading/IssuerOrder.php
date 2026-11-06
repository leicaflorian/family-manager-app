<?php

declare(strict_types=1);

namespace App\Support\Reading;

use Carbon\CarbonImmutable;

/**
 * How the searches for what a payment receipt pays choose among candidates: one of another issuer is left out
 * (one with no issuer stays), and the order is the same issuer first, then the closest date, then the lowest id.
 */
final class IssuerOrder
{
    /**
     * Whether a candidate can be the one: its issuer is unknown, or the wanted one, or nothing is wanted.
     *
     * @param  int|null  $candidateOrganizationId  the issuer of the candidate
     * @param  int|null  $organizationId  the issuer of the paper, if known
     */
    public static function allows(?int $candidateOrganizationId, ?int $organizationId): bool
    {
        return $organizationId === null || $candidateOrganizationId === null || $candidateOrganizationId === $organizationId;
    }

    /**
     * The key to sort a candidate by: the same issuer first, the closest date, the lowest id.
     *
     * @param  int|null  $candidateOrganizationId  the issuer of the candidate
     * @param  int|null  $organizationId  the issuer of the paper, if known
     * @param  CarbonImmutable  $candidateDate  the date of the candidate
     * @param  CarbonImmutable  $day  the day of the paper
     * @param  int  $id  the id of the candidate (of its planned expense, for a due date)
     * @return array{int, int, int}
     */
    public static function key(?int $candidateOrganizationId, ?int $organizationId, CarbonImmutable $candidateDate, CarbonImmutable $day, int $id): array
    {
        return [
            (int) ($organizationId === null || $candidateOrganizationId !== $organizationId),
            self::daysApart($candidateDate, $day),
            $id,
        ];
    }

    /**
     * Whole days between two dates, whichever comes first.
     *
     * @param  CarbonImmutable  $a  a date
     * @param  CarbonImmutable  $b  another date
     */
    public static function daysApart(CarbonImmutable $a, CarbonImmutable $b): int
    {
        return (int) abs($a->startOfDay()->diffInDays($b->startOfDay()));
    }
}
