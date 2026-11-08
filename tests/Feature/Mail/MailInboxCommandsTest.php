<?php

declare(strict_types=1);

use App\Enums\CallOutcome;
use App\Enums\MailOutcome;
use App\Jobs\ScreenReceivedMail;
use App\Models\File;
use App\Models\MailInboxCall;
use App\Models\ReceivedMail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File as Disk;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * The two commands of the mail inbox: `mail-inbox:try`, which delivers a mail to the app without Mailgun (only on a
 * local machine), and `mail-inbox:calls`, the log of the owner, which never prints the text or the payload of a call.
 */

const COMMAND_ADDRESS = 'bianchi.k7m2x9@bollette.familymanager.test';

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 09:41', 'Europe/Rome'));
    Queue::fake();
    $this->admin = familyWithMailAddress('Bianchi', 'marco@example.com', COMMAND_ADDRESS);
    $this->pdf = tempnam(sys_get_temp_dir(), 'fm-try-').'.pdf';
    file_put_contents($this->pdf, "%PDF-1.7\n".str_repeat('bolletta ', 200));
});

afterEach(function (): void {
    Disk::delete($this->pdf);
});

/**
 * Pretends the app runs on a local machine.
 */
function runningLocally(): void
{
    app()->detectEnvironment(static fn (): string => 'local');
}

test('mail-inbox:try puts the PDF in To sort and the mail in the received mails', function (): void {
    runningLocally();

    $this->artisan('mail-inbox:try', ['address' => COMMAND_ADDRESS, '--subject' => 'Bolletta gas', '--file' => [$this->pdf]])
        ->expectsOutputToContain('200')
        ->assertSuccessful();

    insideFamilyOf($this->admin, function (): void {
        $mail = ReceivedMail::query()->sole();
        $file = File::query()->sole();

        expect($mail->subject)->toBe('Bolletta gas')
            ->and($mail->outcome)->toBe(MailOutcome::Saved)
            ->and($file->to_sort)->toBeTrue()
            ->and($file->received_mail_id)->toBe($mail->id)
            ->and(Storage::disk('local')->exists($file->path))->toBeTrue();
    });
    Queue::assertPushed(ScreenReceivedMail::class);
});

test('mail-inbox:try works with the signing key of the installation, or with one made for the call', function (?string $key): void {
    runningLocally();
    config(['services.mailgun.signing_key' => $key]);

    $this->artisan('mail-inbox:try', ['address' => COMMAND_ADDRESS, '--file' => [$this->pdf]])->assertSuccessful();

    expect(MailInboxCall::query()->sole()->outcome)->toBe(CallOutcome::Saved)
        ->and(config('services.mailgun.signing_key'))->toBe($key);
})->with(['the configured key' => ['a-key-of-the-installation'], 'no key' => [null]]);

test('mail-inbox:try delivers every time as a new mail', function (): void {
    runningLocally();

    $this->artisan('mail-inbox:try', ['address' => COMMAND_ADDRESS, '--file' => [$this->pdf]])->assertSuccessful();
    $this->artisan('mail-inbox:try', ['address' => COMMAND_ADDRESS, '--file' => [$this->pdf]])->assertSuccessful();

    expect(insideFamilyOf($this->admin, fn (): int => ReceivedMail::query()->count()))->toBe(2);
});

test('mail-inbox:try stops when the app is not local', function (): void {
    $this->artisan('mail-inbox:try', ['address' => COMMAND_ADDRESS, '--file' => [$this->pdf]])
        ->expectsOutputToContain('only on a local machine')
        ->assertFailed();

    expect(MailInboxCall::query()->count())->toBe(0);
});

test('mail-inbox:try stops when a file is missing', function (): void {
    runningLocally();

    $this->artisan('mail-inbox:try', ['address' => COMMAND_ADDRESS, '--file' => ['/nowhere/missing.pdf']])
        ->expectsOutputToContain('missing.pdf')
        ->assertFailed();

    expect(MailInboxCall::query()->count())->toBe(0);
});

test('mail-inbox:try shows an address of nobody as unknown', function (): void {
    runningLocally();

    $this->artisan('mail-inbox:try', ['address' => 'nobody@bollette.familymanager.test'])->expectsOutputToContain('Unknown address')->assertSuccessful();

    expect(MailInboxCall::query()->sole()->outcome)->toBe(CallOutcome::Unknown);
});

/**
 * Three calls of different days, outcomes and families.
 */
function loggedCalls(int $familyId): void
{
    MailInboxCall::factory()->create(['outcome' => CallOutcome::Saved, 'http_status' => 200, 'family_id' => $familyId, 'attachments' => 2, 'saved' => 1, 'payload' => ['subject' => 'SECRET SUBJECT'], 'error' => null, 'created_at' => now()]);
    MailInboxCall::factory()->create(['outcome' => CallOutcome::Rejected, 'http_status' => 401, 'family_id' => null, 'attachments' => 0, 'saved' => 0, 'payload' => [], 'created_at' => now()->subDays(10)]);
    MailInboxCall::factory()->create(['outcome' => CallOutcome::Unknown, 'http_status' => 200, 'family_id' => null, 'attachments' => 0, 'saved' => 0, 'payload' => ['recipient' => 'x@y.test'], 'created_at' => now()->subDays(2)]);
}

/**
 * Runs mail-inbox:calls and returns what it printed.
 *
 * @param  array<string, string>  $options  the options of the command
 */
function callsPrinted(array $options = []): string
{
    expect(Artisan::call('mail-inbox:calls', $options))->toBe(0);

    return Artisan::output();
}

test('mail-inbox:calls prints the table, with the family name and never the payload', function (): void {
    loggedCalls($this->admin->family_id);

    expect(callsPrinted())->toContain('Bianchi', '25/10/2026 09:41', 'rejected', 'unknown')
        ->not->toContain('SECRET SUBJECT')
        ->not->toContain('x@y.test');
});

test('mail-inbox:calls filters by outcome, family and date', function (): void {
    loggedCalls($this->admin->family_id);

    expect(callsPrinted(['--outcome' => 'rejected']))->toContain('401')->not->toContain('Bianchi')
        ->and(callsPrinted(['--family' => (string) $this->admin->family_id]))->toContain('Bianchi')->not->toContain('401')
        ->and(callsPrinted(['--since' => '2026-10-22']))->toContain('Bianchi', 'unknown')->not->toContain('401');
});

test('mail-inbox:calls refuses an outcome or a date it does not know', function (): void {
    $this->artisan('mail-inbox:calls', ['--outcome' => 'banana'])->expectsOutputToContain('banana')->assertFailed();
    $this->artisan('mail-inbox:calls', ['--since' => 'not a date'])->expectsOutputToContain('not a date')->assertFailed();
});

test('mail-inbox:calls says so when there is nothing to show', function (): void {
    $this->artisan('mail-inbox:calls')->expectsOutputToContain('No calls')->assertSuccessful();
});
