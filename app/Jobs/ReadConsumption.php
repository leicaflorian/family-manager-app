<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Consumption\ConsumptionValues;
use App\Actions\Consumption\SaveConsumption;
use App\Contracts\DocumentReader;
use App\Data\Reading\ConsumptionReading;
use App\Enums\ConsumptionOutcome;
use App\Enums\ConsumptionSource;
use App\Enums\ReadingPurpose;
use App\Enums\UtilityType;
use App\Exceptions\Reading\ReadingFailed;
use App\Exceptions\Reading\ReadingSkipped;
use App\Models\AiReading;
use App\Models\Document;
use App\Models\File;
use App\Models\Utility;
use App\Services\Reading\ArchiveReadingRun;
use App\Services\Reading\ReadingAllowance;
use App\Services\Reading\ReadingSource;
use App\Support\CurrentFamily;
use App\Support\Reading\ConsumptionCheck;
use App\Support\Reading\ValidDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * The reading of the consumption of one bill already in the archive (*Read the bills*), in the queue: one job per file
 * at a time. It runs outside a request, so it works in the family of the bill, as the account that asked. It does not
 * call the model when the reading is off, the readings of the month are used up, or the bill already has its
 * consumption or a consumption reading. A consumption that passes `ConsumptionCheck` is saved; the row of the call
 * says what came of it. A bill of electricity and gas saves, with the same call, the other utilities linked to it.
 *
 * A service that is busy or cannot be reached is tried again after 10, 30 and 90 seconds: that call was not paid. Once
 * the model answered the call is paid, so nothing after it throws: an error is reported and the job ends, and the queue
 * never pays the same bill twice. A try lasts at most 80 seconds, less than the 90 of `retry_after` of the queue.
 */
#[Backoff([10, 30, 90])]
#[Timeout(80)]
#[Tries(3)]
final class ReadConsumption implements ShouldBeUnique, ShouldQueue
{
    use Localizable;
    use Queueable;

    /**
     * @param  int  $familyId  the family of the bill
     * @param  int  $utilityId  the utility the bill belongs to
     * @param  int  $documentId  the bill
     * @param  int  $userId  who asked to read it
     * @param  string  $locale  the language of who asked (`it` or `en`)
     */
    public function __construct(
        public readonly int $familyId,
        public readonly int $utilityId,
        public readonly int $documentId,
        public readonly int $userId,
        public readonly string $locale,
    ) {}

    /**
     * The key that keeps a second job of the same bill out of the queue.
     */
    public function uniqueId(): string
    {
        return (string) $this->documentId;
    }

    /**
     * Reads the consumption of the bill, unless there is no need or no way.
     *
     * @param  DocumentReader  $reader  the model
     * @param  ReadingAllowance  $allowance  the readings left this month
     * @param  ArchiveReadingRun  $run  the run of the page, told when the bill is done
     *
     * @throws ReadingFailed when the service is busy or cannot be reached, so that the queue tries again
     */
    public function handle(DocumentReader $reader, ReadingAllowance $allowance, ArchiveReadingRun $run): void
    {
        $this->withLocale($this->locale, function () use ($reader, $allowance, $run): void {
            CurrentFamily::using($this->familyId, function () use ($reader, $allowance, $run): void {
                try {
                    $this->read($reader, $allowance);
                } catch (ReadingFailed $failure) {
                    if ($failure->retry && $this->attempts() < 3) {
                        throw $failure;
                    }
                }

                $run->finish($this->utilityId, $this->documentId);
            });
        });
    }

    /**
     * After the last try of a busy service: the bill is no longer being read.
     *
     * @param  Throwable|null  $exception  why the job ended
     */
    public function failed(?Throwable $exception): void
    {
        CurrentFamily::using($this->familyId, function (): void {
            resolve(ArchiveReadingRun::class)->finish($this->utilityId, $this->documentId);
        });
    }

