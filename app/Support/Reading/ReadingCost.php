<?php

declare(strict_types=1);

namespace App\Support\Reading;

use Carbon\CarbonImmutable;

/**
 * Estimates what a reading cost in dollars, from the prices in
 * `config('document_reader.prices')`.
 */
final class ReadingCost
{
    /**
     * Estimated cost in dollars, or null for a model without a known price.
     *
     * @param  string  $model  Model named by the answer; may be a dated version (`claude-haiku-4-5-20251001`).
     * @param  int  $input  Tokens sent.
     * @param  int  $output  Tokens answered.
     * @param  CarbonImmutable  $day  Day of the reading: picks the price in force.
     */
    public static function estimate(string $model, int $input, int $output, CarbonImmutable $day): ?float
    {
        /** @var array<string, list<array{input: int|float, output: int|float, from?: string}>> $prices */
        $prices = config('document_reader.prices');

        // Longest name first, so "gemini-3.5-flash-lite" is not priced as "gemini-3.5-flash".
        $names = array_keys($prices);
        usort($names, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $name = collect($names)->first(
            static fn (string $name): bool => $model === $name || str_starts_with($model, $name.'-'),
        );

        if ($name === null) {
            return null;
        }

        $today = $day->toDateString();
        $price = collect($prices[$name])
            ->filter(static fn (array $price): bool => ! isset($price['from']) || $price['from'] <= $today)
            ->last();

        if ($price === null) {
            return null;
        }

        return ($input * $price['input'] + $output * $price['output']) / 1_000_000;
    }
}
