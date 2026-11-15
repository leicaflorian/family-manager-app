<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * A rolling deploy briefly runs the old and the new container together, so two schedulers fire the same commands:
 * each scheduled command must not overlap itself nor run on two servers at once.
 */
it('keeps every scheduled command safe during a rolling deploy', function (): void {
    putenv('BACKUP_S3_BUCKET=family-manager');
    $_ENV['BACKUP_S3_BUCKET'] = 'family-manager';
    $this->refreshApplication();

    try {
        $events = collect(resolve(Schedule::class)->events());

        foreach (['loans:refresh', 'reminders:run', 'model:prune', 'files:backup'] as $command) {
            $event = $events->first(fn (Event $e): bool => str_contains((string) $e->command, $command));

            expect($event)->not->toBeNull("{$command} is scheduled")
                ->and($event->withoutOverlapping)->toBeTrue("{$command} without overlapping")
                ->and($event->onOneServer)->toBeTrue("{$command} on one server")
                // A container killed in the middle of a run must not block the next night (the default is 24 hours)
                ->and($event->expiresAt)->toBe(120, "{$command} lock expires after 2 hours");
        }
    } finally {
        putenv('BACKUP_S3_BUCKET');
        unset($_ENV['BACKUP_S3_BUCKET']);
    }
});
