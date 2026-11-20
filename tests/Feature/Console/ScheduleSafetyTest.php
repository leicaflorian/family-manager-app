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

it('reads the banks three times a day, safely, only with a provider', function (): void {
    putenv('BANK_FEED_PROVIDER=fake');
    $_ENV['BANK_FEED_PROVIDER'] = 'fake';
    $this->refreshApplication();

    try {
        $events = collect(resolve(Schedule::class)->events())->filter(fn (Event $e): bool => str_contains((string) $e->command, 'banks:sync'));

        expect($events)->toHaveCount(3)
            ->and($events->map(fn (Event $e): string => $e->expression)->sort()->values()->all())->toBe(['0 13 * * *', '0 19 * * *', '0 7 * * *']);

        foreach ($events as $event) {
            expect($event->withoutOverlapping)->toBeTrue()
                ->and($event->onOneServer)->toBeTrue()
                ->and($event->expiresAt)->toBe(120)
                ->and($event->timezone)->toBe('Europe/Rome');
        }
    } finally {
        putenv('BANK_FEED_PROVIDER');
        unset($_ENV['BANK_FEED_PROVIDER']);
    }
});

it('does not read the banks without a provider', function (): void {
    expect(collect(resolve(Schedule::class)->events())->filter(fn (Event $e): bool => str_contains((string) $e->command, 'banks:sync')))->toHaveCount(0);
});
