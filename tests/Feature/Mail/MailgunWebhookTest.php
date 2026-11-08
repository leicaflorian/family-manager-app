<?php

declare(strict_types=1);

use App\Enums\CallOutcome;
use App\Enums\FileSource;
use App\Enums\MailOutcome;
use App\Enums\Role;
use App\Exceptions\Mail\MailFailure;
use App\Jobs\ScreenReceivedMail;
use App\Models\File;
use App\Models\MailInboxCall;
use App\Models\ReceivedMail;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Support\Mail\AttachmentRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MailgunCall;

const BIANCHI_ADDRESS = 'bianchi.k7m2x9@bollette.familymanager.test';

/** A PDF as Mailgun uploads it: only the first bytes matter. */
const BILL_PDF = "%PDF-1.7\n% bolletta Enel di ottobre\n";

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 09:41', 'Europe/Rome'));
    Queue::fake();
    $this->admin = familyWithMailAddress('Bianchi', 'marco@example.com', BIANCHI_ADDRESS);
});

/**
 * The rows of the log of the calls, oldest first.
 *
 * @return list<MailInboxCall>
 */
function mailCalls(): array
{
    return array_values(MailInboxCall::query()->orderBy('id')->get()->all());
}

/**
 * The bill of Enel, delivered to the Bianchi with its PDF.
 */
function enelBill(): MailgunCall
{
    return MailgunCall::to(BIANCHI_ADDRESS)->attach('bolletta_ottobre.pdf', BILL_PDF, 'application/pdf');
}

test('without the signing key the door is closed', function (): void {
    config(['services.mailgun.signing_key' => '']);

    enelBill()->send()->assertNotFound();

    expect(mailCalls())->toBe([]);
});

test('a body bigger than 40 MB is refused with 406 before the body is read', function (): void {
    // signed and with a PDF, but over the limit: the files are never looked at
    $response = enelBill()->contentLength(41 * 1024 * 1024)->send();

    $response->assertStatus(406);
    expect(mailCalls())->toHaveCount(1)
        ->and(mailCalls()[0]->outcome)->toBe(CallOutcome::Invalid)
        ->and(mailCalls()[0]->http_status)->toBe(406)
        ->and(mailCalls()[0]->family_id)->toBeNull()
        ->and(insideFamilyOf($this->admin, fn (): int => File::query()->count()))->toBe(0);
});

test('a body bigger than 40 MB with no signature is still 406, not 401', function (): void {
    enelBill()->unsigned()->contentLength(41 * 1024 * 1024)->send()->assertStatus(406);
});

test('a body that is not a form is refused', function (): void {
    enelBill()->contentType('application/json')->send()->assertUnauthorized();

    expect(mailCalls()[0]->outcome)->toBe(CallOutcome::Rejected)
        ->and(mailCalls()[0]->http_status)->toBe(401);
});

test('a call without signature, too old, too far ahead or with a wrong one is refused', function (MailgunCall $call): void {
    $call->send()->assertUnauthorized();

    expect(mailCalls())->toHaveCount(1)
        ->and(mailCalls()[0]->outcome)->toBe(CallOutcome::Rejected)
        ->and(mailCalls()[0]->payload)->toBe([])
        ->and(insideFamilyOf($this->admin, fn (): int => ReceivedMail::query()->count()))->toBe(0);
})->with([
    'no signature' => fn (): MailgunCall => enelBill()->unsigned(),
    'a day and a second ago' => fn (): MailgunCall => enelBill()->signedAt(now()->getTimestamp() - 86401),
    'a day and a second ahead' => fn (): MailgunCall => enelBill()->signedAt(now()->getTimestamp() + 86401),
    'another key' => fn (): MailgunCall => enelBill()->signedWith('not-the-key'),
]);

