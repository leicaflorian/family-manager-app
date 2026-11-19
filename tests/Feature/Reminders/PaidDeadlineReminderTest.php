<?php

declare(strict_types=1);

use App\Actions\Budget\RecordPayment;
use App\Enums\IntervalUnit;
use App\Enums\ReminderStatus;
use App\Models\Family;
use App\Models\Loan;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Deadlines;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-01 10:00', 'Europe/Rome'));
    $this->family = Family::factory()->create();
    $this->actingAs(User::factory()->for($this->family)->admin()->create());
    $this->loan = Loan::factory()->for($this->family)->create(['start_date' => '2026-11-07', 'end_date' => '2028-10-07']);
    $this->instalment = agendaPlanned($this->loan, 'Rata', 189.0, '2026-11-07', ['interval_unit' => IntervalUnit::Month]);
});

afterEach(fn () => Date::setTestNow());

/**
 * The automatic reminder of the first instalment of the loan.
 */
function paidDeadlineFirstInstalment(): Reminder
{
    return Reminder::query()->where('source_field', 'loans.start_date')->sole();
}

test('paying the first instalment marks its reminder as done', function (): void {
    expect(paidDeadlineFirstInstalment()->status)->toBe(ReminderStatus::Open);

    $deadline = resolve(Deadlines::class)->find($this->instalment, CarbonImmutable::parse('2026-11-07'));
    resolve(RecordPayment::class)->handle($deadline, CarbonImmutable::parse('2026-11-11'), 189.0, null, null);

    expect(paidDeadlineFirstInstalment()->status)->toBe(ReminderStatus::Done);
});

test('paying another instalment leaves the reminder of the first one open', function (): void {
    $deadline = resolve(Deadlines::class)->find($this->instalment, CarbonImmutable::parse('2026-12-07'));
    resolve(RecordPayment::class)->handle($deadline, CarbonImmutable::parse('2026-12-07'), 189.0, null, null);

    expect(paidDeadlineFirstInstalment()->status)->toBe(ReminderStatus::Open);
});
