<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\ReceivedMail;
use App\Models\Reminder;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Services\Reminders\NoticeList;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 09:41', 'Europe/Rome'));
    $this->admin = registerFamily();
});

/**
 * A notice of the bell about a received mail of the family of an account.
 *
 * @param  User  $user  who it was sent to
 * @param  array<string, mixed>  $mail  the columns of the mail
 */
function mailNoticeFor(User $user, array $mail = []): ReminderNotice
{
    return insideFamilyOf($user, fn (): ReminderNotice => ReminderNotice::factory()->create([
        'family_id' => $user->family_id,
        'user_id' => $user->id,
        'reminder_id' => null,
        'due_date' => null,
        'offset' => null,
        'received_mail_id' => ReceivedMail::factory()->create([
            'family_id' => $user->family_id,
            'sender' => 'Edison <fatture@edison.example>',
            'subject' => 'La tua bolletta',
            ...$mail,
        ])->id,
        'lines' => ["bolletta_ottobre.pdf — Edison, 84,20\u{A0}€, due on 30/10/2026", 'dettaglio.pdf'],
        'sent_at' => CarbonImmutable::parse('2026-10-25 09:42', 'Europe/Rome'),
    ]));
}

/**
 * The notice of the bell about the limit of mails a day, for an account.
 *
 * @param  User  $user  who it was sent to
 */
function limitNoticeFor(User $user): ReminderNotice
{
    return insideFamilyOf($user, fn (): ReminderNotice => ReminderNotice::factory()->create([
        'family_id' => $user->family_id,
        'user_id' => $user->id,
        'reminder_id' => null,
        'due_date' => null,
        'offset' => null,
        'lines' => ['More than 100 mails arrived today: the others are not saved. If they are spam, change the address.'],
        'sent_at' => CarbonImmutable::parse('2026-10-25 08:00', 'Europe/Rome'),
    ]));
}

/**
 * The rows of *Notices* as an account sees them, new ones first.
 *
 * @return list<array<string, mixed>>
 */
function bellRows(User $user): array
{
    $props = test()->actingAs($user)->get('/notices')->assertOk()->viewData('page')['props'];

    return [...$props['unread'], ...$props['seen']];
}

test('a notice of a mail is a row that leads to the mail', function (): void {
    $notice = mailNoticeFor($this->admin);

    $row = bellRows($this->admin)[0];

    expect($row['id'])->toBe($notice->id)
        ->and($row['title'])->toBe('Arrived by mail · La tua bolletta')
        ->and($row['subject'])->toBe('Edison')
        ->and($row['area'])->toBe('general')
        ->and($row['due_text'])->toBe("bolletta_ottobre.pdf — Edison, 84,20\u{A0}€, due on 30/10/2026")
        ->and($row['sent_at'])->toBe('2026-10-25T09:42')
        ->and($row['read'])->toBeFalse()
        ->and($row['state'])->toBeNull()
        ->and($row['href'])->toBe(url('mail/'.$notice->received_mail_id));
});

test('the row of a mail names the organization of a known sender, or the address without a name', function (): void {
    $organization = insideFamilyOf($this->admin, fn (): Organization => Organization::factory()->create(['family_id' => $this->admin->family_id, 'name' => 'Edison Energia']));
    mailNoticeFor($this->admin, ['organization_id' => $organization->id]);
    mailNoticeFor($this->admin, ['sender' => 'promo@offerte-energia.it', 'sender_address' => 'promo@offerte-energia.it', 'subject' => null]);

    $rows = collect(bellRows($this->admin));

    expect($rows->pluck('subject')->sort()->values()->all())->toBe(['Edison Energia', 'promo@offerte-energia.it'])
        ->and($rows->pluck('title')->all())->toContain('Arrived by mail · no subject');
});

test('the notice of the limit leads to the address for the bills', function (): void {
    limitNoticeFor($this->admin);

    $row = bellRows($this->admin)[0];

    expect($row['title'])->toBe('Too many mails today')
        ->and($row['subject'])->toBeNull()
        ->and($row['due_text'])->toBe('More than 100 mails arrived today: the others are not saved. If they are spam, change the address.')
        ->and($row['state'])->toBeNull()
        ->and($row['href'])->toBe(url('settings/mail'));
});

test('the rows of mails, of the limit and of reminders are listed together, in the order of the bell', function (): void {
    $limit = limitNoticeFor($this->admin);
    $mail = mailNoticeFor($this->admin);
    $reminder = insideFamilyOf($this->admin, fn (): ReminderNotice => ReminderNotice::factory()->create([
        'family_id' => $this->admin->family_id,
        'user_id' => $this->admin->id,
        'reminder_id' => Reminder::factory()->create(['family_id' => $this->admin->family_id, 'title' => 'Pagare il bollo', 'due_date' => '2026-10-31'])->id,
        'due_date' => '2026-10-31',
        'sent_at' => CarbonImmutable::parse('2026-10-25 06:00', 'Europe/Rome'),
    ]));

    expect(array_column(bellRows($this->admin), 'id'))->toBe([$mail->id, $limit->id, $reminder->id])
        ->and(collect(resolve(NoticeList::class)->latest($this->admin->id, CarbonImmutable::today('Europe/Rome'))['unread'])->pluck('id')->all())
        ->toBe([$mail->id, $limit->id, $reminder->id]);
});

test('reading a notice of a mail goes to the mail, the one of the limit to the address for the bills', function (): void {
    $mail = mailNoticeFor($this->admin);
    $limit = limitNoticeFor($this->admin);

    $this->actingAs($this->admin)->post("/notices/{$mail->id}/read")->assertRedirect(url('mail/'.$mail->received_mail_id));
    $this->actingAs($this->admin)->post("/notices/{$limit->id}/read")->assertRedirect(url('settings/mail'));

    expect($mail->fresh()?->read_at)->not->toBeNull()
        ->and($limit->fresh()?->read_at)->not->toBeNull();
});

test('the number on the bell counts the notices of mails and of the limit, as the list shows them', function (): void {
    mailNoticeFor($this->admin);
    limitNoticeFor($this->admin);

    $props = $this->actingAs($this->admin)->get('/notices')->assertOk()->viewData('page')['props'];

    expect($props['unread_notices'])->toBe(2)
        ->and($props['unread'])->toHaveCount(2);
});