test('the refused calls leave at most one row a minute', function (): void {
    enelBill()->unsigned()->send()->assertUnauthorized();
    enelBill()->unsigned()->send()->assertUnauthorized();
    enelBill()->contentLength(41 * 1024 * 1024)->send()->assertStatus(406);
    enelBill()->contentLength(41 * 1024 * 1024)->send()->assertStatus(406);
    expect(mailCalls())->toHaveCount(2);

    $this->travel(61)->seconds();
    enelBill()->unsigned()->send()->assertUnauthorized();

    expect(array_map(fn (MailInboxCall $call): CallOutcome => $call->outcome, mailCalls()))
        ->toBe([CallOutcome::Rejected, CallOutcome::Invalid, CallOutcome::Rejected]);
});

test('a signed call without recipient is answered and logged as other', function (): void {
    MailgunCall::to('')->send()->assertOk();

    expect(mailCalls()[0]->outcome)->toBe(CallOutcome::Other)
        ->and(mailCalls()[0]->family_id)->toBeNull();
});

test('a mail to an address of nobody keeps only the address in the log', function (): void {
    MailgunCall::to('rossi.zzzzzz@bollette.familymanager.test')->subject('Segreto')->send()->assertOk();

    $call = mailCalls()[0];
    expect($call->outcome)->toBe(CallOutcome::Unknown)
        ->and($call->http_status)->toBe(200)
        ->and($call->family_id)->toBeNull()
        ->and($call->event_id)->toBeNull()
        ->and($call->payload)->toBe(['recipient' => 'rossi.zzzzzz@bollette.familymanager.test']);
});

test('the attachments are saved in To sort with where they came from', function (): void {
    enelBill()
        ->attach('logo_enel.png', "\x89PNG\r\n\x1a\n".str_repeat('x', 4000), 'image/png')
        ->send()
        ->assertOk()
        ->assertJson(['saved' => 1, 'skipped' => 1]);

    insideFamilyOf($this->admin, function (): void {
        $mail = ReceivedMail::query()->sole();
        $file = File::query()->sole();

        expect($mail->outcome)->toBe(MailOutcome::Saved)
            ->and($mail->deliveries)->toBe(1)
            ->and($mail->sender_address)->toBe('bollette@enel.it')
            ->and($mail->recipient)->toBe(BIANCHI_ADDRESS)
            ->and($mail->received_at->format('d/m/Y H:i'))->toBe('25/10/2026 09:41')
            ->and($mail->skipped)->toBe([['name' => 'logo_enel.png', 'reason' => 'too_small']])
            ->and($file->original_name)->toBe('bolletta_ottobre.pdf')
            ->and($file->mime_type)->toBe('application/pdf')
            ->and($file->to_sort)->toBeTrue()
            ->and($file->source)->toBe(FileSource::Mail)
            ->and($file->note)->toBe('Arrived by mail on 25/10/2026 from bollette@enel.it: «La tua bolletta di ottobre»')
            ->and($file->received_mail_id)->toBe($mail->id)
            ->and($file->mail_key)->toBe(AttachmentRules::mailKey($mail->event_id, 1));

        Queue::assertPushed(ScreenReceivedMail::class, fn (ScreenReceivedMail $job): bool => $job->mailId === $mail->id && $job->familyId === $this->admin->family_id);
    });

    expect(mailCalls()[0]->outcome)->toBe(CallOutcome::Saved)
        ->and(mailCalls()[0]->saved)->toBe(1)
        ->and(mailCalls()[0]->attachments)->toBe(2)
        ->and(mailCalls()[0]->family_id)->toBe($this->admin->family_id);
});

test('the same file listed twice in a mail is saved once', function (): void {
    enelBill()->attach('bolletta_ottobre.pdf', BILL_PDF, 'application/pdf')->send()->assertOk();

    insideFamilyOf($this->admin, function (): void {
        expect(File::query()->count())->toBe(1)
            ->and(ReceivedMail::query()->sole()->skipped)->toBe([['name' => 'bolletta_ottobre.pdf', 'reason' => 'duplicate']]);
    });
});

