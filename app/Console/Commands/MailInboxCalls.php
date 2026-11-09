<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Admin\MailCalls;
use App\Enums\CallOutcome;
use App\Models\Family;
use App\Models\MailInboxCall;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * The log of the calls of Mailgun for the owner of the app (`mail_inbox_calls`): when, how it ended, the answer, the
 * family, and how many files came and were saved. It never prints the text of a mail, the sender or the payload.
 */
#[Description('Show the latest calls of Mailgun and how they were answered')]
#[Signature('mail-inbox:calls {--family= : Only the calls of this family (its id)} {--outcome= : Only the calls with this outcome} {--since= : Only the calls from this day on (yyyy-mm-dd)}')]
final class MailInboxCalls extends Command
{
    /** How many calls are shown, the newest first. */
    private const int LIMIT = 100;

    /**
     * Prints the table of the calls that match the options.
     *
     * @return int 0; 1 when an option is not valid
     */
    public function handle(): int
    {
        $outcome = $this->option('outcome');
        $since = $this->option('since');
        $family = $this->option('family');

        if (is_string($outcome) && CallOutcome::tryFrom($outcome) === null) {
            $this->error("Unknown outcome: {$outcome}. Choose one of: ".implode(', ', array_column(CallOutcome::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $from = null;

        if (is_string($since)) {
            try {
                $from = CarbonImmutable::parse($since, 'Europe/Rome')->startOfDay();
            } catch (Throwable) {
                $this->error("Not a date: {$since}. Use yyyy-mm-dd.");

                return self::FAILURE;
            }
        }

        $calls = MailCalls::query(
            is_string($family) ? (int) $family : null,
            is_string($outcome) ? CallOutcome::from($outcome) : null,
            $from,
        )->limit(self::LIMIT)->get();

        if ($calls->isEmpty()) {
            $this->info('No calls.');

            return self::SUCCESS;
        }

        $names = Family::query()->whereIn('id', $calls->pluck('family_id')->filter())->pluck('name', 'id');

        $this->table(
            ['Date', 'Outcome', 'Status', 'Family', 'Files', 'Saved'],
            $calls->map(static fn (MailInboxCall $call): array => [
                $call->created_at?->setTimezone('Europe/Rome')->format('d/m/Y H:i') ?? '-',
                $call->outcome->value,
                $call->http_status,
                $call->family_id === null ? '-' : ($names->get($call->family_id) ?? '#'.$call->family_id),
                $call->attachments,
                $call->saved,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
