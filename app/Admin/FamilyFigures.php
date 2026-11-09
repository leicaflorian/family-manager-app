<?php

declare(strict_types=1);

namespace App\Admin;

use App\Enums\ReadingPurpose;
use App\Services\Reading\ReadingAllowance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The figures counted across families for the owner panel. This is the only place that reads the tables of family
 * content from outside a family, and only with `count`, `sum` and `max` grouped by `family_id`: one query per table.
 * It goes through the query builder, which has no family scope.
 */
final readonly class FamilyFigures
{
    /**
     * @param  ReadingAllowance  $allowance  gives the edges of the month of Rome
     */
    public function __construct(private ReadingAllowance $allowance) {}

    /**
     * The figures of some families for a month.
     *
     * @param  list<int>  $familyIds  the families
     * @param  CarbonImmutable  $month  any moment of the month of the readings
     * @return array<int, FamilyFigure> by family id, a zeroed figure for a family with nothing
     */
    public function forFamilies(array $familyIds, CarbonImmutable $month): array
    {
        if ($familyIds === []) {
            return [];
        }

        [$from, $to] = $this->allowance->monthEdges($month);

        $people = $this->grouped('people', $familyIds)->selectRaw('count(*) as total')->pluck('total', 'family_id');
        $documents = $this->grouped('documents', $familyIds)->selectRaw('count(*) as total')->pluck('total', 'family_id');
        $bytes = $this->grouped('files', $familyIds)->selectRaw('sum(size) as total')->pluck('total', 'family_id');
        $readings = $this->grouped('ai_readings', $familyIds)
            ->where('purpose', '!=', ReadingPurpose::Crop->value)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->selectRaw('count(*) as total, sum(cost_usd) as cost')
            ->get()
            ->keyBy('family_id');
        $activity = DB::table('sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->whereIn('users.family_id', $familyIds)
            ->groupBy('users.family_id')
            ->selectRaw('users.family_id as family_id, max(sessions.last_activity) as last')
            ->pluck('last', 'family_id');

        $figures = [];
        foreach ($familyIds as $id) {
            $last = $activity[$id] ?? null;
            $figures[$id] = new FamilyFigure(
                people: $this->number($people[$id] ?? 0),
                documents: $this->number($documents[$id] ?? 0),
                fileBytes: $this->number($bytes[$id] ?? 0),
                readings: (int) ($readings[$id]->total ?? 0),
                readingCost: (float) ($readings[$id]->cost ?? 0),
                lastActivity: is_numeric($last) ? CarbonImmutable::createFromTimestamp((int) $last) : null,
            );
        }

        return $figures;
    }

    /**
     * The readings and the cost of a family month by month, the current month last.
     *
     * @param  int  $familyId  the family
     * @param  int  $months  how many months, counting the current one
     * @param  CarbonImmutable|null  $now  the moment that counts as today (default: now)
     * @return array<string, array{readings: int, cost: float}> keyed `yyyy-mm`, months with nothing included
     */
    public function monthlyReadings(int $familyId, int $months = 12, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $last = $now->setTimezone('Europe/Rome')->startOfMonth();
        $first = $last->subMonths($months - 1);

        $result = [];
        for ($month = $first; $month <= $last; $month = $month->addMonth()) {
            [$from, $to] = $this->allowance->monthEdges($month);
            $row = DB::table('ai_readings')
                ->where('family_id', $familyId)
                ->where('purpose', '!=', ReadingPurpose::Crop->value)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to)
                ->selectRaw('count(*) as total, sum(cost_usd) as cost')
                ->first();
            $result[$month->format('Y-m')] = ['readings' => (int) ($row->total ?? 0), 'cost' => round((float) ($row->cost ?? 0), 6)];
        }

        return $result;
    }

    /**
     * The last request of an account, from the sessions table.
     *
     * @param  int  $userId  the account
     */
    public function lastActivityOf(int $userId): ?CarbonImmutable
    {
        $last = DB::table('sessions')->where('user_id', $userId)->max('last_activity');

        return is_numeric($last) ? CarbonImmutable::createFromTimestamp((int) $last) : null;
    }

    /**
     * A count or a sum from the database as an integer.
     *
     * @param  mixed  $value  what the database gave back
     */
    private function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * A query on a table of family data, for some families, grouped by `family_id`.
     *
     * @param  string  $table  the table
     * @param  list<int>  $familyIds  the families
     */
    private function grouped(string $table, array $familyIds): Builder
    {
        return DB::table($table)->whereIn('family_id', $familyIds)->groupBy('family_id')->select('family_id');
    }
}