test('a mail with no file and a text is held for the screening, and the notice is not saved yet', function (): void {
    $call = MailgunCall::to(BIANCHI_ADDRESS)->subject('La tua fattura è disponibile')
        ->text(str_repeat('La tua fattura di ottobre è disponibile nella tua area clienti. ', 3));

    $call->send()->assertOk();

    insideFamilyOf($this->admin, function (): void {
        expect(ReceivedMail::query()->sole()->outcome)->toBe(MailOutcome::Held)
            ->and(File::query()->count())->toBe(0);
    });
    expect(mailCalls()[0]->outcome)->toBe(CallOutcome::Held);
    Queue::assertPushed(ScreenReceivedMail::class, 1);

    // delivered again: the mail is held already, the question is not asked twice
    $call->send()->assertOk();

    insideFamilyOf($this->admin, function (): void {
        $mail = ReceivedMail::query()->sole();
        expect($mail->outcome)->toBe(MailOutcome::Held)->and($mail->deliveries)->toBe(2);
    });
    expect(mailCalls()[1]->outcome)->toBe(CallOutcome::Empty);
    Queue::assertPushed(ScreenReceivedMail::class, 1);
});

test('a discarded mail delivered again is not screened again', function (): void {
    $call = MailgunCall::to(BIANCHI_ADDRESS)->subject('Offerta imperdibile')->text(str_repeat('Solo per oggi, sconti su tutto! ', 5));
    $call->send()->assertOk();
    insideFamilyOf($this->admin, fn () => ReceivedMail::query()->sole()->update(['outcome' => MailOutcome::Discarded]));

    $call->send()->assertOk();

    insideFamilyOf($this->admin, function (): void {
        expect(ReceivedMail::query()->sole()->outcome)->toBe(MailOutcome::Discarded);
    });
    Queue::assertPushed(ScreenReceivedMail::class, 1);
});

test('a mail with no file and no text is empty and goes no further', function (): void {
    MailgunCall::to(BIANCHI_ADDRESS)->send()->assertOk();

    insideFamilyOf($this->admin, function (): void {
        expect(ReceivedMail::query()->sole()->outcome)->toBe(MailOutcome::Empty);
    });
    expect(mailCalls()[0]->outcome)->toBe(CallOutcome::Empty);
    Queue::assertNotPushed(ScreenReceivedMail::class);
});

test('the confirmation of Gmail keeps its code and saves no file', function (): void {
    MailgunCall::to(BIANCHI_ADDRESS)
        ->from('Gmail Team <forwarding-noreply@google.com>')
        ->subject('(#123456789) Conferma inoltro Gmail - Ricevi posta da marco@gmail.com')
        ->text('Per confermare la richiesta, fai clic sul link qui sotto.')
        ->attach('logo.pdf', BILL_PDF, 'application/pdf')
        ->send()
        ->assertOk();

    insideFamilyOf($this->admin, function (): void {
        $mail = ReceivedMail::query()->sole();
        expect($mail->outcome)->toBe(MailOutcome::Confirmation)
            ->and($mail->gmail_code)->toBe('123456789')
            ->and(File::query()->count())->toBe(0);
    });
    expect(mailCalls()[0]->outcome)->toBe(CallOutcome::Confirmation);
    Queue::assertNotPushed(ScreenReceivedMail::class);
});

test('a failure answers 500 and the delivery after it saves the files once', function (): void {
    // an object, so the listener sees the switch turned off
    $disk = (object) ['full' => true];
    File::creating(function (File $file) use ($disk): void {
        if ($disk->full && $file->original_name === 'dettaglio.pdf') {
            throw new RuntimeException('The disk is full.');
        }
    });
    $call = enelBill()->attach('dettaglio.pdf', BILL_PDF.'dettaglio', 'application/pdf');

    $call->send()->assertStatus(500);

    insideFamilyOf($this->admin, function (): void {
        $mail = ReceivedMail::query()->sole();
        expect($mail->outcome)->toBe(MailOutcome::Error)
            ->and($mail->deliveries)->toBe(1)
            ->and(File::query()->count())->toBe(0);
    });
    expect(Storage::disk('local')->allFiles())->toBe([])
        ->and(mailCalls()[0]->outcome)->toBe(CallOutcome::Error)
        ->and(mailCalls()[0]->http_status)->toBe(500)
        ->and(mailCalls()[0]->error)->toBe('RuntimeException: The disk is full.');
    Queue::assertNotPushed(ScreenReceivedMail::class);

    $disk->full = false;
    $call->send()->assertOk();

    insideFamilyOf($this->admin, function (): void {
        $mail = ReceivedMail::query()->sole();
        expect($mail->outcome)->toBe(MailOutcome::Saved)
            ->and($mail->deliveries)->toBe(2)
            ->and(File::query()->pluck('original_name')->sort()->values()->all())->toBe(['bolletta_ottobre.pdf', 'dettaglio.pdf']);
    });
    expect(Storage::disk('local')->allFiles())->toHaveCount(2)
        ->and(mailCalls()[1]->outcome)->toBe(CallOutcome::Saved)
        ->and(mailCalls()[1]->received_mail_id)->toBe(mailCalls()[0]->received_mail_id);
    Queue::assertPushed(ScreenReceivedMail::class, 1);
});

