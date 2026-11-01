<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reminders\ExpireCards;
use App\Actions\Reminders\SendMorningNotices;
use App\Actions\Reminders\SyncReminders;
use App\Models\Family;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * The round of every morning (`routes/console.php`): for each family, the cards past their date expire, the
 * automatic reminders are synced and the notices of the day go out.
 */
#[Description('Expire the cards, sync the reminders and send the notices of the day')]
#[Signature('reminders:run {--family= : Only this family (its id)} {--date= : Run as if today were this day (aaaa-mm-gg)}')]
final class RunReminders extends Command
{
    /**
     * Runs the round. A family that fails is reported and skipped, so it does not stop the others; the command
     * then ends with a failure.
     *
     * @param  ExpireCards  $expire  Moves the cards past their date to expired.
     * @param  SyncReminders  $sync  Aligns the automatic reminders with the cards.
     * @param  SendMorningNotices  $send  Sends the notices of the day.
     */
    public function handle(ExpireCards $expire, SyncReminders $sync, SendMorningNotices $send): int
    {
        $date = $this->option('date');
        $today = is_string($date) && $date !== ''
            ? CarbonImmutable::parse($date, 'Europe/Rome')->startOfDay()
            : CarbonImmutable::now('Europe/Rome')->startOfDay();
        $only = $this->option('family');
        $created = 0;
        $failed = 0;

        $families = Family::query()->when(is_numeric($only), fn ($query) => $query->whereKey((int) $only))->orderBy('id')->get(['id']);

        foreach ($families as $family) {
            try {
                $created += CurrentFamily::using($family->id, function () use ($expire, $sync, $send, $today): int {
                    $expire->handle($today);
                    $sync->family($today);

                    return $send->handle($today);
                });
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->info("Notices created: {$created}");

        if ($failed > 0) {
            $this->error("Families that failed: {$failed}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
