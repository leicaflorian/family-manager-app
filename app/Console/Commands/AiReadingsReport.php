<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiReading;
use App\Models\Family;
use App\Services\Reading\ReadingAllowance;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * A table of the AI readings of a month and what they cost, one row per family and a total. The month is the one of
 * Rome; the cost is the estimate saved with each reading, in dollars.
 */
#[Description('Show the AI readings of a month and their cost, per family')]
#[Signature('ai-readings:report {--month= : The month to report (aaaa-mm), by default the current one in Rome}')]
final class AiReadingsReport extends Command
{
    /**
     * Prints the table, or says that the month has no readings.
     *
     * @param  ReadingAllowance  $allowance  Gives the edges of the month of Rome.
     */
    public function handle(ReadingAllowance $allowance): int
    {
        $option = $this->option('month');
        $text = is_string($option) ? $option : '';
        $month = $text === '' ? CarbonImmutable::now('Europe/Rome') : $this->parseMonth($text);

        if (! $month instanceof CarbonImmutable) {
            $this->error('--month must be a month like 2026-10.');

            return self::FAILURE;
        }

        [$from, $to] = $allowance->monthEdges($month);

        // A list of pairs, not a map by name: two families may share a name
        /** @var list<array{string, Collection<int, AiReading>}> $byFamily */
        $byFamily = [];
        /** @var Collection<int, AiReading> $all */
        $all = new Collection;

        foreach (Family::query()->orderBy('name')->orderBy('id')->get(['id', 'name']) as $family) {
            $readings = CurrentFamily::using($family->id, fn (): Collection => AiReading::query()
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to)
                ->get(['id', 'input_tokens', 'output_tokens', 'cost_usd']));

            if ($readings->isNotEmpty()) {
                $byFamily[] = [$family->name, $readings];
                $all = $all->concat($readings);
            }
        }

        if ($byFamily === []) {
            $this->line('No readings in '.$month->settings(['locale' => 'en'])->translatedFormat('F Y').'.');

            return self::SUCCESS;
        }

        $rows = array_map(fn (array $pair): array => $this->row($pair[0], $pair[1]), $byFamily);
        $rows[] = $this->row('Total', $all);

        $this->table(['Family', 'Readings', 'Input tokens', 'Output tokens', 'Cost'], $rows);

        return self::SUCCESS;
    }

    /**
     * A day of the month written as `aaaa-mm`, or null when the text is not a real month.
     *
     * @param  string  $value  the text
     */
    private function parseMonth(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $month = CarbonImmutable::createFromFormat('!Y-m', $value, 'Europe/Rome');
        } catch (InvalidArgumentException) {
            return null;
        }

        return $month instanceof CarbonImmutable && $month->format('Y-m') === $value ? $month : null;
    }

    /**
     * A row of the table for some readings. The cost is `-` when none of them has a price.
     *
     * @param  string  $label  the first cell
     * @param  Collection<int, AiReading>  $readings  the readings to add up
     * @return list<string>
     */
    private function row(string $label, Collection $readings): array
    {
        $unpriced = $readings->whereNull('cost_usd')->count();
        // Four decimals: a reading costs a fraction of a cent, and two would show most months as 0,00 $
        $cost = $unpriced === $readings->count()
            ? '-'
            : number_format($readings->sum(fn (AiReading $reading): float => (float) $reading->cost_usd), 4, ',', '.').' $';

        return [
            $label,
            (string) $readings->count(),
            (string) $readings->sum(fn (AiReading $reading): int => $reading->input_tokens),
            (string) $readings->sum(fn (AiReading $reading): int => $reading->output_tokens),
            $unpriced > 0 ? "{$cost} ({$unpriced} without a price)" : $cost,
        ];
    }
}