test('a delivery repeated after a success finds its files and saves nothing', function (): void {
    $call = enelBill();
    $call->send()->assertOk();

    $call->send()->assertOk()->assertJson(['saved' => 0]);

    insideFamilyOf($this->admin, function (): void {
        $mail = ReceivedMail::query()->sole();
        expect(File::query()->count())->toBe(1)
            ->and($mail->deliveries)->toBe(2)
            ->and($mail->outcome)->toBe(MailOutcome::Saved);
    });
    expect(mailCalls()[1]->outcome)->toBe(CallOutcome::Empty);
    Queue::assertPushed(ScreenReceivedMail::class, 1);
});

test('the 101st mail of the day is limited and tells the admins once', function (): void {
    $laura = User::factory()->create(['family_id' => $this->admin->family_id, 'role' => Role::Admin]);
    $giulia = User::factory()->create(['family_id' => $this->admin->family_id, 'role' => Role::Member]);
    insideFamilyOf($this->admin, function (): void {
        // yesterday's mails, the last at 23:30 of Rome, do not count
        ReceivedMail::factory()->count(5)->create(['family_id' => $this->admin->family_id, 'received_at' => CarbonImmutable::parse('2026-10-24 23:30', 'Europe/Rome')]);
        ReceivedMail::factory()->count(99)->create(['family_id' => $this->admin->family_id, 'received_at' => today()->setTime(0, 5)]);
    });

    enelBill()->send()->assertOk();
    expect(mailCalls()[0]->outcome)->toBe(CallOutcome::Saved);

    enelBill()->send()->assertOk();
    enelBill()->send()->assertOk();

    insideFamilyOf($this->admin, function () use ($laura, $giulia): void {
        expect(ReceivedMail::query()->count())->toBe(105)
            ->and(File::query()->count())->toBe(1);

        $notices = ReminderNotice::query()->orderBy('user_id')->get();
        expect($notices->pluck('user_id')->all())->toBe([$this->admin->id, $laura->id])
            ->and($notices->pluck('user_id')->all())->not->toContain($giulia->id)
            ->and($notices[0]->reminder_id)->toBeNull()
            ->and($notices[0]->received_mail_id)->toBeNull()
            ->and($notices[0]->lines)->toBe(['More than 100 mails arrived today: the others are not saved. If they are spam, change the address.']);
    });
    expect(mailCalls()[1]->outcome)->toBe(CallOutcome::Limited)
        ->and(mailCalls()[1]->http_status)->toBe(200)
        ->and(mailCalls()[2]->outcome)->toBe(CallOutcome::Limited);
});

test('over the limit a mail already received is still delivered again', function (): void {
    $call = enelBill();
    $call->send()->assertOk();
    insideFamilyOf($this->admin, function (): void {
        ReceivedMail::factory()->count(100)->create(['family_id' => $this->admin->family_id, 'received_at' => today()->setTime(0, 5)]);
    });

    $call->send()->assertOk();

    expect(mailCalls()[1]->outcome)->toBe(CallOutcome::Empty);
});

test('the door opens no session and sets no cookie', function (): void {
    $response = enelBill()->send()->assertOk();

    expect($response->headers->get('Set-Cookie'))->toBeNull();
});

