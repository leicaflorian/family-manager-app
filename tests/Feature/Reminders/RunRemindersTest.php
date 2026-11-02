<?php

declare(strict_types=1);

use App\Mail\MorningNoticesMail;
use App\Models\Document;
use App\Models\Family;
use App\Models\Person;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Date::setTestNow(CarbonImmutable::parse('2026-11-25 10:00', 'Europe/Rome'));
    $this->family = Family::factory()->create();
    CurrentFamily::set($this->family->id);
    $this->marcoUser = User::factory()->for($this->family)->admin()->create(['email' => 'marco@example.com']);
    $this->lauraUser = User::factory()->for($this->family)->create(['email' => 'laura@example.com']);
    $this->marco = Person::factory()->for($this->family)->create(['first_name' => 'Marco', 'user_id' => $this->marcoUser->id]);
    Person::factory()->for($this->family)->create(['first_name' => 'Laura', 'user_id' => $this->lauraUser->id]);
    ReminderGroup::factory()->for($this->family)->create([
        'name' => 'Veicoli',
        'deadline_types' => ['vehicles.inspection_due_date'],
        'notifications' => [['name' => '45 giorni', 'days' => 45, 'timing' => 'before', 'channels' => 'both', 'recipients' => ['all']]],
    ]);
    $this->panda = Vehicle::factory()->for($this->family)->create(['name' => 'Panda', 'inspection_due_date' => '2027-01-09']);
});

afterEach(function (): void {
    Date::setTestNow();
    CurrentFamily::clear();
});

test('the morning of 25/11/2026', function (): void {
    $this->artisan('reminders:run')->assertSuccessful();

    expect(ReminderNotice::query()->count())->toBe(2)
        ->and(ReminderNotice::query()->pluck('user_id')->sort()->values()->all())->toBe([$this->marcoUser->id, $this->lauraUser->id])
        ->and(ReminderNotice::query()->first()->due_date->toDateString())->toBe('2027-01-09')
        ->and(ReminderNotice::query()->first()->offset)->toBe(-45);
    Mail::assertQueued(MorningNoticesMail::class, 2);
    Mail::assertQueued(MorningNoticesMail::class, fn (MorningNoticesMail $mail): bool => $mail->hasTo('marco@example.com') && count($mail->items) === 1 && $mail->items[0]->subject === 'Panda');
    Mail::assertQueued(MorningNoticesMail::class, fn (MorningNoticesMail $mail): bool => $mail->hasTo('laura@example.com'));
});

test('twice in a day sends nothing new', function (): void {
    $this->artisan('reminders:run')->assertSuccessful();
    $this->artisan('reminders:run')->assertSuccessful();

    expect(ReminderNotice::query()->count())->toBe(2);
    Mail::assertQueued(MorningNoticesMail::class, 2);
});

test('three days off sends only the latest', function (): void {
    ReminderGroup::query()->update(['notifications' => [
        ['name' => 'a', 'days' => 50, 'timing' => 'before', 'channels' => 'app', 'recipients' => ['all']],
        ['name' => 'b', 'days' => 47, 'timing' => 'before', 'channels' => 'app', 'recipients' => ['all']],
        ['name' => 'c', 'days' => 45, 'timing' => 'before', 'channels' => 'app', 'recipients' => ['all']],
    ]]);

    $this->artisan('reminders:run')->assertSuccessful();

    expect(ReminderNotice::query()->pluck('offset')->unique()->all())->toBe([-45])
        ->and(ReminderNotice::query()->count())->toBe(2)
        ->and(Reminder::query()->where('source_field', 'vehicles.inspection_due_date')->sole()->notifications_sent)
        ->toBe(['2027-01-09:-50', '2027-01-09:-47', '2027-01-09:-45']);
    Mail::assertNothingQueued();
});

test('a moved date starts the notices again', function (): void {
    $this->artisan('reminders:run')->assertSuccessful();
    $this->panda->update(['inspection_due_date' => '2027-01-10']);
    Date::setTestNow(CarbonImmutable::parse('2026-11-26 10:00', 'Europe/Rome'));

    $this->artisan('reminders:run')->assertSuccessful();

    // The old reminder went with its notices when its date moved; the new one has its own
    expect(ReminderNotice::query()->whereDate('due_date', '2027-01-10')->count())->toBe(2);
    Mail::assertQueued(MorningNoticesMail::class, 4);
});

