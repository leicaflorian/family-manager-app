<?php

declare(strict_types=1);

namespace App\Actions\Consumption;

use App\Jobs\ReadConsumption;
use App\Models\Document;
use App\Models\User;
use App\Models\Utility;
use App\Services\ConsumptionPage;
use App\Services\Reading\ArchiveReadingRun;
use App\Services\Reading\ReadingAllowance;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * *Read the bills*: queues the reading of the consumption of the bills of a utility already in the archive, in the
 * current family. Only the bills the page counts (a PDF or a picture, without consumption, never read for it), the
 * most recent first, as many as the readings left this month.
 */
final readonly class QueueConsumptionReadings
{
    /**
     * @param  ReadingAllowance  $allowance  the readings left this month
     * @param  ArchiveReadingRun  $run  the run the page follows
     */
    public function __construct(private ReadingAllowance $allowance, private ArchiveReadingRun $run) {}

    /**
     * Queues one reading per bill.
     *
     * @param  Utility  $utility  the utility, of the current family
     * @param  User  $user  who asked: the calls are theirs
     * @param  string  $locale  their language (`it` or `en`)
     * @return int how many bills were queued
     *
     * @throws LogicException outside a family
     */
    public function handle(Utility $utility, User $user, string $locale): int
    {
        $familyId = CurrentFamily::id() ?? throw new LogicException('The bills are read in a family.');
        $left = $this->allowance->left(CarbonImmutable::now('Europe/Rome'));

        if ($left === 0) {
            return 0;
        }

        $ids = array_values(ConsumptionPage::unreadBills($utility)
            ->orderByDesc('documents.date')
            ->orderByDesc('documents.id')
            ->limit($left)
            ->get(['documents.id'])
            ->map(static fn (Document $document): int => $document->id)
            ->all());

        if ($ids === []) {
            return 0;
        }

        // The run starts before the jobs: with a queue that runs at once, a job ends before the next line
        $this->run->start($utility->id, $ids);

        foreach ($ids as $id) {
            dispatch(new ReadConsumption($familyId, $utility->id, $id, $user->id, $locale));
        }

        return count($ids);
    }
}
