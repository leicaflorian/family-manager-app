<?php

declare(strict_types=1);

use App\Contracts\DocumentReader;
use App\Data\ReceivedMailRowData;
use App\Enums\FileSource;
use App\Enums\MailOutcome;
use App\Enums\ReadingPurpose;
use App\Enums\ReadingStatus;
use App\Enums\Role;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\ReadFile;
use App\Models\Document;
use App\Models\File;
use App\Models\Organization;
use App\Models\ReceivedMail;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Support\Mail\AttachmentRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeDocumentReader;

const PAGES_ADDRESS = 'bianchi.k7m2x9@bollette.familymanager.test';

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 09:41', 'Europe/Rome'));
    Queue::fake();
    app()->instance(DocumentReader::class, new FakeDocumentReader);
    $this->marco = familyWithMailAddress('Bianchi', 'marco@example.com', PAGES_ADDRESS);
    $this->laura = User::factory()->for($this->marco->family)->create(['role' => Role::Member, 'email_verified_at' => now()]);
});

/**
 * A mail of the Bianchi.
 *
 * @param  array<string, mixed>  $attributes
 */
function bianchiMail(array $attributes = []): ReceivedMail
{
    return ReceivedMail::factory()->create([
        'family_id' => test()->marco->family_id,
        'recipient' => PAGES_ADDRESS,
        'recipients' => [PAGES_ADDRESS],
        ...$attributes,
    ]);
}

/**
 * A file saved from a mail of the Bianchi, recorded on the mail as saved.
 *
 * @param  array<string, mixed>  $attributes
 */
function mailFile(ReceivedMail $mail, int $index, array $attributes = []): File
{
    $file = File::factory()->create([
        'family_id' => $mail->family_id,
        'to_sort' => true,
        'source' => FileSource::Mail,
        'received_mail_id' => $mail->id,
        'mail_key' => AttachmentRules::mailKey($mail->event_id, $index),
        ...$attributes,
    ]);
    $mail->recordSaved($file);

    return $file;
}

/**
 * The mails of the example of the analysis, oldest first: Edison delivered twice, the advertisement discarded, the
 * TARI of the council with its logo left out, the notice of Hera, Fastweb and Enel.
 *
 * @return array<string, ReceivedMail>
 */
function boardMails(): array
{
    $family = test()->marco->family_id;
    $enel = Organization::factory()->create(['family_id' => $family, 'name' => 'Enel Energia']);

    $edison = bianchiMail(['sender' => 'Edison <fatture@edison.it>', 'sender_address' => 'fatture@edison.it', 'subject' => 'Bolletta di settembre', 'received_at' => '2026-10-14 07:58', 'deliveries' => 2]);
    mailFile($edison, 0, ['original_name' => 'bolletta_settembre.pdf']);
    $promo = bianchiMail(['sender' => 'promo@offerte-energia.it', 'sender_address' => 'promo@offerte-energia.it', 'subject' => 'Nuove offerte luce e gas', 'received_at' => '2026-10-22 08:03', 'outcome' => MailOutcome::Discarded, 'notes' => ['scartata: offerta commerciale'], 'body' => ['plainText' => 'Offerte!', 'html' => null]]);
    $tari = bianchiMail([
        'sender' => 'Comune di Bologna <tributi@comune.bologna.it>', 'sender_address' => 'tributi@comune.bologna.it', 'subject' => 'TARI 2026 - avviso di pagamento', 'received_at' => '2026-10-23 11:05',
        'skipped' => [['name' => 'logo_comune.png', 'reason' => 'too_small']],
        'body' => ['plainText' => 'Gentile contribuente, in allegato trova l\'avviso di pagamento della TARI 2026.', 'html' => '<p>Gentile contribuente</p>'],
    ]);
    mailFile($tari, 0, ['original_name' => 'Avviso TARI 2026.pdf']);
    $hera = bianchiMail(['sender' => 'Hera <bollette@hera.it>', 'sender_address' => 'bollette@hera.it', 'subject' => 'La tua fattura è disponibile', 'received_at' => '2026-10-24 17:30']);
    mailFile($hera, -1, ['original_name' => 'Avviso - La tua fattura è disponibile.txt', 'mime_type' => 'text/plain']);
    $fastweb = bianchiMail(['sender' => 'Fastweb <noreply@fastweb.it>', 'sender_address' => 'noreply@fastweb.it', 'subject' => 'Fattura disponibile', 'received_at' => '2026-10-25 09:12']);
    mailFile($fastweb, 0);
    $enelMail = bianchiMail(['sender' => 'Enel <bollette@enel.it>', 'sender_address' => 'bollette@enel.it', 'subject' => 'La tua bolletta', 'received_at' => '2026-10-25 09:41', 'organization_id' => $enel->id]);
    mailFile($enelMail, 0);

    return ['edison' => $edison, 'promo' => $promo, 'tari' => $tari, 'hera' => $hera, 'fastweb' => $fastweb] + ['enel' => $enelMail];
}

