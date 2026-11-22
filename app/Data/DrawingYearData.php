<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The drawings uploaded in one year, newest first.
 */
#[TypeScript]
final class DrawingYearData extends Data
{
    /**
     * @param  int  $year  the upload year
     * @param  int  $count  how many drawings
     * @param  list<DrawingData>  $drawings  the drawings, newest first
     */
    public function __construct(
        public readonly int $year,
        public readonly int $count,
        public readonly array $drawings,
    ) {}
}
