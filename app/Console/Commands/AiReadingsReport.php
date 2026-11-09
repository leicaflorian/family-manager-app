<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Admin\ReadingReport;
use App\Admin\ReadingReportRow;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
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
     * @param  ReadingReport  $report  Adds up the readings of the month.
     */
    public function handle(ReadingReport $report): int
    {
        $option = $this->option('month');
        $text = is_string($option) ? $option : '';
        $month = $text === '' ? CarbonImmutable::now('Europe/Rome') : $this->parseMonth($text);

        if (! $month instanceof CarbonImmutable) {
            $this->error('--month must be a month like 2026-10.');

            return self::FAILURE;
        }

        $rows = $report->forMonth($month);

        if ($rows->isEmpty()) {
            $this->line('No readings in '.$month->settings(['locale' => 'en'])->translatedFormat('F Y').'.');

            return self::SUCCESS;
        }

        $table = $rows->push($report->total($rows))->map(fn (ReadingReportRow $row): array => $this->row($row))->all();

        $this->table(['Family', 'Readings', 'Input tokens', 'Output tokens', 'Cost'], $table);

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
     * A row of the table. The count is of every call, cropping included, as the command always did (the panel counts
     * readings without the cropping); the cost is `-` when none of them has a price.
     *
     * @param  ReadingReportRow  $row  the figures of the row
     * @return list<string>
     */
    private function row(ReadingReportRow $row): array
    {
        $cost = $row->costText();

        return [
            $row->label,
            (string) $row->calls,
            (string) $row->inputTokens,
            (string) $row->outputTokens,
            $row->unpriced > 0 ? "{$cost} ({$row->unpriced} without a price)" : $cost,
        ];
    }
}