test('a failure of the database keeps the text of the mail out of the log of the calls and of the app', function (): void {
    Exceptions::fake();
    // MySQL refuses a subject longer than its TEXT column: the error of the query holds every value of the insert
    $call = MailgunCall::to(BIANCHI_ADDRESS)->subject(str_repeat('Oggetto lunghissimo ', 4000))
        ->text('PAROLA-SEGRETA del corpo della mail, che non deve finire nel registro.');

    $call->send()->assertStatus(500);

    $error = (string) mailCalls()[0]->error;
    expect(mailCalls()[0]->outcome)->toBe(CallOutcome::Error)
        ->and(mailCalls()[0]->http_status)->toBe(500)
        ->and($error)->toStartWith('QueryException: SQLSTATE[22001]')
        ->and($error)->toContain("Data too long for column 'subject'")
        ->and($error)->not->toContain('PAROLA-SEGRETA')
        ->and($error)->not->toContain('Oggetto lunghissimo')
        ->and(mb_strlen($error))->toBeLessThanOrEqual(300);
    Exceptions::assertNotReported(QueryException::class);
    Exceptions::assertReported(fn (MailFailure $reported): bool => ! str_contains($reported->getMessage(), 'PAROLA-SEGRETA')
        && ! str_contains($reported->getMessage(), 'Oggetto lunghissimo')
        && ! $reported->getPrevious() instanceof Throwable);
});

test('an attachment bigger than the upload limit of PHP is left out as too large', function (): void {
    enelBill()->attachBroken('scansione_enorme.pdf', UPLOAD_ERR_INI_SIZE)->send()->assertOk();

    insideFamilyOf($this->admin, function (): void {
        expect(ReceivedMail::query()->sole()->skipped)->toBe([['name' => 'scansione_enorme.pdf', 'reason' => 'too_large']])
            ->and(File::query()->count())->toBe(1);
    });
});

test('an upload that failed on the server answers 500, so Mailgun delivers again', function (int $error): void {
    enelBill()->attachBroken('dettaglio.pdf', $error)->send()->assertStatus(500);

    insideFamilyOf($this->admin, function (): void {
        expect(File::query()->count())->toBe(0)
            ->and(ReceivedMail::query()->sole()->outcome)->toBe(MailOutcome::Error);
    });
    expect(mailCalls()[0]->outcome)->toBe(CallOutcome::Error)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['no temporary folder' => UPLOAD_ERR_NO_TMP_DIR, 'partial' => UPLOAD_ERR_PARTIAL, 'cannot write' => UPLOAD_ERR_CANT_WRITE]);

test('the limit counts the day of Rome, not the one of UTC', function (): void {
    // 00:30 of Rome on 25/10/2026 is still 24/10/2026 in UTC
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 00:30', 'Europe/Rome'));
    insideFamilyOf($this->admin, function (): void {
        ReceivedMail::factory()->count(100)->create(['family_id' => $this->admin->family_id, 'received_at' => CarbonImmutable::parse('2026-10-24 23:50', 'Europe/Rome')]);
    });

    enelBill()->send()->assertOk();
    expect(mailCalls()[0]->outcome)->toBe(CallOutcome::Saved);

    insideFamilyOf($this->admin, function (): void {
        ReceivedMail::factory()->count(99)->create(['family_id' => $this->admin->family_id, 'received_at' => CarbonImmutable::parse('2026-10-25 00:10', 'Europe/Rome')]);
    });
    enelBill()->send()->assertOk();

    expect(mailCalls()[1]->outcome)->toBe(CallOutcome::Limited);
});

test('a log of the calls that cannot be written does not change the answer', function (): void {
    Exceptions::fake();
    MailInboxCall::creating(fn () => throw new RuntimeException('The log is gone.'));

    enelBill()->send()->assertOk()->assertJson(['saved' => 1]);

    insideFamilyOf($this->admin, function (): void {
        expect(ReceivedMail::query()->sole()->outcome)->toBe(MailOutcome::Saved)
            ->and(File::query()->count())->toBe(1);
    });
    expect(MailInboxCall::query()->count())->toBe(0);
    Exceptions::assertReported(fn (MailFailure $reported): bool => $reported->getMessage() === 'RuntimeException: The log is gone.');
});