test('the list: the mails of the family, newest first, with who wrote, the subject and what was done', function (): void {
    $mails = boardMails();

    $this->actingAs($this->marco)->get(route('mail.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('mail/ReceivedMails')
        ->where('address', PAGES_ADDRESS)
        ->where('search', '')
        ->where('mail', null)
        ->where('next', null)
        ->has('mails', 6)
        ->where('mails.0', [
            'id' => $mails['enel']->id,
            'sender' => 'Enel Energia',
            'subject' => 'La tua bolletta',
            'received_at' => '2026-10-25T09:41:00+01:00',
            'outcome_label' => '1 file saved',
            'tone' => 'success',
        ])
        ->where('mails.1.sender', 'Fastweb')
        ->where('mails.2.outcome_label', 'Notice saved')
        ->where('mails.2.tone', 'info')
        ->where('mails.4.sender', 'promo@offerte-energia.it')
        ->where('mails.4.outcome_label', 'Discarded')
        ->where('mails.4.tone', 'neutral')
        ->where('mails.5.outcome_label', '1 file saved · 2 deliveries')
        ->where('mails.5.received_at', '2026-10-14T07:58:00+02:00'));
});

test('the list shows 50 mails at a time, the next ones scrolling', function (): void {
    foreach (range(1, 51) as $minute) {
        bianchiMail(['subject' => "Mail {$minute}", 'received_at' => now()->subMinutes($minute)]);
    }

    $this->actingAs($this->marco)->get(route('mail.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('mails', 50)
        ->where('mails.0.subject', 'Mail 1')
        ->where('next', 2));

    $this->actingAs($this->marco)->get(route('mail.index', ['page' => 2]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('mails', 1)
        ->where('mails.0.subject', 'Mail 51')
        ->where('next', null));
});

test('the search looks in the sender, the subject and the recipients, never in the text', function (): void {
    boardMails();
    bianchiMail(['subject' => 'Inoltrata', 'recipients' => [PAGES_ADDRESS, 'nonna.pina@example.com'], 'body' => ['plainText' => 'Hera', 'html' => null]]);

    $subjects = fn (string $search): array => array_column(
        $this->actingAs($this->marco)->get(route('mail.index', ['search' => $search]))->viewData('page')['props']['mails'],
        'subject',
    );

    $this->actingAs($this->marco)->get(route('mail.index', ['search' => 'hera']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('search', 'hera'));
    expect($subjects('hera'))->toBe(['La tua fattura è disponibile'])
        ->and($subjects('TARI'))->toBe(['TARI 2026 - avviso di pagamento'])
        ->and($subjects('nonna.pina'))->toBe(['Inoltrata'])
        ->and($subjects('contribuente'))->toBe([]);
});

test('a mail open: who wrote, to whom, what was done with each file and the files left out', function (): void {
    $mails = boardMails();

    $this->actingAs($this->marco)->get(route('mail.show', $mails['tari']))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('mail/ReceivedMails')
        ->has('mails', 6)
        ->where('mail.row.id', $mails['tari']->id)
        ->where('mail.row.subject', 'TARI 2026 - avviso di pagamento')
        ->where('mail.from', 'Comune di Bologna <tributi@comune.bologna.it>')
        ->where('mail.recipient', PAGES_ADDRESS)
        ->where('mail.recipients', [PAGES_ADDRESS])
        ->where('mail.files', [[
            'name' => 'Avviso TARI 2026.pdf',
            'state' => 'to_sort',
            'url' => route('to-sort'),
            'document_title' => null,
        ]])
        ->where('mail.skipped', [['name' => 'logo_comune.png', 'reason' => 'Too small']])
        ->where('mail.notes', [])
        ->where('mail.gmail_code', null)
        ->where('mail.has_text', true)
        ->where('mail.has_both_texts', true)
        ->where('mail.html_too_large', null)
        ->where('mail.text_url', route('mail.text', $mails['tari']))
        ->where('mail.can_save_as_notice', false)
        ->where('mail.can_delete', true)
        ->where('mail.pending_notice', null));
});

test('a file that became a document leads to it, a file deleted is gone', function (): void {
    $mail = bianchiMail(['subject' => 'Due bollette', 'deliveries' => 1]);
    $filed = mailFile($mail, 0, ['original_name' => 'gas.pdf', 'to_sort' => false]);
    $document = Document::factory()->create(['family_id' => $mail->family_id, 'title' => 'Bolletta gas ottobre', 'file_id' => $filed->id]);
    mailFile($mail, 1, ['original_name' => 'luce.pdf'])->delete();

    $this->actingAs($this->laura)->get(route('mail.show', $mail))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('mail.files', [
            ['name' => 'gas.pdf', 'state' => 'document', 'url' => route('documents.show', $document), 'document_title' => 'Bolletta gas ottobre'],
            ['name' => 'luce.pdf', 'state' => 'gone', 'url' => null, 'document_title' => null],
        ])
        ->where('mail.row.outcome_label', '2 files saved · 1 deleted')
        ->where('mail.has_text', false)
        ->where('mail.can_delete', false)
        ->where('mail.can_save_as_notice', false));
});

test('the confirmation of Gmail shows its code, and an HTML too large says its size', function (): void {
    $mail = bianchiMail([
        'sender' => 'Gmail Team <forwarding-noreply@google.com>', 'sender_address' => 'forwarding-noreply@google.com', 'outcome' => MailOutcome::Confirmation, 'gmail_code' => '123456789',
        'body' => ['plainText' => 'Codice 123456789', 'html' => null, 'htmlBytes' => 1_468_006],
    ]);

    $this->actingAs($this->marco)->get(route('mail.show', $mail))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('mail.row.outcome_label', 'Gmail code')
        ->where('mail.gmail_code', '123456789')
        ->where('mail.has_text', true)
        ->where('mail.has_both_texts', false)
        ->where('mail.html_too_large', 1_468_006));
});

test('a held mail can be saved as a notice by an admin, and deleting it says its notice goes too', function (): void {
    $held = bianchiMail(['outcome' => MailOutcome::Held, 'body' => ['plainText' => str_repeat('La tua fattura è disponibile. ', 4), 'html' => null]]);
    $saved = boardMails()['hera'];

    $this->actingAs($this->marco)->get(route('mail.show', $held))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('mail.can_save_as_notice', true));
    $this->actingAs($this->laura)->get(route('mail.show', $held))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('mail.can_save_as_notice', false));
    $this->actingAs($this->marco)->get(route('mail.show', $saved))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('mail.pending_notice', 'Avviso - La tua fattura è disponibile.txt'));
});

