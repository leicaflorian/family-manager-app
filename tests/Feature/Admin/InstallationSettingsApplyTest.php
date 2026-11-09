<?php

declare(strict_types=1);

use App\Enums\InstallationSetting;
use App\Enums\ReadingPurpose;
use App\Models\AiReading;
use App\Models\Family;
use App\Services\Reading\ReadingAllowance;
use App\Support\CurrentFamily;
use App\Support\InstallationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;

it('reads the settings of the moment in a queued job', function (): void {
    config(['document_reader.model' => 'from-env']);
    resolve(InstallationSettings::class)->set(InstallationSetting::Model, 'changed-after-boot');

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn([]);

    Event::dispatch(new JobProcessing('database', $job));

    expect(config('document_reader.model'))->toBe('changed-after-boot');
});

it('applies the settings on a web request', function (): void {
    resolve(InstallationSettings::class)->set(InstallationSetting::Model, 'web-model');
    config(['document_reader.model' => 'from-env']);

    $this->get('/login');

    expect(config('document_reader.model'))->toBe('web-model');
});

it('uses the stored times in the schedule', function (): void {
    resolve(InstallationSettings::class)->set(InstallationSetting::RemindersTime, '07:00');
    resolve(InstallationSettings::class)->set(InstallationSetting::LoansTime, '01:30');
    $before = count(Schedule::events());

    // The console routes build the schedule: load them again to read the stored times
    require base_path('routes/console.php');
    $events = collect(Schedule::events())->slice($before);

    expect($events->first(fn ($e): bool => str_contains((string) $e->command, 'reminders:run'))?->expression)->toBe('0 7 * * *')
        ->and($events->first(fn ($e): bool => str_contains((string) $e->command, 'loans:refresh'))?->expression)->toBe('30 1 * * *');
});

it('takes the monthly limit from the settings', function (): void {
    config(['document_reader.monthly_limit' => 1000]);
    resolve(InstallationSettings::class)->set(InstallationSetting::MonthlyLimit, '3');
    $family = Family::factory()->create();
    AiReading::factory()->for($family)->count(3)->create(['purpose' => ReadingPurpose::Document]);

    $left = CurrentFamily::using($family->id, fn (): int => resolve(ReadingAllowance::class)->left(CarbonImmutable::now()));

    expect($left)->toBe(0);
});
