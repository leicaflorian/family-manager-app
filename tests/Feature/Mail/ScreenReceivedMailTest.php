<?php

declare(strict_types=1);

use App\Contracts\DocumentReader;
use App\Data\Mail\MailScreeningAnswer;
use App\Data\Reading\Extraction;
use App\Enums\FileSource;
use App\Enums\MailOutcome;
use App\Enums\ReadingPurpose;
use App\Enums\ReadingStatus;
use App\Enums\Role;
use App\Exceptions\Reading\ReadingFailed;
use App\Jobs\ScreenReceivedMail;
use App\Models\AiReading;
use App\Models\FamilySetting;
use App\Models\File;
use App\Models\Organization;
use App\Models\ReceivedMail;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\Mail\AttachmentRules;
use Carbon\CarbonImmutable;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeDocumentReader;
use Tests\Support\MailgunCall;

const SCREEN_ADDRESS = 'bianchi.k7m2x9@bollette.familymanager.test';

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 09:41', 'Europe/Rome'));
    // The jobs wait in the `jobs` table, as online, and a worker runs them
    config(['queue.default' => 'database']);
    $this->admin = familyWithMailAddress('Bianchi', 'marco@example.com', SCREEN_ADDRESS);
    $this->reader = (new FakeDocumentReader)->returns(Extraction::from([
        'kind' => 'utility_bill', 'confidence' => 'high', 'issuer_name' => 'Edison', 'total_amount' => 84.2, 'due_date' => '2026-10-30',
    ]));
    app()->instance(DocumentReader::class, $this->reader);
});

/**
 * Runs the jobs waiting in the queue, one at a time, until there are none left. One at a time because a worker
 * that finds the memory of the whole suite above its limit stops after the first job.
 */
function workMailQueue(): void
{
    for ($run = 0; $run < 50 && DB::table('jobs')->exists(); $run++) {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true]);
    }
}

/**
 * A PDF the reading can open, with a mark that makes each one a different file.
 *
 * @param  string  $mark  what tells it apart
 */
function screenPdf(string $mark = ''): string
{
    return file_get_contents(base_path('tests/Fixtures/one-page.pdf'))."\n%{$mark}\n";
}

/**
 * A mail without attachments whose text is long enough to be a notice.
 *
 * @param  string  $subject  the subject
 * @param  string  $from  the sender
 */
function heldNoticeCall(string $subject = 'La tua fattura è disponibile', string $from = 'Hera <bollette@hera.example>'): MailgunCall
{
    return MailgunCall::to(SCREEN_ADDRESS)->from($from)->subject($subject)
        ->text(str_repeat('La tua fattura di ottobre è disponibile nella tua area clienti. ', 3));
}

/**
 * The only mail of the Bianchi, read in their family.
 */
function screenedMail(User $admin): ReceivedMail
{
    return insideFamilyOf($admin, fn (): ReceivedMail => ReceivedMail::query()->sole());
}

/**
 * The notices of the bell of the Bianchi.
 *
 * @return list<ReminderNotice>
 */
function mailNotices(User $admin): array
{
    return insideFamilyOf($admin, fn (): array => array_values(ReminderNotice::query()->orderBy('id')->get()->all()));
}

/**
 * The files of the Bianchi, oldest first.
 *
 * @return list<File>
 */
function mailFiles(User $admin): array
{
    return insideFamilyOf($admin, fn (): array => array_values(File::query()->orderBy('id')->get()->all()));
}

test('an advertisement without attachments is discarded, with no file and no notice', function (): void {
    $this->reader->screens(new MailScreeningAnswer('discard', 'offerta commerciale'));

    heldNoticeCall('Nuove offerte luce e gas', 'promo@offerte-energia.it')->send()->assertOk();
    workMailQueue();

    $mail = screenedMail($this->admin);
    expect($mail->outcome)->toBe(MailOutcome::Discarded)
        ->and($mail->notes)->toBe(['discarded: offerta commerciale'])
        ->and(mailFiles($this->admin))->toBe([])
        ->and(mailNotices($this->admin))->toBe([])
        ->and($this->reader->reads())->toBe(0);
});

