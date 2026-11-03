<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Reading\ReadStoredFile;
use App\Enums\ReadingPurpose;
use App\Enums\ReadingStatus;
use App\Exceptions\Reading\ReadingFailed;
use App\Models\File;
use App\Models\User;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * The reading of one file, in the queue: one job per file at a time. It runs outside a request, so it works in
 * the family of the file and in the language of who uploaded it. A service that is busy or cannot be reached is
 * tried again after 10, 30 and 90 seconds; any other failure ends the reading at once.
 *
 * A try lasts at most 80 seconds: less than the 90 of `retry_after` of the queue, after which another worker would
 * start the same reading (and pay it twice), more than the 60 of the call to the service. `DeleteWhenMissingModels`
 * drops a job whose models are gone; the file travels as an id, so `handle()` checks it as well.
 */
#[Backoff([10, 30, 90])]
#[DeleteWhenMissingModels]
#[Timeout(80)]
#[Tries(3)]
final class ReadFile implements ShouldBeUnique, ShouldQueue
{
    use Localizable;
    use Queueable;

    /**
     * @param  int  $fileId  the file to read
     * @param  int  $familyId  the family of the file
     * @param  int|null  $userId  who uploaded it; null for the files that came by mail
     * @param  string  $locale  the language of who uploaded it (`it` or `en`)
     * @param  ReadingPurpose  $purpose  what the reading is for
     */
    public function __construct(
        public readonly int $fileId,
        public readonly int $familyId,
        public readonly ?int $userId,
        public readonly string $locale,
        public readonly ReadingPurpose $purpose,
    ) {}

    /**
     * The key that keeps a second job of the same file out of the queue.
     */
    public function uniqueId(): string
    {
        return (string) $this->fileId;
    }

    /**
     * Reads the file. A file deleted while it waited, or no longer `queued` or `reading`, ends the job without a word.
     *
     * @param  ReadStoredFile  $read  the reading itself
     *
     * @throws ReadingFailed when the service is busy or cannot be reached, so that the queue tries again
     */
    public function handle(ReadStoredFile $read): void
    {
        $this->inFamilyAndLanguage(function () use ($read): void {
            $file = File::query()->find($this->fileId);

            // Gone, or no longer waiting: a try started again after a timeout must not read (and pay) twice
            if (! $file instanceof File || ! in_array($file->reading_status, [ReadingStatus::Queued, ReadingStatus::Reading], true)) {
                return;
            }

            $user = $this->userId === null ? null : User::query()->find($this->userId);

            try {
                $read->handle($file, $user, $this->locale, $this->purpose, CarbonImmutable::now('Europe/Rome'));
            } catch (ReadingFailed $failure) {
                if ($failure->retry) {
                    throw $failure;
                }

                // Asking again gets the same answer: the job ends here, and `failed()` writes why
                $this->fail($failure);
            }
        });
    }

    /**
     * Marks the file `failed`, with the message of the failure, after the last try or a failure that does not pass.
     *
     * @param  Throwable|null  $exception  why the reading ended
     */
    public function failed(?Throwable $exception): void
    {
        $this->inFamilyAndLanguage(function () use ($exception): void {
            File::query()->find($this->fileId)?->markReading(
                ReadingStatus::Failed,
                $exception instanceof ReadingFailed ? __($exception->key) : __('The file could not be read.'),
            );
        });
    }

    /**
     * Runs some work in the family of the file and in the language of who uploaded it.
     *
     * @param  callable(): void  $work  the work
     */
    private function inFamilyAndLanguage(callable $work): void
    {
        $this->withLocale($this->locale, function () use ($work): void {
            CurrentFamily::using($this->familyId, $work);
        });
    }
}
