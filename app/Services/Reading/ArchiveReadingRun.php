<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Support\CurrentFamily;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * The run of *Read the bills* of a utility of the current family: which bills were queued and which are still to read,
 * so the page can say that the bills are being read and how many are done. It lives in the shared cache under the
 * family and the utility, and fades an hour after the last change, so a job lost by the queue does not keep the page
 * waiting forever.
 */
final class ArchiveReadingRun
{
    /** How long a run is kept after its last change, in seconds. */
    private const int TTL = 3600;

    /** How long a change waits for another one of the same run, in seconds. */
    private const int LOCK_WAIT = 5;

    /**
     * Adds bills to the run of a utility, starting one if none is going.
     *
     * @param  int  $utilityId  the utility
     * @param  list<int>  $documentIds  the bills queued
     */
    public function start(int $utilityId, array $documentIds): void
    {
        $this->change($utilityId, static fn (array $run): array => [
            'ids' => array_values(array_unique([...$run['ids'], ...$documentIds])),
            'pending' => array_values(array_unique([...$run['pending'], ...$documentIds])),
        ]);
    }

    /**
     * Takes a bill out of the bills still to read; the run ends with the last one.
     *
     * @param  int  $utilityId  the utility
     * @param  int  $documentId  the bill read, or given up
     */
    public function finish(int $utilityId, int $documentId): void
    {
        $this->change($utilityId, static fn (array $run): array => [
            'ids' => $run['ids'],
            'pending' => array_values(array_diff($run['pending'], [$documentId])),
        ]);
    }

    /**
     * How far the run of a utility is.
     *
     * @param  int  $utilityId  the utility
     * @return array{done: int, total: int}|null the bills read and queued, or null when nothing is being read
     */
    public function progress(int $utilityId): ?array
    {
        $run = $this->read($utilityId);

        return $run['pending'] === [] ? null : ['done' => count($run['ids']) - count($run['pending']), 'total' => count($run['ids'])];
    }

    /**
     * Changes the run under a lock, so two jobs that end together do not lose each other's change.
     *
     * @param  int  $utilityId  the utility
     * @param  callable(array{ids: list<int>, pending: list<int>}): array{ids: list<int>, pending: list<int>}  $change  the run after the change
     */
    private function change(int $utilityId, callable $change): void
    {
        $key = $this->key($utilityId);

        Cache::lock($key.':lock', self::LOCK_WAIT)->block(self::LOCK_WAIT, function () use ($key, $utilityId, $change): void {
            $run = $change($this->read($utilityId));

            if ($run['pending'] === []) {
                Cache::forget($key);

                return;
            }

            Cache::put($key, $run, self::TTL);
        });
    }

    /**
     * The run of a utility as stored, or an empty one.
     *
     * @param  int  $utilityId  the utility
     * @return array{ids: list<int>, pending: list<int>}
     */
    private function read(int $utilityId): array
    {
        $run = Cache::get($this->key($utilityId));

        if (! is_array($run) || ! is_array($run['ids'] ?? null) || ! is_array($run['pending'] ?? null)) {
            return ['ids' => [], 'pending' => []];
        }

        return [
            'ids' => array_values(array_filter($run['ids'], is_int(...))),
            'pending' => array_values(array_filter($run['pending'], is_int(...))),
        ];
    }

    /**
     * The key of the run of a utility of the current family.
     *
     * @param  int  $utilityId  the utility
     *
     * @throws LogicException outside a family
     */
    private function key(int $utilityId): string
    {
        $familyId = CurrentFamily::id() ?? throw new LogicException('A run of the archive belongs to a family.');

        return sprintf('consumption-reading:%d:%d', $familyId, $utilityId);
    }
}