test('a mail to discard with attachments keeps its files unread, with the note', function (): void {
    $this->reader->screens(new MailScreeningAnswer('discard', 'offerta commerciale'));

    MailgunCall::to(SCREEN_ADDRESS)->from('promo@offerte-energia.it')->subject('Offerta')
        ->attach('listino.pdf', screenPdf(), 'application/pdf')->send()->assertOk();
    workMailQueue();

    $mail = screenedMail($this->admin);
    $files = mailFiles($this->admin);
    expect($mail->outcome)->toBe(MailOutcome::Saved)
        ->and($mail->notes)->toBe(['not read: offerta commerciale'])
        ->and($files)->toHaveCount(1)
        ->and($files[0]->reading_status)->toBeNull()
        ->and($this->reader->reads())->toBe(0)
        ->and(mailNotices($this->admin)[0]->lines)->toBe(['listino.pdf']);
});

test('a held notice is saved in To sort and read', function (): void {
    // the families start with only the mails with attachments wanted
    insideFamilyOf($this->admin, fn () => FamilySetting::query()->sole()->forceFill(['mail_inbox_notify_attachments_only' => false])->save());

    heldNoticeCall()->send()->assertOk();
    workMailQueue();

    $mail = screenedMail($this->admin);
    $files = mailFiles($this->admin);
    expect($mail->outcome)->toBe(MailOutcome::Saved)
        ->and($files)->toHaveCount(1)
        ->and($files[0]->original_name)->toBe('Notice - La tua fattura è disponibile.txt')
        ->and($files[0]->mime_type)->toBe('text/plain')
        ->and($files[0]->to_sort)->toBeTrue()
        ->and($files[0]->source)->toBe(FileSource::Mail)
        ->and($files[0]->note)->toBe('Arrived by mail on 25/10/2026 from bollette@hera.example: «La tua fattura è disponibile»')
        ->and($files[0]->received_mail_id)->toBe($mail->id)
        ->and($files[0]->mail_key)->toBe(AttachmentRules::mailKey($mail->event_id, -1))
        ->and($mail->saved_files)->toBe([['key' => $files[0]->mail_key, 'name' => 'Notice - La tua fattura è disponibile.txt']])
        ->and($files[0]->reading_status)->toBe(ReadingStatus::Done)
        ->and(insideFamilyOf($this->admin, fn (): array => AiReading::query()->whereNotNull('file_id')->pluck('purpose')->all()))->toBe([ReadingPurpose::MailNotice])
        ->and(mailNotices($this->admin)[0]->lines)->toBe(["Notice - La tua fattura è disponibile.txt — Edison, 84,20\u{A0}€, due on 30/10/2026"]);
});

test('at most 5 files of a mail are read, the PDFs first', function (): void {
    $call = MailgunCall::to(SCREEN_ADDRESS)->subject('Sette bollette');
    foreach (range(1, 7) as $n) {
        $call->attach("bolletta_{$n}.pdf", screenPdf((string) $n), 'application/pdf');
    }

    $call->send()->assertOk();
    workMailQueue();

    $files = mailFiles($this->admin);
    expect($this->reader->reads())->toBe(5)
        ->and(array_map(fn (File $file): ?ReadingStatus => $file->reading_status, $files))
        ->toBe([...array_fill(0, 5, ReadingStatus::Done), null, null])
        ->and(insideFamilyOf($this->admin, fn (): int => AiReading::query()->where('purpose', ReadingPurpose::MailFile)->count()))->toBe(5);
});

