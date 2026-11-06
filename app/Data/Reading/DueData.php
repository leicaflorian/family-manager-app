<?php

declare(strict_types=1);

namespace App\Data\Reading;

use App\Enums\DueMode;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * "Already paid" or "to pay" for the expense of a file: the automatic choice, the deadline and, with "to pay" in
 * effect, what it does with the deadline. Without "to pay" in effect `mode` is `new` and `plan` and `occurrence` are
 * empty.
 */
#[TypeScript]
final class DueData extends Data
{
    /**
     * @param  bool  $suggested  the automatic choice: a bill due after today
     * @param  string|null  $date  `YYYY-MM-DD`, the deadline written by the person, otherwise the one read
     * @param  array{id: int, title: string, date: string}|null  $plan  the planned expense the bill joins: the one-off already there (`existing`), or the commitment whose due date changes (`exception`, dated on the due date)
     * @param  string|null  $occurrence  `YYYY-MM-DD`, the original date of the due date that changes (`exception`)
     * @param  DueMode  $mode  what "to pay" does
     */
    public function __construct(
        public readonly bool $suggested,
        public readonly ?string $date,
        public readonly ?array $plan,
        public readonly ?string $occurrence,
        public readonly DueMode $mode,
    ) {}
}
