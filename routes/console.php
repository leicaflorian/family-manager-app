<?php

declare(strict_types=1);

use App\Models\BankTransaction;
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
// which needs a shared cache (redis in production). The lock expires after 120 minutes, not the default 24 hours, so a
// container killed in the middle of a run does not block the next night.

// The instalments that the calendar makes paid at midnight, in the families' time zone
Schedule::command('loans:refresh')->dailyAt(resolve(InstallationSettings::class)->loansTime())->timezone('Europe/Rome')->withoutOverlapping(120)->onOneServer();

// Cards that expire, reminders in line with them, and the notices and the email of the morning
Schedule::command('reminders:run')->dailyAt(resolve(InstallationSettings::class)->remindersTime())->timezone('Europe/Rome')->withoutOverlapping(120)->onOneServer();
Schedule::command('model:prune', ['--model' => [ReminderNotice::class, MailInboxCall::class, ServerError::class, LinkedDevice::class, BankTransaction::class, Family::class]])->daily()->withoutOverlapping(120)->onOneServer();

// The nightly copy of the family files to the Space, only where a bucket is configured
if (filled(config('filesystems.disks.backup.bucket'))) {
    Schedule::command('files:backup')->dailyAt('02:30')->timezone('Europe/Rome')->withoutOverlapping(120)->onOneServer();
}