test('a failed reading does not stop the others, and the note says which file', function (): void {
    $this->reader->failsAt(2, ReadingFailed::refused());

    MailgunCall::to(SCREEN_ADDRESS)->subject('Tre bollette')
        ->attach('a.pdf', screenPdf('a'), 'application/pdf')
        ->attach('b.pdf', screenPdf('b'), 'application/pdf')
        ->attach('c.pdf', screenPdf('c'), 'application/pdf')
        ->send()->assertOk();
    workMailQueue();

    $files = mailFiles($this->admin);
    expect($this->reader->reads())->toBe(3)
        ->and(array_map(fn (File $file): ?ReadingStatus => $file->reading_status, $files))
        ->toBe([ReadingStatus::Done, ReadingStatus::Failed, ReadingStatus::Done])
        ->and(screenedMail($this->admin)->notes)->toBe(['AI reading failed: b.pdf: The model refused to read the document.'])
        ->and(mailNotices($this->admin))->toHaveCount(1)
        ->and(mailNotices($this->admin)[0]->lines)->toBe([
            "a.pdf — Edison, 84,20\u{A0}€, due on 30/10/2026",
            'b.pdf',
            "c.pdf — Edison, 84,20\u{A0}€, due on 30/10/2026",
        ]);
});

test('one notice per mail, with the lines of its files, only to the chosen accounts', function (): void {
    $laura = User::factory()->create(['family_id' => $this->admin->family_id, 'role' => Role::Member]);
    User::factory()->create(['family_id' => $this->admin->family_id, 'role' => Role::Admin]);
    insideFamilyOf($this->admin, fn () => FamilySetting::query()->sole()->forceFill(['mail_inbox_notify' => [$laura->id]])->save());

    MailgunCall::to(SCREEN_ADDRESS)->subject('La tua bolletta')
        ->attach('bolletta_ottobre.pdf', screenPdf(), 'application/pdf')->send()->assertOk();
    workMailQueue();

    $notices = mailNotices($this->admin);
    expect($notices)->toHaveCount(1)
        ->and($notices[0]->user_id)->toBe($laura->id)
        ->and($notices[0]->received_mail_id)->toBe(screenedMail($this->admin)->id)
        ->and($notices[0]->reminder_id)->toBeNull()
        ->and($notices[0]->sent_at->equalTo(now()))->toBeTrue()
        ->and($notices[0]->lines)->toBe(["bolletta_ottobre.pdf — Edison, 84,20\u{A0}€, due on 30/10/2026"]);
});

test('with only the mails with attachments wanted, a notice of text tells nobody', function (): void {
    insideFamilyOf($this->admin, fn () => FamilySetting::query()->sole()->forceFill(['mail_inbox_notify_attachments_only' => true])->save());

    heldNoticeCall()->send()->assertOk();
    workMailQueue();

    expect(mailFiles($this->admin))->toHaveCount(1)
        ->and(mailNotices($this->admin))->toBe([]);
});

test('the confirmation of Gmail tells the chosen accounts even with only the mails with attachments wanted', function (): void {
    $laura = User::factory()->create(['family_id' => $this->admin->family_id, 'role' => Role::Member]);
    insideFamilyOf($this->admin, fn () => FamilySetting::query()->sole()->forceFill([
        'mail_inbox_notify_attachments_only' => true,
        'mail_inbox_notify' => [$this->admin->id, $laura->id],
    ])->save());

    MailgunCall::to(SCREEN_ADDRESS)
        ->from('Gmail Team <forwarding-noreply@google.com>')
        ->subject('(#123456789) Conferma inoltro Gmail - Ricevi posta da marco@gmail.com')
        ->text('Per confermare la richiesta, fai clic sul link qui sotto.')
        ->send()->assertOk();
    workMailQueue();

    $notices = mailNotices($this->admin);
    expect(array_map(fn (ReminderNotice $notice): int => $notice->user_id, $notices))->toBe([$this->admin->id, $laura->id])
        ->and($notices[0]->lines)->toBe(['Gmail confirmation code: 123456789'])
        ->and($this->reader->screenings())->toBe(0)
        ->and($this->reader->reads())->toBe(0)
        ->and(mailFiles($this->admin))->toBe([]);
});

test('with the reading off nothing is asked, the files stay off and the notice lists them', function (): void {
    $this->reader->off();

    MailgunCall::to(SCREEN_ADDRESS)->subject('La tua bolletta')
        ->attach('bolletta_ottobre.pdf', screenPdf(), 'application/pdf')->send()->assertOk();
    workMailQueue();

    expect($this->reader->screenings())->toBe(0)
        ->and(mailFiles($this->admin)[0]->reading_status)->toBe(ReadingStatus::Off)
        ->and(mailNotices($this->admin)[0]->lines)->toBe(['bolletta_ottobre.pdf']);
});