test('the text of the mail comes isolated: a sandbox policy, no sniffing, and every link in a new tab', function (): void {
    $mail = bianchiMail(['body' => ['plainText' => 'Testo <semplice>', 'html' => '<p>Ciao <img src="https://tracker.example/x.png"></p><script>alert(1)</script>']]);

    $response = $this->actingAs($this->marco)->get(route('mail.text', $mail));

    $response->assertOk()
        ->assertHeader('Content-Security-Policy', "sandbox allow-popups allow-popups-to-escape-sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:; form-action 'none'; base-uri 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Content-Type'))->toStartWith('text/html')
        ->and($response->getContent())->toContain('<base target="_blank">')
        ->toContain('<p>Ciao <img src="https://tracker.example/x.png"></p>')
        ->toContain('<meta http-equiv="Content-Security-Policy"');
});

test('the plain text of the mail is written as text, never as HTML', function (): void {
    $mail = bianchiMail(['body' => ['plainText' => 'Testo <b>semplice</b>', 'html' => '<p>Originale</p>']]);

    $plain = $this->actingAs($this->marco)->get(route('mail.text', ['receivedMail' => $mail, 'plain' => 1]))->getContent();
    $onlyPlain = bianchiMail(['body' => ['plainText' => 'Solo <testo>', 'html' => null]]);

    expect($plain)->toContain('Testo &lt;b&gt;semplice&lt;/b&gt;')->not->toContain('<p>Originale</p>')
        ->and($this->actingAs($this->marco)->get(route('mail.text', $onlyPlain))->getContent())->toContain('Solo &lt;testo&gt;');
});

test('a mail without a text kept has no text to show', function (): void {
    $mail = bianchiMail(['body' => null]);

    $this->actingAs($this->marco)->get(route('mail.text', $mail))->assertNotFound();
});

test('a member cannot delete a mail', function (): void {
    $mail = boardMails()['tari'];

    $this->actingAs($this->laura)->delete(route('mail.destroy', $mail))->assertForbidden();

    expect(insideFamilyOf($this->marco, fn (): bool => ReceivedMail::query()->whereKey($mail->id)->exists()))->toBeTrue();
});

test('deleting a mail takes away its notice not worked on, the attachments stay without the link', function (): void {
    $mails = boardMails();
    $notice = File::query()->withoutGlobalScopes()->where('received_mail_id', $mails['hera']->id)->sole();
    $attachment = File::query()->withoutGlobalScopes()->where('received_mail_id', $mails['tari']->id)->sole();
    insideFamilyOf($this->marco, fn (): ReminderNotice => ReminderNotice::factory()->create(['family_id' => $this->marco->family_id, 'user_id' => $this->marco->id, 'reminder_id' => null, 'due_date' => null, 'offset' => null, 'received_mail_id' => $mails['hera']->id, 'lines' => []]));

    $this->actingAs($this->marco)->delete(route('mail.destroy', $mails['hera']))->assertRedirect(route('mail.index'));
    $this->actingAs($this->marco)->delete(route('mail.destroy', $mails['tari']))->assertRedirect(route('mail.index'));

    insideFamilyOf($this->marco, function () use ($mails, $notice, $attachment): void {
        expect(ReceivedMail::query()->whereKey([$mails['hera']->id, $mails['tari']->id])->exists())->toBeFalse()
            ->and(File::query()->whereKey($notice->id)->exists())->toBeFalse()
            ->and($attachment->fresh()?->received_mail_id)->toBeNull()
            ->and(ReminderNotice::query()->count())->toBe(0);
    });
});

test('deleting a mail keeps a notice that became a document', function (): void {
    $mail = boardMails()['hera'];
    $notice = File::query()->withoutGlobalScopes()->where('received_mail_id', $mail->id)->sole();
    $notice->forceFill(['to_sort' => false])->save();
    Document::factory()->create(['family_id' => $mail->family_id, 'file_id' => $notice->id]);

    $this->actingAs($this->marco)->delete(route('mail.destroy', $mail))->assertRedirect(route('mail.index'));

    expect($notice->fresh())->not->toBeNull()
        ->and($notice->fresh()?->received_mail_id)->toBeNull();
});

test('save as notice: the notice goes to To sort and is read, without a notice in the bell', function (): void {
    $mail = bianchiMail(['subject' => 'La tua fattura è disponibile', 'outcome' => MailOutcome::Discarded, 'notes' => ['scartata: offerta commerciale'], 'body' => ['plainText' => str_repeat('La tua fattura di ottobre è disponibile. ', 4), 'html' => null]]);

    $this->actingAs($this->marco)->from(route('mail.show', $mail))->post(route('mail.notice', $mail))
        ->assertRedirect(route('mail.show', $mail))
        ->assertSessionHasNoErrors();

    insideFamilyOf($this->marco, function () use ($mail): void {
        $notice = File::query()->sole();
        $fresh = $mail->fresh();
        expect($notice->original_name)->toBe('Notice - La tua fattura è disponibile.txt')
            ->and($notice->to_sort)->toBeTrue()
            ->and($notice->received_mail_id)->toBe($mail->id)
            ->and($notice->mail_key)->toBe(AttachmentRules::mailKey($mail->event_id, -1))
            ->and($notice->reading_status)->toBe(ReadingStatus::Queued)
            ->and($fresh?->outcome)->toBe(MailOutcome::Saved)
            ->and($fresh?->screening)->toBeNull()
            ->and(receivedMailLabel($fresh))->toBe('Notice saved')
            ->and(ReminderNotice::query()->count())->toBe(0);
    });
    Queue::assertPushed(ReadFile::class, fn (ReadFile $job): bool => $job->purpose === ReadingPurpose::MailNotice && $job->userId === null);
});

test('save as notice: a notice already there is not saved twice', function (): void {
    $mail = bianchiMail(['outcome' => MailOutcome::Held, 'body' => ['plainText' => str_repeat('Testo della mail. ', 10), 'html' => null]]);
    File::factory()->create(['family_id' => $mail->family_id, 'mail_key' => AttachmentRules::mailKey($mail->event_id, -1)]);

    $this->actingAs($this->marco)->from(route('mail.show', $mail))->post(route('mail.notice', $mail))
        ->assertRedirect(route('mail.show', $mail))
        ->assertSessionHasErrors(['notice' => 'The notice is already in To sort']);

    expect(insideFamilyOf($this->marco, fn (): int => File::query()->count()))->toBe(1);
    Queue::assertNothingPushed();
});

test('save as notice: the notice the job saves at the same moment answers with the message, not an error', function (): void {
    $mail = bianchiMail(['outcome' => MailOutcome::Held, 'body' => ['plainText' => str_repeat('Testo della mail. ', 10), 'html' => null]]);
    $raced = false;
    // the job writes its notice after the button found none, just before the button writes its own
    File::creating(function (File $file) use ($mail, &$raced): void {
        if (! $raced && $file->mail_key === AttachmentRules::mailKey($mail->event_id, -1)) {
            $raced = true;
            File::factory()->create(['family_id' => $mail->family_id, 'mail_key' => $file->mail_key]);
        }
    });

    $this->actingAs($this->marco)->from(route('mail.show', $mail))->post(route('mail.notice', $mail))
        ->assertRedirect(route('mail.show', $mail))
        ->assertSessionHasErrors(['notice' => 'The notice is already in To sort']);

    expect($raced)->toBeTrue();
    Queue::assertNothingPushed();
});

test('save as notice: a mail that saved its files has no notice to save', function (): void {
    $mail = boardMails()['tari'];

    $this->actingAs($this->marco)->from(route('mail.show', $mail))->post(route('mail.notice', $mail))
        ->assertSessionHasErrors(['notice' => 'This mail has no notice to save.']);

    $this->actingAs($this->laura)->post(route('mail.notice', $mail))->assertForbidden();
});

test('a mail of another family answers 404 everywhere, also with a body that is not valid', function (): void {
    $other = familyWithMailAddress('Rossi', 'anna@example.com', 'rossi.a1b2c3@bollette.familymanager.test');
    $mail = insideFamilyOf($other, fn (): ReceivedMail => ReceivedMail::factory()->create(['family_id' => $other->family_id, 'outcome' => MailOutcome::Held, 'body' => ['plainText' => str_repeat('Testo. ', 30), 'html' => null]]));

    $this->actingAs($this->marco)->get(route('mail.show', $mail))->assertNotFound();
    $this->actingAs($this->marco)->get(route('mail.text', $mail))->assertNotFound();
    $this->actingAs($this->marco)->delete(route('mail.destroy', $mail), ['junk' => ['x' => str_repeat('y', 9000)]])->assertNotFound();
    $this->actingAs($this->marco)->post(route('mail.notice', $mail), ['junk' => ['x' => str_repeat('y', 9000)]])->assertNotFound();

    expect(insideFamilyOf($other, fn (): bool => ReceivedMail::query()->whereKey($mail->id)->exists()))->toBeTrue()
        ->and(insideFamilyOf($other, fn (): int => File::query()->count()))->toBe(0);
});

/**
 * The label of the row of a mail, as the list writes it.
 */
function receivedMailLabel(?ReceivedMail $mail): string
{
    return ReceivedMailRowData::fromMail($mail ?? throw new RuntimeException('No mail'))->outcome_label;
}

/**
 * A partial reload of the page, as Inertia asks it: only some props.
 *
 * @param  list<string>  $only  the props asked
 */
function partialMailPage(string $url, array $only): TestResponse
{
    return test()->actingAs(test()->marco)->getJson($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(Request::create('/')),
        'X-Inertia-Partial-Component' => 'mail/ReceivedMails',
        'X-Inertia-Partial-Data' => implode(',', $only),
    ])->assertOk();
}

