<?php

declare(strict_types=1);

use App\Models\LinkedDevice;
use App\Models\MailInboxCall;
use App\Models\ReminderNotice;
use App\Support\InstallationSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The instalments that the calendar makes paid at midnight, in the families' time zone
Schedule::command('loans:refresh')->dailyAt(resolve(InstallationSettings::class)->loansTime())->timezone('Europe/Rome');

// Cards that expire, reminders in line with them, and the notices and the email of the morning
Schedule::command('reminders:run')->dailyAt(resolve(InstallationSettings::class)->remindersTime())->timezone('Europe/Rome')->withoutOverlapping()->onOneServer();
Schedule::command('model:prune', ['--model' => [ReminderNotice::class, MailInboxCall::class, LinkedDevice::class]])->daily();
