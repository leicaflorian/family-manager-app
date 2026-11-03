<?php

declare(strict_types=1);

namespace App\Actions\Reading;

use App\Contracts\DocumentReader;
use App\Enums\ReadingPurpose;
use App\Enums\ReadingStatus;
use App\Exceptions\Reading\ReadingSkipped;
use App\Jobs\ReadFile;
use App\Models\File;
use App\Models\User;
use App\Services\Reading\ReadingAllowance;
use App\Services\Reading\ReadingSource;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Traits\Localizable;

/**
 * Puts a file in the queue of the readings, once: a file already waiting or being read is left as it is, and a
 * file already read is read again only when asked. Without the service, or with the readings of the month used
 * up, the file is `off`; a file the model does not read is `skipped`. The messages are in the language of who
 * uploaded it.
 */
final readonly class QueueReading
{
    use Localizable;

    /**
     * @param  DocumentReader  $reader  says whether the reading is on
     * @param  ReadingAllowance  $allowance  the readings left this month
     */
    public function __construct(private DocumentReader $reader, private ReadingAllowance $allowance) {}

    /**
     * Queues the reading of a file.
     *
     * @param  File  $file  the file to read
     * @param  User|null  $user  who uploaded it; null for the files that came by mail
     * @param  string  $locale  the language of who uploaded it (`it` or `en`)
     * @param  bool  $again  true to read again a file already read (*Leggi di nuovo*, *Compila dal file*)
     * @return ReadingStatus where the reading of the file stands now
     */
    public function handle(File $file, ?User $user, string $locale, bool $again = false): ReadingStatus
    {
        $status = $file->reading_status;

        if ($status === ReadingStatus::Queued || $status === ReadingStatus::Reading || ($status === ReadingStatus::Done && ! $again)) {
            return $status;
        }

        return $this->withLocale($locale, fn (): ReadingStatus => CurrentFamily::using(
            $file->family_id,
            fn (): ReadingStatus => $this->queue($file, $user, $locale),
        ));
    }

    /**
     * Checks the service, the file and the limit, then sends the job; inside the family of the file.
     *
     * @param  File  $file  the file to read
     * @param  User|null  $user  who uploaded it
     * @param  string  $locale  the language of who uploaded it
     */
    private function queue(File $file, ?User $user, string $locale): ReadingStatus
    {
        if (! $this->reader->isActive()) {
            return $this->stop($file, ReadingStatus::Off, __('Automatic reading is not on.'));
        }

        try {
            ReadingSource::check($file);
        } catch (ReadingSkipped $skipped) {
            return $this->stop($file, ReadingStatus::Skipped, $skipped->getMessage());
        }

        $now = CarbonImmutable::now('Europe/Rome');

        if ($this->allowance->left($now) === 0) {
            return $this->stop($file, ReadingStatus::Off, $this->allowance->usedUpMessage($now, $locale));
        }

        $file->markReading(ReadingStatus::Queued);
        dispatch(new ReadFile($file->id, $file->family_id, $user?->id, $locale, ReadingPurpose::Document))->afterCommit();

        return ReadingStatus::Queued;
    }

    /**
     * Leaves the file unread, with the reason.
     *
     * @param  File  $file  the file
     * @param  ReadingStatus  $status  `off` or `skipped`
     * @param  string  $error  the reason, translated
     */
    private function stop(File $file, ReadingStatus $status, string $error): ReadingStatus
    {
        $file->markReading($status, $error);

        return $status;
    }
}