test('a family that fails does not stop the others', function (): void {
    Exceptions::fake();
    $second = Family::factory()->create();
    CurrentFamily::using($second->id, function () use ($second): void {
        $user = User::factory()->for($second)->admin()->create();
        Person::factory()->for($second)->create(['user_id' => $user->id]);
        // A week before 02/12/2026 is today, for the default rules of a family without groups
        Document::factory()->for($second)->create(['title' => 'Passport', 'expiry_date' => '2026-12-02']);
    });
    $first = $this->family->id;
    ReminderNotice::creating(function () use ($first): void {
        if (CurrentFamily::id() === $first) {
            throw new RuntimeException('boom');
        }
    });

    $this->artisan('reminders:run')->assertFailed();

    expect(ReminderNotice::query()->withoutGlobalScopes()->where('family_id', $second->id)->count())->toBe(1)
        ->and(ReminderNotice::query()->withoutGlobalScopes()->where('family_id', $first)->count())->toBe(0);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'boom');
});

test('two reminders for the same account make one mail with both', function (): void {
    Document::factory()->for($this->family)->create(['title' => 'Passport', 'expiry_date' => '2026-12-02']);

    $this->artisan('reminders:run')->assertSuccessful();

    Mail::assertQueued(MorningNoticesMail::class, 2);
    Mail::assertQueued(MorningNoticesMail::class, fn (MorningNoticesMail $mail): bool => $mail->hasTo('marco@example.com') && count($mail->items) === 2);
});

test('a reminder that fails does not lose the mail of the ones before it', function (): void {
    Exceptions::fake();
    Document::factory()->for($this->family)->create(['title' => 'Passport', 'expiry_date' => '2026-12-02']);
    // Sync runs first, so both reminders exist; the last one is the one that breaks.
    ReminderNotice::creating(function (ReminderNotice $notice): void {
        if ($notice->reminder_id === Reminder::query()->max('id')) {
            throw new RuntimeException('boom');
        }
    });

    $this->artisan('reminders:run')->assertFailed();

    expect(ReminderNotice::query()->count())->toBe(2);
    Mail::assertQueued(MorningNoticesMail::class, 2);
    Mail::assertQueued(MorningNoticesMail::class, fn (MorningNoticesMail $mail): bool => $mail->hasTo('marco@example.com') && count($mail->items) === 1);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'boom');
});

test('nobody with an account: nothing sent and nothing marked', function (): void {
    User::query()->delete();

    $this->artisan('reminders:run')->assertSuccessful();

    expect(ReminderNotice::query()->count())->toBe(0)
        ->and(Reminder::query()->where('source_field', 'vehicles.inspection_due_date')->sole()->notifications_sent)->toBeNull();
    Mail::assertNothingQueued();
});

test('the midnight of Rome', function (): void {
    // 23:30 UTC of the 24th is 00:30 of the 25th in Rome
    Date::setTestNow(CarbonImmutable::parse('2026-11-24 23:30', 'UTC'));

    $this->artisan('reminders:run')->assertSuccessful();

    expect(ReminderNotice::query()->count())->toBe(2);
});

test('--family and --date', function (): void {
    $other = Family::factory()->create();
    $otherUser = User::factory()->for($other)->admin()->create();
    $this->artisan('reminders:run', ['--family' => $other->id, '--date' => '2026-11-25'])->assertSuccessful();
    expect(ReminderNotice::query()->count())->toBe(0);

    $this->artisan('reminders:run', ['--family' => $this->family->id, '--date' => '2026-11-24'])->assertSuccessful();
    expect(ReminderNotice::query()->count())->toBe(0);

    $this->artisan('reminders:run', ['--family' => $this->family->id, '--date' => '2026-11-25'])->assertSuccessful();
    expect(ReminderNotice::query()->count())->toBe(2)
        ->and($otherUser->id)->not->toBe($this->marcoUser->id);
});

test('a --family that is not an id, or a bad --date, fails with a message and runs nothing', function (): void {
    $this->artisan('reminders:run', ['--family' => 'abc'])->expectsOutputToContain('--family')->assertFailed();
    $this->artisan('reminders:run', ['--family' => '0'])->expectsOutputToContain('--family')->assertFailed();
    $this->artisan('reminders:run', ['--date' => '25/11/2026'])->expectsOutputToContain('--date')->assertFailed();
    $this->artisan('reminders:run', ['--date' => '2026-13-45'])->expectsOutputToContain('--date')->assertFailed();

    expect(ReminderNotice::query()->count())->toBe(0);
});

test('a run that finds another one going stops with a message and sends nothing', function (): void {
    $lock = Cache::lock('reminders:run', 60);
    expect($lock->get())->toBeTrue();

    $this->artisan('reminders:run')->expectsOutputToContain('already running')->assertSuccessful();
    expect(ReminderNotice::query()->count())->toBe(0);

    $lock->release();
    $this->artisan('reminders:run')->assertSuccessful();
    expect(ReminderNotice::query()->count())->toBe(2);
});

test('the lock is let go after a run, also one that fails', function (): void {
    $this->artisan('reminders:run')->assertSuccessful();

    $lock = Cache::lock('reminders:run', 60);
    expect($lock->get())->toBeTrue();
    $lock->release();
});
