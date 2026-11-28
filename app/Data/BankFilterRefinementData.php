<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\Banks\BankFilterCriteria;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How the filter being written changed after *It is not of this group* (`RefineBankFilter`): the words and amounts it
 * has now, what changed and the word or the amount that changed.
 */
#[TypeScript]
final class BankFilterRefinementData extends Data
{
    /**
     * @param  list<string>  $contains  the words the text must all have
     * @param  list<string>  $excludes  the words the text must not have
     * @param  string|null  $amount_min  the smallest amount, without sign
     * @param  string|null  $amount_max  the largest amount, without sign
     * @param  string  $change  `excludes`, `contains` or `amount`
     * @param  string  $value  the word added, or the amount of the new limit
     */
    public function __construct(
        public readonly array $contains,
        public readonly array $excludes,
        public readonly ?string $amount_min,
        public readonly ?string $amount_max,
        public readonly string $change,
        public readonly string $value,
    ) {}

    /**
     * The data of what `RefineBankFilter::without()` gave.
     *
     * @param  array{criteria: BankFilterCriteria, change: string, value: string}  $refined  the answer
     */
    public static function fromRefined(array $refined): self
    {
        $criteria = $refined['criteria'];

        return new self($criteria->contains, $criteria->excludes, $criteria->amountMin, $criteria->amountMax, $refined['change'], $refined['value']);
    }
}