test('the list reads only what its rows need, never the text of the mails', function (): void {
    boardMails();
    $columns = [];
    ReceivedMail::retrieved(function (ReceivedMail $mail) use (&$columns): void {
        $columns = [...$columns, ...array_keys($mail->getAttributes())];
    });

    $this->actingAs($this->marco)->get(route('mail.index'))->assertOk();

    expect($columns)->not->toBeEmpty()
        ->not->toContain('body')
        ->not->toContain('screening');
});

test('reloading the mail open does not read the list, reloading the list does not build the mail', function (): void {
    $mails = boardMails();
    $filed = File::query()->withoutGlobalScopes()->where('received_mail_id', $mails['tari']->id)->sole();
    $filed->forceFill(['to_sort' => false])->save();
    Document::factory()->create(['family_id' => $filed->family_id, 'file_id' => $filed->id]);
    $mailsRead = 0;
    $documentsRead = 0;
    ReceivedMail::retrieved(function () use (&$mailsRead): void {
        $mailsRead++;
    });
    Document::retrieved(function () use (&$documentsRead): void {
        $documentsRead++;
    });

    $mail = partialMailPage(route('mail.show', $mails['tari']), ['mail'])->json('props');
    expect(array_keys($mail))->toContain('mail')->not->toContain('mails')
        ->and($mailsRead)->toBe(1);

    $documentsRead = 0;
    $list = partialMailPage(route('mail.show', $mails['tari']), ['mails'])->json('props');
    expect(array_keys($list))->toContain('mails')->not->toContain('mail')
        ->and($list['mails'])->toHaveCount(6)
        ->and($documentsRead)->toBe(0);
});

