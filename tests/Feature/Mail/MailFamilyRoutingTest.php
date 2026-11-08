<?php

declare(strict_types=1);

use App\Enums\CallOutcome;
use App\Jobs\ScreenReceivedMail;
use App\Models\FamilySetting;
use App\Models\File;
use App\Models\MailInboxCall;
use App\Models\ReceivedMail;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Tests\Support\MailgunCall;

/*
 * A mail never lands in the wrong family: the family is found only from the address the mail was delivered to
 * (`recipient`), exactly, and everything is written inside it.
 */

const ROUTING_A = 'bianchi.k7m2x9@bollette.familymanager.test';
const ROUTING_B = 'rossi.p4r8tw@bollette.familymanager.test';

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 09:41', 'Europe/Rome'));
    Queue::fake();
    $this->a = familyWithMailAddress('Bianchi', 'marco@example.com', ROUTING_A);
    $this->b = familyWithMailAddress('Rossi', 'paolo@example.com', ROUTING_B);
});

/**
 * What a family holds of the mail: its files, its mails, its bell notices, and the rows of the log with its id.
 *
 * @return array{files: int, mails: int, notices: int, calls: int}
 */
function mailHeldBy(User $admin): array
{
    return insideFamilyOf($admin, fn (): array => [
        'files' => File::query()->count(),
        'mails' => ReceivedMail::query()->count(),
        'notices' => ReminderNotice::query()->count(),
        'calls' => MailInboxCall::query()->where('family_id', $admin->family_id)->count(),
    ]);
}

/**
 * A bill with its PDF, delivered to an address.
 */
function billTo(string $recipient): MailgunCall
{
    return MailgunCall::to($recipient)->attach('bolletta.pdf', "%PDF-1.7\n", 'application/pdf');
}

test('a mail for A goes only to A, and B sees nothing of it', function (): void {
    billTo(ROUTING_A)->send()->assertOk();

    expect(mailHeldBy($this->a))->toBe(['files' => 1, 'mails' => 1, 'notices' => 0, 'calls' => 1])
        ->and(mailHeldBy($this->b))->toBe(['files' => 0, 'mails' => 0, 'notices' => 0, 'calls' => 0]);
    Queue::assertPushed(ScreenReceivedMail::class, fn (ScreenReceivedMail $job): bool => $job->familyId === $this->a->family_id);
    Queue::assertNotPushed(ScreenReceivedMail::class, fn (ScreenReceivedMail $job): bool => $job->familyId === $this->b->family_id);
});

test('a mail for A goes to A even while B is the current family', function (): void {
    CurrentFamily::set($this->b->family_id);

    billTo(ROUTING_A)->send()->assertOk();

    expect(mailHeldBy($this->a)['files'])->toBe(1)
        ->and(mailHeldBy($this->b)['files'])->toBe(0)
        ->and(CurrentFamily::id())->toBe($this->b->family_id);
});

test('the limit notice of A goes only to A', function (): void {
    insideFamilyOf($this->a, function (): void {
        ReceivedMail::factory()->count(100)->create(['family_id' => $this->a->family_id, 'received_at' => today()->setTime(0, 5)]);
    });

    billTo(ROUTING_A)->send()->assertOk();

    expect(mailHeldBy($this->a)['notices'])->toBe(1)
        ->and(mailHeldBy($this->b))->toBe(['files' => 0, 'mails' => 0, 'notices' => 0, 'calls' => 0]);
});

test('the To of B with the recipient of A goes to A', function (): void {
    billTo(ROUTING_A)->toHeader(ROUTING_B)->send()->assertOk();

    expect(mailHeldBy($this->a)['mails'])->toBe(1)
        ->and(mailHeldBy($this->b)['mails'])->toBe(0);
});

test('the recipient in capitals goes to A', function (): void {
    billTo(strtoupper(ROUTING_A))->send()->assertOk();

    expect(mailHeldBy($this->a)['files'])->toBe(1)
        ->and(mailHeldBy($this->b)['files'])->toBe(0);
});

test('an address that is not exactly one of a family goes to nobody', function (string $recipient): void {
    billTo($recipient)->send()->assertOk();

    expect(mailHeldBy($this->a))->toBe(['files' => 0, 'mails' => 0, 'notices' => 0, 'calls' => 0])
        ->and(mailHeldBy($this->b))->toBe(['files' => 0, 'mails' => 0, 'notices' => 0, 'calls' => 0])
        ->and(MailInboxCall::query()->sole()->outcome)->toBe(CallOutcome::Unknown);
    Queue::assertNothingPushed();
})->with([
    'one character wrong' => 'bianchi.k7m2x8@bollette.familymanager.test',
    'another domain' => 'bianchi.k7m2x9@bollette.example.com',
    'two addresses' => ROUTING_A.', '.ROUTING_B,
    'two addresses without space' => ROUTING_A.','.ROUTING_B,
]);

test('the old address of A goes to nobody once A changed it', function (): void {
    insideFamilyOf($this->a, function (): void {
        FamilySetting::query()->sole()->forceFill(['mail_inbox_recipient' => 'bianchi.newnew@bollette.familymanager.test'])->save();
    });

    billTo(ROUTING_A)->send()->assertOk();

    expect(mailHeldBy($this->a)['mails'])->toBe(0)
        ->and(MailInboxCall::query()->sole()->outcome)->toBe(CallOutcome::Unknown);
});
