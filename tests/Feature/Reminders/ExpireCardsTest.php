<?php

declare(strict_types=1);

use App\Actions\Reminders\ExpireCards;
use App\Enums\DocumentStatus;
use App\Enums\InsurancePolicyStatus;
use App\Enums\LoanStatus;
use App\Enums\ReminderStatus;
use App\Models\Document;
use App\Models\Family;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\Reminder;
use App\Models\User;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-21 10:00', 'Europe/Rome'));
    $this->family = Family::factory()->create();
    $this->actingAs(User::factory()->for($this->family)->admin()->create());
});

afterEach(fn () => Date::setTestNow());

/**
 * Runs a creation without model events, so the observer does not expire the card on its own.
 *
 * @template T
 *
 * @param  Closure(): T  $create  the creation
 * @return T
 */
function quietly(Closure $create): mixed
{
    return Model::withoutEvents($create);
}

test('a date of yesterday expires today', function (): void {
    $document = quietly(fn () => Document::factory()->for($this->family)->create(['status' => DocumentStatus::Active, 'expiry_date' => '2026-10-20']));
    $policy = quietly(fn () => InsurancePolicy::factory()->for($this->family)->create(['status' => InsurancePolicyStatus::Active, 'expiry_date' => '2026-10-20']));
    $loan = quietly(fn () => Loan::factory()->for($this->family)->create(['status' => LoanStatus::Active, 'end_date' => '2026-10-20']));

    $count = (new ExpireCards)->handle(CarbonImmutable::parse('2026-10-21', 'Europe/Rome'));

    expect($count)->toBe(3)
        ->and($document->fresh()->status)->toBe(DocumentStatus::Expired)
        ->and($policy->fresh()->status)->toBe(InsurancePolicyStatus::Expired)
        ->and($loan->fresh()->status)->toBe(LoanStatus::PaidOff);
});

test('a date of today does not', function (): void {
    $document = quietly(fn () => Document::factory()->for($this->family)->create(['status' => DocumentStatus::Active, 'expiry_date' => '2026-10-21']));

    $count = (new ExpireCards)->handle(CarbonImmutable::parse('2026-10-21', 'Europe/Rome'));

    expect($count)->toBe(0)->and($document->fresh()->status)->toBe(DocumentStatus::Active);
});

test('a card that is not active is left alone', function (): void {
    $document = Document::factory()->for($this->family)->create(['status' => DocumentStatus::Archived, 'expiry_date' => '2026-10-01']);

    expect((new ExpireCards)->handle(CarbonImmutable::parse('2026-10-21', 'Europe/Rome')))->toBe(0)
        ->and($document->fresh()->status)->toBe(DocumentStatus::Archived);
});

test('the midnight of Rome', function (): void {
    // 25/11/2026 00:30 in Rome is still the 24th in UTC
    Date::setTestNow(CarbonImmutable::parse('2026-11-25 00:30', 'Europe/Rome'));
    $document = quietly(fn () => Document::factory()->for($this->family)->create(['status' => DocumentStatus::Active, 'expiry_date' => '2026-11-24']));

    $count = (new ExpireCards)->handle(CarbonImmutable::today('Europe/Rome'));

    expect($count)->toBe(1)->and($document->fresh()->status)->toBe(DocumentStatus::Expired);
});

test('saving an expired card changes its status', function (): void {
    $document = Document::factory()->for($this->family)->create(['status' => DocumentStatus::Active, 'expiry_date' => '2026-10-20']);
    $loan = Loan::factory()->for($this->family)->create(['status' => LoanStatus::Active, 'end_date' => '2026-10-21']);

    expect($document->fresh()->status)->toBe(DocumentStatus::Expired)
        ->and($loan->fresh()->status)->toBe(LoanStatus::Active);

    $loan->update(['end_date' => '2026-10-20']);

    expect($loan->fresh()->status)->toBe(LoanStatus::PaidOff);
});

test('the open reminder stays', function (): void {
    $document = Document::factory()->for($this->family)->create(['status' => DocumentStatus::Active, 'expiry_date' => '2026-10-30']);
    $document->update(['expiry_date' => '2026-10-20']);

    expect($document->fresh()->status)->toBe(DocumentStatus::Expired);

    // an expired document keeps its reminder to renew it
    $document->update(['expiry_date' => '2026-12-01', 'status' => DocumentStatus::Active]);
    (new ExpireCards)->handle(CarbonImmutable::parse('2026-12-02', 'Europe/Rome'));

    $reminder = Reminder::query()->where('source_field', 'documents.expiry_date')->first();

    expect($document->fresh()->status)->toBe(DocumentStatus::Expired)
        ->and($reminder?->status)->toBe(ReminderStatus::Open)
        ->and($reminder?->due_date->toDateString())->toBe('2026-12-01');
});

test('another family is not touched', function (): void {
    $other = Family::factory()->create();
    $mine = quietly(fn () => Document::factory()->for($this->family)->create(['status' => DocumentStatus::Active, 'expiry_date' => '2026-10-20']));
    $theirs = CurrentFamily::using($other->id, fn (): mixed => quietly(fn () => Document::factory()->for($other)->create(['status' => DocumentStatus::Active, 'expiry_date' => '2026-10-01'])));

    $count = (new ExpireCards)->handle(CarbonImmutable::parse('2026-10-21', 'Europe/Rome'));

    expect($count)->toBe(1)
        ->and($mine->fresh()->status)->toBe(DocumentStatus::Expired)
        ->and(CurrentFamily::using($other->id, fn () => Document::query()->find($theirs->id)?->status))->toBe(DocumentStatus::Active);
});
