<?php

declare(strict_types=1);

use App\Enums\CallOutcome;
use App\Enums\MailOutcome;
use App\Models\Family;
use App\Models\FamilySetting;
use App\Models\File;
use App\Models\MailInboxCall;
use App\Models\ReceivedMail;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Support\CurrentFamily;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;

test('a mail is unique by event in its family', function (): void {
    $a = Family::factory()->create();
    $b = Family::factory()->create();
    ReceivedMail::factory()->for($a)->create(['event_id' => 'evt-1']);

    ReceivedMail::factory()->for($b)->create(['event_id' => 'evt-1']);

    expect(fn () => ReceivedMail::factory()->for($a)->create(['event_id' => 'evt-1']))->toThrow(QueryException::class);
});

test('a received mail casts its outcome and its json columns', function (): void {
    $mail = ReceivedMail::factory()->create(['outcome' => MailOutcome::Held, 'notes' => ['one'], 'skipped' => [['name' => 'a.png', 'reason' => 'too_small']]]);
    $mail = ReceivedMail::query()->withoutGlobalScopes()->findOrFail($mail->id);

    expect($mail->outcome)->toBe(MailOutcome::Held)
        ->and($mail->notes)->toBe(['one'])
        ->and($mail->deliveries)->toBe(1)
        ->and($mail->received_at)->toBeInstanceOf(DateTimeImmutable::class);
});

test('the same mail key is unique inside a family but not across families', function (): void {
    $a = Family::factory()->create();
    $b = Family::factory()->create();
    File::factory()->for($a)->create(['mail_key' => 'k1']);

    File::factory()->for($b)->create(['mail_key' => 'k1']);
    File::factory()->for($a)->create(['mail_key' => null]);
    File::factory()->for($a)->create(['mail_key' => null]);

    expect(fn () => File::factory()->for($a)->create(['mail_key' => 'k1']))->toThrow(QueryException::class);
});

test('the inbox address is unique among the families', function (): void {
    FamilySetting::factory()->create(['mail_inbox_recipient' => 'bianchi@example.test']);

    expect(fn () => FamilySetting::factory()->create(['mail_inbox_recipient' => 'bianchi@example.test']))->toThrow(QueryException::class);
});

test('the notification flags of the settings have their defaults', function (): void {
    $setting = FamilySetting::factory()->create();

    expect($setting->refresh()->mail_inbox_notify_attachments_only)->toBeTrue();
});

test('a call of 91 days is pruned and one of 89 days is not', function (): void {
    Date::setTestNow('2026-10-25 09:41:00');
    $old = MailInboxCall::factory()->create(['created_at' => now()->subDays(91)]);
    $recent = MailInboxCall::factory()->create(['created_at' => now()->subDays(89)]);

    $ids = (new MailInboxCall)->prunable()->pluck('id')->all();

    expect($ids)->toBe([$old->id])->not->toContain($recent->id)
        ->and($recent->outcome)->toBe(CallOutcome::Saved);
});

test('deleting a mail removes its bell notices and empties the link of its files', function (): void {
    $family = Family::factory()->create();
    $user = User::factory()->for($family)->create();
    $mail = ReceivedMail::factory()->for($family)->create();
    $file = File::factory()->for($family)->create(['received_mail_id' => $mail->id]);
    $notice = ReminderNotice::factory()->for($family)->create([
        'reminder_id' => null, 'due_date' => null, 'offset' => null,
        'user_id' => $user->id, 'received_mail_id' => $mail->id, 'lines' => ['a.pdf - Edison'],
    ]);
    $other = ReminderNotice::factory()->for($family)->create(['user_id' => $user->id]);

    CurrentFamily::using($family->id, fn () => $mail->delete());

    expect(ReminderNotice::query()->withoutGlobalScopes()->find($notice->id))->toBeNull()
        ->and(ReminderNotice::query()->withoutGlobalScopes()->find($other->id))->not->toBeNull()
        ->and($file->fresh()?->received_mail_id)->toBeNull();
});

test('a mail relates to its files and its notice to the mail', function (): void {
    $mail = ReceivedMail::factory()->create();
    $file = File::factory()->for($mail->family)->create(['received_mail_id' => $mail->id]);
    $notice = ReminderNotice::factory()->for($mail->family)->create(['reminder_id' => null, 'received_mail_id' => $mail->id, 'lines' => ['x']]);

    expect($mail->files()->withoutGlobalScopes()->pluck('id')->all())->toBe([$file->id])
        ->and($notice->mail()->withoutGlobalScopes()->first()?->id)->toBe($mail->id)
        ->and($notice->lines)->toBe(['x']);
});

test('the outcomes have an order for the best one', function (): void {
    $order = [MailOutcome::Saved, MailOutcome::Confirmation, MailOutcome::Discarded, MailOutcome::Held, MailOutcome::Empty, MailOutcome::Error];
    $ranks = array_map(static fn (MailOutcome $o): int => $o->rank(), $order);
    $sorted = $ranks;
    sort($sorted);

    expect($ranks)->toBe($sorted)->and(array_unique($ranks))->toHaveCount(6);
});