    /**
     * The reading itself, inside the family.
     *
     * @param  DocumentReader  $reader  the model
     * @param  ReadingAllowance  $allowance  the readings left this month
     *
     * @throws ReadingFailed when the service gives no answer
     */
    private function read(DocumentReader $reader, ReadingAllowance $allowance): void
    {
        $utility = Utility::query()->find($this->utilityId);
        $document = Document::query()->with('file')->find($this->documentId);
        $file = $document?->file;

        if (! $utility instanceof Utility || ! $document instanceof Document || ! $file instanceof File) {
            return;
        }

        if (! $this->worthReading($reader, $allowance, $document, $file)) {
            $this->closeUnfinished($document, $file);

            return;
        }

        try {
            $source = ReadingSource::of($file);
        } catch (ReadingSkipped) {
            return;
        }

        try {
            // The call is recorded as the account that asked, as when it reads from a page
            $reading = $reader->consumption($source, $file->id, $this->locale, $this->userId);
        } catch (ReadingFailed $failure) {
            if ($failure->paid) {
                // Paid but of no use: the bill goes to *To check*, where the person writes it
                $this->writeOutcome($file, ConsumptionOutcome::NoConsumption);
            }

            throw $failure;
        }

        try {
            $outcome = $this->save($reading, $utility, $document);
        } catch (Throwable $error) {
            report($error);
            // Without an outcome the bill would be neither in the archive nor in *To check*
            $this->writeOutcome($file, ConsumptionOutcome::NoConsumption);

            return;
        }

        $this->writeOutcome($file, $outcome);
    }

    /**
     * Whether the bill is worth a call: the reading is on, the family has readings left, and the bill has neither the
     * consumption of the utility of the job nor a consumption reading (a second job of the same bill, or a bill
     * imported meanwhile). The row of another utility of the same bill (electricity and gas) does not count.
     *
     * @param  DocumentReader  $reader  the model
     * @param  ReadingAllowance  $allowance  the readings left this month
     * @param  Document  $document  the bill
     * @param  File  $file  its file
     */
    private function worthReading(DocumentReader $reader, ReadingAllowance $allowance, Document $document, File $file): bool
    {
        return $reader->isActive()
            && $allowance->left(CarbonImmutable::now('Europe/Rome')) > 0
            && ! $this->hasConsumption($document)
            && ! AiReading::query()->where('file_id', $file->id)->where('purpose', ReadingPurpose::Consumption)->exists();
    }

    /**
     * A call of an earlier try whose outcome was never written (the worker was stopped between the call and the
     * outcome) gets `NoConsumption`, so the bill goes to *To check* instead of being lost: without an outcome it is
     * neither in the archive (it has a consumption reading) nor in *To check* (it has no outcome). Nothing is done when
     * the bill has the consumption of the utility of the job or the latest call has an outcome.
     *
     * @param  Document  $document  the bill
     * @param  File  $file  its file
     */
    private function closeUnfinished(Document $document, File $file): void
    {
        if ($this->hasConsumption($document)) {
            return;
        }

        $latest = AiReading::query()
            ->where('file_id', $file->id)
            ->where('purpose', ReadingPurpose::Consumption)
            ->latest('id')
            ->first();

        if ($latest instanceof AiReading && $latest->outcome === null) {
            $this->writeOutcome($file, ConsumptionOutcome::NoConsumption);
        }
    }

    /**
     * Whether the bill has its row for the utility of the job.
     *
     * @param  Document  $document  the bill
     */
    private function hasConsumption(Document $document): bool
    {
        return $document->consumptions()->where('utility_id', $this->utilityId)->exists();
    }

    /**
     * Saves the consumption when it passes the check, and says what came of it for the utility of the job. A bill of
     * electricity and gas also saves, from the same answer, the supply of every other measured utility linked to it
     * that has no row of the bill yet; what comes of those is not the outcome of the call. A bill that lists supplies
     * without one of the utility of the job saves nothing for it: *No consumption found*, so it is to check.
     *
     * @param  ConsumptionReading  $reading  what the model found
     * @param  Utility  $utility  the utility of the bill
     * @param  Document  $document  the bill
     */
    private function save(ConsumptionReading $reading, Utility $utility, Document $document): ConsumptionOutcome
    {
        if (! $reading->isBill) {
            return ConsumptionOutcome::NotABill;
        }

        $found = $this->supplyOf($reading, $utility);
        $outcome = $found instanceof ConsumptionReading ? $this->saveFor($found, $utility, $document) : ConsumptionOutcome::NoConsumption;

        foreach ($this->otherUtilities($reading, $utility, $document) as $other) {
            $supply = $this->supplyOf($reading, $other);

            if (! $supply instanceof ConsumptionReading) {
                continue;
            }

            try {
                $this->saveFor($supply, $other, $document);
            } catch (Throwable $error) {
                // The outcome of the call is the one of the utility of the job, already saved
                report($error);
            }
        }

        return $outcome;
    }

