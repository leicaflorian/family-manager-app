<?php

declare(strict_types=1);

namespace App\Admin;

use App\Enums\ReadingPurpose;
use App\Services\Reading\ReadingAllowance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The AI readings of a month and what they cost, one row per family. It is the one place that adds them up across
 * families, for the Letture AI page and for `ai-readings:report`. It goes through the query builder, which has no
 * family scope, and only counts and sums: no content is read.
 */
final readonly class ReadingReport
{
    /**
     * @param  ReadingAllowance  $allowance  gives the edges of the month of Rome
     */
    public function __construct(private ReadingAllowance $allowance) {}

    /**
     * The rows of a month, by family name (a deleted family last).
     *
     * @param  CarbonImmutable  $month  any moment of the month, in any time zone
     * @param  string|null  $provider  only the calls of this service
     * @param  string|null  $model  only the calls of this model
     * @return Collection<int, ReadingReportRow>
     */
    public function forMonth(CarbonImmutable $month, ?string $provider = null, ?string $model = null): Collection
    {
        [$from, $to] = $this->allowance->monthEdges($month);

        return DB::table('ai_readings')
            ->leftJoin('families', 'families.id', '=', 'ai_readings.family_id')
            ->where('ai_readings.created_at', '>=', $from)
            ->where('ai_readings.created_at', '<', $to)
            ->when($provider !== null && $provider !== '', fn ($query) => $query->where('ai_readings.provider', $provider))
            ->when($model !== null && $model !== '', fn ($query) => $query->where('ai_readings.model', $model))
            ->groupBy('ai_readings.family_id', 'families.name')
            ->selectRaw(
                'ai_readings.family_id as family_id, families.name as name, count(*) as calls, sum(ai_readings.purpose != ?) as readings, '
                .'sum(ai_readings.input_tokens) as input_tokens, sum(ai_readings.output_tokens) as output_tokens, '
                .'sum(ai_readings.cost_usd) as cost, sum(ai_readings.cost_usd is null) as unpriced',
                [ReadingPurpose::Crop->value],
            )
            ->get()
            ->map(fn (object $row): ReadingReportRow => new ReadingReportRow(
                familyId: $row->family_id === null ? null : (int) $row->family_id,
                label: $row->family_id === null ? __('Deleted family') : (string) $row->name,
                readings: (int) $row->readings,
                calls: (int) $row->calls,
                inputTokens: (int) $row->input_tokens,
                outputTokens: (int) $row->output_tokens,
                cost: (int) $row->unpriced === (int) $row->calls ? null : (float) $row->cost,
                unpriced: (int) $row->unpriced,
            ))
            ->sort(fn (ReadingReportRow $a, ReadingReportRow $b): int => [$a->familyId === null, $a->label, $a->familyId]
                <=> [$b->familyId === null, $b->label, $b->familyId])
            ->values();
    }

    /**
     * The total row of some rows.
     *
     * @param  Collection<int, ReadingReportRow>  $rows  the rows of the month
     */
    public function total(Collection $rows): ReadingReportRow
    {
        $calls = $rows->sum(fn (ReadingReportRow $row): int => $row->calls);
        $unpriced = $rows->sum(fn (ReadingReportRow $row): int => $row->unpriced);

        return new ReadingReportRow(
            familyId: null,
            label: __('Total'),
            readings: $rows->sum(fn (ReadingReportRow $row): int => $row->readings),
            calls: $calls,
            inputTokens: $rows->sum(fn (ReadingReportRow $row): int => $row->inputTokens),
            outputTokens: $rows->sum(fn (ReadingReportRow $row): int => $row->outputTokens),
            cost: $unpriced === $calls ? null : $rows->sum(fn (ReadingReportRow $row): float => $row->cost ?? 0.0),
            unpriced: $unpriced,
        );
    }

    /**
     * The services and the models used in a month, for the filters of the page.
     *
     * @param  CarbonImmutable  $month  any moment of the month
     * @return array{providers: list<string>, models: list<string>}
     */
    public function filterOptions(CarbonImmutable $month): array
    {
        [$from, $to] = $this->allowance->monthEdges($month);
        $column = fn (string $name): array => array_values(array_map(
            fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            DB::table('ai_readings')
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to)
                ->distinct()
                ->orderBy($name)
                ->pluck($name)
                ->all(),
        ));

        return ['providers' => $column('provider'), 'models' => $column('model')];
    }
}
