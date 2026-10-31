<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The instalments that the calendar makes paid at midnight, in the families' time zone
Schedule::command('loans:refresh')->dailyAt('00:05')->timezone('Europe/Rome');