test('the job works in the family of the mail even with another family set, and nothing reaches the other', function (): void {
    $other = familyWithMailAddress('Rossi', 'anna@example.com', 'rossi.a1b2c3@bollette.familymanager.test');
    MailgunCall::to(SCREEN_ADDRESS)->subject('La tua bolletta')
        ->attach('bolletta_ottobre.pdf', screenPdf(), 'application/pdf')->send()->assertOk();

    // the job runs here, with the other family set around it (a worker would start every job without one)
    DB::table('jobs')->delete();
    CurrentFamily::set($other->family_id);
    app()->call([new ScreenReceivedMail(screenedMail($this->admin)->id, $this->admin->family_id), 'handle']);

    expect(CurrentFamily::id())->toBe($other->family_id);
    CurrentFamily::clear();
    workMailQueue();

    $heldBy = fn (User $user): array => insideFamilyOf($user, fn (): array => [
        'notices' => ReminderNotice::query()->count(),
        'readings' => AiReading::query()->count(),
        'files' => File::query()->count(),
    ]);

    // two calls of the AI: the question about the mail and the reading of its file
    expect($heldBy($this->admin))->toBe(['notices' => 1, 'readings' => 2, 'files' => 1])
        ->and($heldBy($other))->toBe(['notices' => 0, 'readings' => 0, 'files' => 0]);
});

/**
 * The calls of the AI of the Bianchi made for a purpose.
 *
 * @param  User  $admin  an account of the family
 * @param  ReadingPurpose  $purpose  what the calls were for
 */
function aiCalls(User $admin, ReadingPurpose $purpose): int
{
    return insideFamilyOf($admin, fn (): int => AiReading::query()->where('purpose', $purpose)->count());
}

test('a try that fails after the notice was saved is retried without asking again or saving the notice twice', function (): void {
    insideFamilyOf($this->admin, fn () => FamilySetting::query()->sole()->forceFill(['mail_inbox_notify_attachments_only' => false])->save());
    // the first try breaks when the notice is put in the queue of the readings, after the question and the saving
    $broken = (object) ['once' => true];
    File::updating(function (File $file) use ($broken): void {
        if ($broken->once && $file->reading_status === ReadingStatus::Queued) {
            $broken->once = false;

            throw new RuntimeException('The queue is down.');
        }
    });

    heldNoticeCall()->send()->assertOk();
    workMailQueue();

    $files = mailFiles($this->admin);
    expect($broken->once)->toBeFalse()
        ->and($this->reader->screenings())->toBe(1)
        ->and(aiCalls($this->admin, ReadingPurpose::MailFilter))->toBe(1)
        ->and($files)->toHaveCount(1)
        ->and($files[0]->reading_status)->toBe(ReadingStatus::Done)
        ->and(aiCalls($this->admin, ReadingPurpose::MailNotice))->toBe(1)
        ->and(aiCalls($this->admin, ReadingPurpose::MailFile))->toBe(0)
        ->and(screenedMail($this->admin)->outcome)->toBe(MailOutcome::Saved)
        ->and(screenedMail($this->admin)->saved_files)->toHaveCount(1)
        ->and(screenedMail($this->admin)->screening)->toEqual(['verdict' => 'unsure', 'reason' => null])
        ->and(mailNotices($this->admin))->toHaveCount(1);
});

