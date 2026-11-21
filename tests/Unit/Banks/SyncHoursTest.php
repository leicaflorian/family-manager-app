<?php

declare(strict_types=1);

use App\Support\Banks\SyncHours;

test('the hours the banks are read come from the times of the config', function (): void {
    config(['bank_feed.sync_times' => ['07:00', '13:30', '19:00']]);

    expect(SyncHours::all())->toBe([7, 13, 19]);
});
