<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reminders\ExpireCards;
use App\Actions\Reminders\SendMorningNotices;
use App\Actions\Reminders\SyncReminders;
use App\Console\Concerns\RunsPerFamily;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * The round of every morning (`routes/console.php`): for each family, the cards past their date expire, the
 * automatic reminders are synced and the notices of the day go out.
 */
#[Description('Expire the cards, sync the reminders and send the notices of the day')]
#[Signature('reminders:run {--family= : Only this family (its id)} {--date= : Run as if today were this day (aaaa-mm-gg)}')]
final class RunReminders extends Command
{
    use RunsPerFamily;

    /** How long a run may hold the lock, in seconds: more than any round takes, so a crash cannot block the next day. */
    private const int LOCK_SECONDS = 1800;

    /**
     * Runs the round. A family that fails is reported and skipped, so it does not stop the others; the command
     * then ends with a failure. An option that is not valid, or a round already going (the scheduled one and a manual
     * one overlapping would send the same mails twice), stops it before anything runs.
     *
     * @param  ExpireCards  $expire  Moves the cards past their date to expired.
     * @param  SyncReminders  $sync  Aligns the automatic reminders with the cards.
     * @param  SendMorningNotices  $send  Sends the notices of the day.
     */
    public function handle(ExpireCards $expire, SyncReminders $sync, SendMorningNotices $send): int
    {
        $date = $this->textOption('date');

        if ($date !== '' && ! $this->isDay($date)) {
            $this->error('--date must be a day like 2026-11-25.');

            return self::FAILURE;
        }

        if (! $this->hasValidFamilyOption()) {
            return self::FAILURE;
        }

        $lock = Cache::lock('reminders:run', self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->warn('The round is already running: nothing done.');

            return self::SUCCESS;
        }

        try {
            return $this->round(
                $date !== '' ? CarbonImmutable::parse($date, 'Europe/Rome')->startOfDay() : CarbonImmutable::now('Europe/Rome')->startOfDay(),
                $expire,
                $sync,
                $send,
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * Whether a text is a real day written as `aaaa-mm-gg`.
     *
     * @param  string  $value  the text
     */
    private function isDay(string $value): bool
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value)?->format('Y-m-d') === $value;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * The round itself, for every family or the one chosen.
     *
     * @param  CarbonImmutable  $today  the day to run for, in Rome
     * @param  ExpireCards  $expire  Moves the cards past their date to expired.
     * @param  SyncReminders  $sync  Aligns the automatic reminders with the cards.
     * @param  SendMorningNotices  $send  Sends the notices of the day.
     */
    private function round(CarbonImmutable $today, ExpireCards $expire, SyncReminders $sync, SendMorningNotices $send): int
    {
        return $this->runPerFamily('Notices created', static function () use ($expire, $sync, $send, $today): int {
            $expire->handle($today);
            $sync->family($today);

            return $send->handle($today);
        });
    }
}
