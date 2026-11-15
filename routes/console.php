<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\MailInboxCall;
use App\Models\ReminderNotice;
use App\Models\ServerError;
use App\Support\InstallationSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A rolling deploy briefly runs two schedulers (old and new container): every command is withoutOverlapping()->onOneServer(),
// which needs a shared cache (redis in production).

// The instalments that the calendar makes paid at midnight, in the families' time zone
Schedule::command('loans:refresh')->dailyAt(resolve(InstallationSettings::class)->loansTime())->timezone('Europe/Rome')->withoutOverlapping()->onOneServer();

// Cards that expire, reminders in line with them, and the notices and the email of the morning
Schedule::command('reminders:run')->dailyAt(resolve(InstallationSettings::class)->remindersTime())->timezone('Europe/Rome')->withoutOverlapping()->onOneServer();
Schedule::command('model:prune', ['--model' => [ReminderNotice::class, MailInboxCall::class, ServerError::class, LinkedDevice::class, Family::class]])->daily()->withoutOverlapping()->onOneServer();

// The nightly copy of the family files to the Space, only where a bucket is configured
if (filled(config('filesystems.disks.backup.bucket'))) {
    Schedule::command('files:backup')->dailyAt('02:30')->timezone('Europe/Rome')->withoutOverlapping()->onOneServer();
}