test('a try that fails after the note was written is retried without asking again or writing the note twice', function (): void {
    $this->reader->screens(new MailScreeningAnswer('discard', 'offerta commerciale'));
    $broken = (object) ['once' => true];
    ReminderNotice::creating(function () use ($broken): void {
        if ($broken->once) {
            $broken->once = false;

            throw new RuntimeException('The bell is down.');
        }
    });

    MailgunCall::to(SCREEN_ADDRESS)->from('promo@offerte-energia.it')->subject('Offerta')
        ->attach('listino.pdf', screenPdf(), 'application/pdf')->send()->assertOk();
    workMailQueue();

    expect($broken->once)->toBeFalse()
        ->and(aiCalls($this->admin, ReadingPurpose::MailFilter))->toBe(1)
        ->and(screenedMail($this->admin)->notes)->toBe(['not read: offerta commerciale'])
        ->and(mailFiles($this->admin))->toHaveCount(1)
        ->and(mailNotices($this->admin))->toHaveCount(1);
});

test('when the readings cannot be sent, the files go back unread and the retry reads them', function (): void {
    $repository = resolve(BatchRepository::class);
    $broken = (object) ['once' => true];
    $mock = Mockery::mock($repository)->makePartial();
    $mock->shouldReceive('store')->andReturnUsing(function ($batch) use ($repository, $broken) {
        if ($broken->once) {
            $broken->once = false;

            throw new RuntimeException('The batches are down.');
        }

        return $repository->store($batch);
    });
    app()->instance(BatchRepository::class, $mock);

    MailgunCall::to(SCREEN_ADDRESS)->subject('La tua bolletta')
        ->attach('bolletta_ottobre.pdf', screenPdf(), 'application/pdf')->send()->assertOk();
    workMailQueue();

    expect($broken->once)->toBeFalse()
        ->and(mailFiles($this->admin)[0]->reading_status)->toBe(ReadingStatus::Done)
        ->and($this->reader->reads())->toBe(1)
        ->and(mailNotices($this->admin)[0]->lines)->toBe(["bolletta_ottobre.pdf — Edison, 84,20\u{A0}€, due on 30/10/2026"]);
});

test('a known sender is not asked about, and the mail takes its organization', function (): void {
    $hera = insideFamilyOf($this->admin, fn (): Organization => Organization::factory()->create(['family_id' => $this->admin->family_id, 'name' => 'Hera', 'email' => 'clienti@hera.example']));

    MailgunCall::to(SCREEN_ADDRESS)->from('Hera <bollette@fatture.hera.example>')->subject('La tua bolletta')
        ->attach('bolletta_ottobre.pdf', screenPdf(), 'application/pdf')->send()->assertOk();
    workMailQueue();

    expect($this->reader->screenings())->toBe(0)
        ->and(screenedMail($this->admin)->organization_id)->toBe($hera->id)
        ->and(mailFiles($this->admin)[0]->reading_status)->toBe(ReadingStatus::Done);
});

test('a notice saved by hand before the job leaves the job nothing to do', function (): void {
    heldNoticeCall()->send()->assertOk();
    $mail = screenedMail($this->admin);
    // *Salva come avviso* saved the notice, with the key of the position -1
    insideFamilyOf($this->admin, fn (): File => File::factory()->create([
        'family_id' => $this->admin->family_id, 'received_mail_id' => $mail->id, 'mail_key' => AttachmentRules::mailKey($mail->event_id, -1),
    ]));

    workMailQueue();

    expect($this->reader->screenings())->toBe(0)
        ->and($this->reader->reads())->toBe(0)
        ->and(mailFiles($this->admin))->toHaveCount(1)
        ->and(mailNotices($this->admin))->toBe([])
        ->and(screenedMail($this->admin)->outcome)->toBe(MailOutcome::Held);
});

test('a question the AI cannot answer counts as unsure, and the notice is saved', function (): void {
    $this->reader->fails(ReadingFailed::refused());

    heldNoticeCall()->send()->assertOk();
    workMailQueue();

    $mail = screenedMail($this->admin);
    expect($this->reader->screenings())->toBe(1)
        ->and($mail->outcome)->toBe(MailOutcome::Saved)
        ->and($mail->screening)->toEqual(['verdict' => 'unsure', 'reason' => null])
        ->and(mailFiles($this->admin))->toHaveCount(1)
        ->and($mail->notes)->toBe(['AI reading failed: Notice - La tua fattura è disponibile.txt: The model refused to read the document.']);
});
