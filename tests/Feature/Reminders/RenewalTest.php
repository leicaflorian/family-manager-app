<?php

declare(strict_types=1);

use App\Actions\Budget\RecordPayment;
use App\Actions\Reminders\MarkReminderDone;
use App\Actions\Reminders\ReopenReminder;
use App\Enums\IntervalUnit;
use App\Enums\ReminderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Family;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Deadlines;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-01 10:00', 'Europe/Rome'));
    $this->family = Family::factory()->create();
    $this->actingAs(User::factory()->for($this->family)->admin()->create());
    $this->subscription = Subscription::factory()->for($this->family)->create(['name' => 'Fatture in Cloud', 'renewal_date' => '2026-10-03']);
    $this->fee = agendaPlanned($this->subscription, 'Fatture in Cloud', 120.0, '2025-10-03', ['interval_unit' => IntervalUnit::Year]);
});

afterEach(fn () => Date::setTestNow());

/**
 * The automatic reminders of the renewal of the subscription, oldest first.
 *
 * @return list<Reminder>
 */
function renewalReminders(): array
{
    return Reminder::query()->where('source_field', 'subscriptions.renewal_date')->oldest('due_date')->get()->all();
}

test('done moves the renewal by a year', function (): void {
    [$reminder] = renewalReminders();

    resolve(MarkReminderDone::class)->handle($reminder);

    $reminders = renewalReminders();

    expect($this->subscription->fresh()->renewal_date->toDateString())->toBe('2027-10-03')
        ->and($reminders)->toHaveCount(2)
        ->and($reminders[0]->due_date->toDateString())->toBe('2026-10-03')
        ->and($reminders[0]->status)->toBe(ReminderStatus::Done)
        ->and($reminders[1]->due_date->toDateString())->toBe('2027-10-03')
        ->and($reminders[1]->status)->toBe(ReminderStatus::Open);
});

test('reopen brings it back', function (): void {
    [$reminder] = renewalReminders();
    resolve(MarkReminderDone::class)->handle($reminder);

    resolve(ReopenReminder::class)->handle($reminder->fresh());

    $reminders = renewalReminders();

    expect($this->subscription->fresh()->renewal_date->toDateString())->toBe('2026-10-03')
        ->and($reminders)->toHaveCount(1)
        ->and($reminders[0]->due_date->toDateString())->toBe('2026-10-03')
        ->and($reminders[0]->status)->toBe(ReminderStatus::Open);
});

test('reopen leaves a renewal changed by hand where it is', function (): void {
    [$reminder] = renewalReminders();
    resolve(MarkReminderDone::class)->handle($reminder);
    $this->subscription->fresh()->update(['renewal_date' => '2027-11-15']);

    resolve(ReopenReminder::class)->handle($reminder->fresh());

    expect($this->subscription->fresh()->renewal_date->toDateString())->toBe('2027-11-15')
        ->and($reminder->fresh()->status)->toBe(ReminderStatus::Open);
});

test('a cancelled subscription is refused', function (): void {
    App::setLocale('it');
    [$reminder] = renewalReminders();
    // Without events: a cancelled subscription saved the normal way loses its reminder, and this is the one left behind.
    Subscription::query()->whereKey($this->subscription->id)->update(['status' => SubscriptionStatus::Cancelled->value]);

    try {
        resolve(MarkReminderDone::class)->handle($reminder);
        $this->fail('The renewal of a cancelled subscription must be refused.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['status' => [
            "Il rinnovo di Fatture in Cloud non è stato spostato: l'abbonamento non è più attivo o la sua data è cambiata. Controlla la scheda dell'abbonamento.",
        ]]);
    }

    expect($reminder->fresh()->status)->toBe(ReminderStatus::Open)
        ->and($this->subscription->fresh()->renewal_date->toDateString())->toBe('2026-10-03');
});

test('a renewal changed by hand since the reminder is refused', function (): void {
    [$reminder] = renewalReminders();
    Subscription::query()->whereKey($this->subscription->id)->update(['renewal_date' => '2026-12-01']);

    expect(fn () => resolve(MarkReminderDone::class)->handle($reminder))->toThrow(ValidationException::class)
        ->and($this->subscription->fresh()->renewal_date->toDateString())->toBe('2026-12-01');
});

test('paying the fee confirms the renewal', function (): void {
    $deadline = resolve(Deadlines::class)->find($this->fee, CarbonImmutable::parse('2026-10-03'));

    resolve(RecordPayment::class)->handle($deadline, CarbonImmutable::parse('2026-10-01'), 120.0, null, null);

    $reminders = renewalReminders();

    expect($this->subscription->fresh()->renewal_date->toDateString())->toBe('2027-10-03')
        ->and($reminders)->toHaveCount(2)
        ->and($reminders[0]->status)->toBe(ReminderStatus::Done)
        ->and($reminders[1]->due_date->toDateString())->toBe('2027-10-03')
        ->and($reminders[1]->status)->toBe(ReminderStatus::Open);
});

test('a payment of an add-on fee does not renew the subscription', function (): void {
    $addOn = agendaPlanned($this->subscription, 'Extra', 5.0, '2026-10-03', ['interval_unit' => IntervalUnit::Month]);
    $deadline = resolve(Deadlines::class)->find($addOn, CarbonImmutable::parse('2026-10-03'));

    resolve(RecordPayment::class)->handle($deadline, CarbonImmutable::parse('2026-10-01'), 5.0, null, null);

    expect($this->subscription->fresh()->renewal_date->toDateString())->toBe('2026-10-03');
});

test('a payment saved again does not move the renewal twice', function (): void {
    $deadline = resolve(Deadlines::class)->find($this->fee, CarbonImmutable::parse('2026-10-03'));
    $expense = resolve(RecordPayment::class)->handle($deadline, CarbonImmutable::parse('2026-10-01'), 120.0, null, null);

    $expense->update(['date' => '2026-10-02']);

    expect($this->subscription->fresh()->renewal_date->toDateString())->toBe('2027-10-03');
});
