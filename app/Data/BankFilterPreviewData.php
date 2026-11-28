<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the criteria of a filter take among the movements of the last days of a first read (90), and what saving it
 * would do: the movements, how many it would pay, create, link, mark as a transfer or put aside, and how many another
 * filter takes already (while one does, the filter does not save).
 */
#[TypeScript]
final class BankFilterPreviewData extends Data
{
    /**
     * @param  list<BankFilterPreviewRowData>  $rows  the movements it takes, newest first, the sorted ones too
     * @param  array{pay: int, create: int, link: int, transfer: int, ignore: int}  $summary  what saving does with the movements to sort
     * @param  int  $conflicts  how many movements to sort another filter takes already
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $summary,
        public readonly int $conflicts,
    ) {}
}
