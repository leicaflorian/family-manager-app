<?php

declare(strict_types=1);

namespace App\Admin;

use Carbon\CarbonImmutable;

/**
 * The numbers of one family that the owner panel shows: counted, never read.
 */
final readonly class FamilyFigure
{
    /**
     * @param  int  $people  people of the family
     * @param  int  $documents  documents of the family
     * @param  int  $fileBytes  bytes of all its files
     * @param  int  $readings  automatic readings of the month (cropping excluded)
     * @param  float  $readingCost  cost in dollars of the AI calls of the month
     * @param  CarbonImmutable|null  $lastActivity  last request of any of its accounts
     */
    public function __construct(
        public int $people = 0,
        public int $documents = 0,
        public int $fileBytes = 0,
        public int $readings = 0,
        public float $readingCost = 0.0,
        public ?CarbonImmutable $lastActivity = null,
    ) {}
}