    /**
     * The consumption of a utility on the bill: its own supply, or the flat fields of a bill without supplies. Null
     * when the bill lists supplies and none is the utility's (the flat fields are those of the first supply, another's).
     *
     * @param  ConsumptionReading  $reading  what the model found
     * @param  Utility  $utility  the utility
     */
    private function supplyOf(ConsumptionReading $reading, Utility $utility): ?ConsumptionReading
    {
        $found = $reading->forUtility($utility);

        return $reading->supplies !== [] && $found === $reading ? null : $found;
    }

    /**
     * The other measured utilities linked to a bill of several supplies that have no row of it yet; none for a bill
     * of one supply.
     *
     * @param  ConsumptionReading  $reading  what the model found
     * @param  Utility  $utility  the utility of the job
     * @param  Document  $document  the bill
     * @return Collection<int, Utility>
     */
    private function otherUtilities(ConsumptionReading $reading, Utility $utility, Document $document): Collection
    {
        if ($reading->supplies === []) {
            return new Collection;
        }

        return $document->utilities()
            ->whereKeyNot($utility->id)
            ->whereIn('type', UtilityType::measured())
            ->whereDoesntHave('consumptions', static fn (Builder $query): Builder => $query->where('document_id', $document->id))
            ->orderBy('utilities.id')
            ->get();
    }

    /**
     * Saves the consumption of one utility when it passes the check, and says what came of it.
     *
     * @param  ConsumptionReading  $found  the consumption of the utility on the bill
     * @param  Utility  $utility  the utility
     * @param  Document  $document  the bill
     */
    private function saveFor(ConsumptionReading $found, Utility $utility, Document $document): ConsumptionOutcome
    {
        $outcome = ConsumptionCheck::outcome($utility->type, $found->quantity, $found->unit, $found->periodStart, $found->periodEnd);
        $start = ValidDate::carbon($found->periodStart);
        $end = ValidDate::carbon($found->periodEnd);

        // The check passed, so quantity and period are there: the second half only tells PHPStan
        if ($outcome !== ConsumptionOutcome::Saved || $found->quantity === null || ! $start instanceof CarbonImmutable || ! $end instanceof CarbonImmutable) {
            return $outcome;
        }

        // Resolved here, after the call, so that nothing it needs can fail before the model is asked
        resolve(SaveConsumption::class)->handle($utility, new ConsumptionValues(
            periodStart: $start,
            periodEnd: $end,
            quantity: $found->quantity,
            amount: $found->totalAmount,
            isEstimated: $found->estimated ?? false,
            hasAdjustments: $found->adjusted ?? false,
            energyAmount: $found->energyAmount,
            f1: $found->f1,
            f2: $found->f2,
            f3: $found->f3,
        ), ConsumptionSource::Reading, $document);

        return ConsumptionOutcome::Saved;
    }

    /**
     * Writes the outcome on the row of the call just made, if it was written. The call is paid already: an error here
     * is reported, never thrown.
     *
     * @param  File  $file  the file read
     * @param  ConsumptionOutcome  $outcome  what came of the reading
     */
    private function writeOutcome(File $file, ConsumptionOutcome $outcome): void
    {
        try {
            $this->outcomeRow($file)?->forceFill(['outcome' => $outcome])->save();
        } catch (Throwable $error) {
            report($error);
        }
    }

    /**
     * The row of the call just made: the latest consumption call of the file without an outcome.
     *
     * @param  File  $file  the file read
     */
    private function outcomeRow(File $file): ?AiReading
    {
        return AiReading::query()
            ->where('file_id', $file->id)
            ->where('purpose', ReadingPurpose::Consumption)
            ->whereNull('outcome')
            ->latest('id')
            ->first();
    }
}
