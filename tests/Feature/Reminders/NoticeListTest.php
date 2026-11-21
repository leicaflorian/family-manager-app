<?php

declare(strict_types=1);

use App\Actions\Mail\NotifyMailLimit;
use App\Enums\BankMatchSource;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
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
        ->and($row['href'])->toBe(route('mail.show', $notice->received_mail_id));
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

    $this->actingAs($this->admin)->post("/notices/{$mail->id}/read")->assertRedirect(route('mail.show', $mail->received_mail_id));
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

test('the bell never reads the text of the mails', function (): void {
    $notice = mailNoticeFor($this->admin);
    $columns = [];
    ReceivedMail::retrieved(function (ReceivedMail $mail) use (&$columns): void {
        $columns = [...$columns, ...array_keys($mail->getAttributes())];
    });

    bellRows($this->admin);
    insideFamilyOf($this->admin, fn (): string => resolve(NoticeList::class)->hrefOf($notice, CarbonImmutable::today('Europe/Rome')));

    expect($columns)->not->toBeEmpty()
        ->not->toContain('body')
        ->not->toContain('screening');
});

/**
 * The notice of the bell about a payment the bank settled, for an account, with the transaction and its payment.
 *
 * @param  User  $user  who it was sent to
 * @param  string  $kind  `expense` or `income`
 * @param  string  $title  the title of the payment
 */
function bankNoticeListNotice(User $user, string $kind = 'expense', string $title = 'Luce novembre 2026'): ReminderNotice
{
    return insideFamilyOf($user, function () use ($user, $kind, $title): ReminderNotice {
        $payment = $kind === 'expense'
            ? Expense::factory()->create(['family_id' => $user->family_id, 'title' => $title])
            : Income::factory()->create(['family_id' => $user->family_id, 'title' => $title]);
        $transaction = BankTransaction::factory()->create([
            'family_id' => $user->family_id,
            'text' => 'ADDEBITO SDD EDISON ENERGIA',
            'amount' => $kind === 'expense' ? '-72.40' : '1850.00',
            $kind.'_id' => $payment->id,
            'matched_by' => BankMatchSource::Rule,
        ]);

        return ReminderNotice::factory()->create([
            'family_id' => $user->family_id,
            'user_id' => $user->id,
            'reminder_id' => null,
            'due_date' => null,
            'offset' => null,
            'bank_transaction_id' => $transaction->id,
            'lines' => ["72,40\u{A0}€ from Conto BancoPosta", "72,40\u{A0}€ instead of 45,00\u{A0}€"],
            'sent_at' => CarbonImmutable::parse('2026-10-25 07:00', 'Europe/Rome'),
        ]);
    });
}

test('the notice of a payment of the bank names the payment and leads to it', function (): void {
    $notice = bankNoticeListNotice($this->admin);
    $expense = insideFamilyOf($this->admin, fn (): ?Expense => BankTransaction::query()->find($notice->bank_transaction_id)?->expense);

    $row = bellRows($this->admin)[0];

    expect($row['id'])->toBe($notice->id)
        ->and($row['title'])->toBe('Paid by the bank · Luce novembre 2026')
        ->and($row['subject'])->toBeNull()
        ->and($row['area'])->toBe('banks')
        ->and($row['due_text'])->toBe("72,40\u{A0}€ from Conto BancoPosta")
        ->and($row['sent_at'])->toBe('2026-10-25T07:00')
        ->and($row['state'])->toBeNull()
        ->and($row['href'])->toBe(route('budget.movements.show', ['kind' => 'expense', 'id' => $expense?->id]));

    $this->actingAs($this->admin)->post("/notices/{$notice->id}/read")->assertRedirect($row['href']);
});

test('the notice of an income of the bank says it arrived', function (): void {
    $notice = bankNoticeListNotice($this->admin, 'income', 'Stipendio di Marco novembre 2026');
    $income = insideFamilyOf($this->admin, fn (): ?Income => BankTransaction::query()->find($notice->bank_transaction_id)?->income);

    $row = bellRows($this->admin)[0];

    expect($row['title'])->toBe('Arrived in the bank · Stipendio di Marco novembre 2026')
        ->and($row['href'])->toBe(route('budget.movements.show', ['kind' => 'income', 'id' => $income?->id]));
});

test('the notice of a payment of the bank that is gone leads to the transactions of the bank', function (): void {
    $notice = bankNoticeListNotice($this->admin);
    insideFamilyOf($this->admin, fn (): ?bool => BankTransaction::query()->find($notice->bank_transaction_id)?->expense?->delete());

    $row = bellRows($this->admin)[0];

    expect($row['title'])->toBe('Paid by the bank · ADDEBITO SDD EDISON ENERGIA')
        ->and($row['href'])->toBe(route('budget.bank.index'));
});

test('the notice of a consent that ends names the bank and leads to the banks', function (): void {
    $notice = insideFamilyOf($this->admin, function (): ReminderNotice {
        $connection = BankConnection::factory()->create(['family_id' => $this->admin->family_id, 'institution_name' => 'BancoPosta']);

        return ReminderNotice::factory()->create([
            'family_id' => $this->admin->family_id,
            'user_id' => $this->admin->id,
            'reminder_id' => null,
            'due_date' => null,
            'offset' => null,
            'bank_connection_id' => $connection->id,
            'lines' => ['BancoPosta has removed the link. Connect it again to keep receiving the transactions.'],
            'sent_at' => CarbonImmutable::parse('2026-10-25 07:00', 'Europe/Rome'),
        ]);
    });

    $row = bellRows($this->admin)[0];

    expect($row['id'])->toBe($notice->id)
        ->and($row['title'])->toBe('Bank to connect again · BancoPosta')
        ->and($row['area'])->toBe('banks')
        ->and($row['due_text'])->toBe('BancoPosta has removed the link. Connect it again to keep receiving the transactions.')
        ->and($row['href'])->toBe(route('banks.index'));
});

test('the mail limit notice is still sent once a day', function (): void {
    bankNoticeListNotice($this->admin);
    insideFamilyOf($this->admin, function (): void {
        ReminderNotice::query()->update(['sent_at' => now()]);

        resolve(NotifyMailLimit::class)->handle(100);
        resolve(NotifyMailLimit::class)->handle(100);

        expect(ReminderNotice::query()->whereNull('bank_transaction_id')->where('user_id', $this->admin->id)->count())->toBe(1);
    });
});