test('the text of the mail cannot move the frame elsewhere, and sends no address to the links', function (): void {
    $mail = bianchiMail(['body' => ['plainText' => null, 'html' => '<meta http-equiv="refresh" content="0;url=https://phishing.example"><META HTTP-EQUIV=Refresh CONTENT="1; URL=https://x.example"><p>Ciao</p>']]);

    $response = $this->actingAs($this->marco)->get(route('mail.text', $mail));

    $response->assertHeader('Referrer-Policy', 'no-referrer');
    expect(strtolower((string) $response->getContent()))->not->toContain('refresh')
        ->toContain('<p>ciao</p>');
});

test('a mail that cannot be deleted keeps its notice, on the disk too', function (): void {
    $mail = boardMails()['hera'];
    $notice = File::query()->withoutGlobalScopes()->where('received_mail_id', $mail->id)->sole();
    Storage::disk('local')->put($notice->path, 'Avviso');
    ReceivedMail::deleting(function (): never {
        throw new RuntimeException('The database went away.');
    });

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($this->marco)->delete(route('mail.destroy', $mail)))->toThrow(RuntimeException::class);

    expect(Storage::disk('local')->exists($notice->path))->toBeTrue()
        ->and(insideFamilyOf($this->marco, fn (): bool => File::query()->whereKey($notice->id)->exists()))->toBeTrue();
});

test('deleting a mail takes its notice off the disk', function (): void {
    $mail = boardMails()['hera'];
    $notice = File::query()->withoutGlobalScopes()->where('received_mail_id', $mail->id)->sole();
    Storage::disk('local')->put($notice->path, 'Avviso');

    $this->actingAs($this->marco)->delete(route('mail.destroy', $mail))->assertRedirect(route('mail.index'));

    expect(Storage::disk('local')->exists($notice->path))->toBeFalse();
});
