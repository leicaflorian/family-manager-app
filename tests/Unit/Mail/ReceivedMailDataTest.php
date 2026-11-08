<?php

declare(strict_types=1);

use App\Data\ReceivedMailRowData;
use App\Enums\MailOutcome;
use App\Support\Mail\MailFlow;

beforeEach(function (): void {
    // The values are those of mails.test.ts in Directus, in Italian
    app()->setLocale('it');
});

/**
 * A file saved from the mail, there or gone.
 *
 * @return array{notice: bool, gone: bool}
 */
function savedFile(bool $gone = false, bool $notice = false): array
{
    return ['notice' => $notice, 'gone' => $gone];
}

/**
 * The label of a mail, as the row writes it.
 *
 * @param  list<array{notice: bool, gone: bool}>  $files
 */
function label(MailOutcome $outcome, array $files = [], int $deliveries = 1): string
{
    return ReceivedMailRowData::outcome($outcome, $deliveries, $files)['label'];
}

test('without knowing which files are there, the label is the one of the log', function (): void {
    expect(label(MailOutcome::Saved, [savedFile(notice: true)]))->toBe('Avviso salvato')
        ->and(label(MailOutcome::Saved, [savedFile(), savedFile(), savedFile()]))->toBe('3 file salvati');
});

test('with all the files there, nothing changes', function (): void {
    expect(label(MailOutcome::Saved, [savedFile(false, true)]))->toBe('Avviso salvato')
        ->and(label(MailOutcome::Saved, [savedFile(false), savedFile(false), savedFile(false)]))->toBe('3 file salvati')
        ->and(label(MailOutcome::Saved, [savedFile()]))->toBe('1 file salvato');
});

test('a notice that is gone is a deleted notice', function (): void {
    expect(label(MailOutcome::Saved, [savedFile(true, true)]))->toBe('Avviso eliminato');
});

test('files that are all gone are deleted files', function (): void {
    expect(label(MailOutcome::Saved, [savedFile(true)]))->toBe('File eliminato')
        ->and(label(MailOutcome::Saved, [savedFile(true), savedFile(true), savedFile(true)]))->toBe('3 file eliminati');
});

test('some files gone: how many were saved and how many of them are deleted', function (): void {
    expect(label(MailOutcome::Saved, [savedFile(), savedFile(true), savedFile()]))->toBe('3 file salvati · 1 eliminato')
        ->and(label(MailOutcome::Saved, [savedFile(true), savedFile(), savedFile(true)]))->toBe('3 file salvati · 2 eliminati');
});

test('a file saved by two deliveries counts once, and the deliveries are still said', function (): void {
    expect(label(MailOutcome::Saved, [savedFile(true)], 3))->toBe('File eliminato · 3 consegne');
});

test('a mail with no file saved is not changed by the files there', function (): void {
    expect(label(MailOutcome::Empty))->toBe('Nessun file');
});

test('a discarded or held mail with no file saved says so', function (): void {
    expect(label(MailOutcome::Discarded))->toBe('Scartata')
        ->and(label(MailOutcome::Held))->toBe('In esame')
        ->and(label(MailOutcome::Discarded, [], 2))->toBe('Scartata · 2 consegne');
});

test('a mail that only failed is an error, and the confirmation of Gmail says the code', function (): void {
    expect(label(MailOutcome::Error, [], 2))->toBe('Errore · 2 consegne')
        ->and(label(MailOutcome::Confirmation))->toBe('Codice di Gmail');
});

test('the colour of the label: green for files, blue for a notice, grey when nothing is left, orange under review, red for an error', function (): void {
    $tone = static fn (MailOutcome $outcome, array $files = []): string => ReceivedMailRowData::outcome($outcome, 1, $files)['tone'];

    expect($tone(MailOutcome::Saved, [savedFile()]))->toBe('success')
        ->and($tone(MailOutcome::Saved, [savedFile(), savedFile(true)]))->toBe('success')
        ->and($tone(MailOutcome::Saved, [savedFile(notice: true)]))->toBe('info')
        ->and($tone(MailOutcome::Confirmation))->toBe('info')
        ->and($tone(MailOutcome::Saved, [savedFile(true)]))->toBe('neutral')
        ->and($tone(MailOutcome::Saved, [savedFile(true, true)]))->toBe('neutral')
        ->and($tone(MailOutcome::Discarded))->toBe('neutral')
        ->and($tone(MailOutcome::Empty))->toBe('neutral')
        ->and($tone(MailOutcome::Held))->toBe('warning')
        ->and($tone(MailOutcome::Error))->toBe('danger');
});

test('canSaveAsNotice: a held or discarded mail with nothing saved', function (): void {
    expect(MailFlow::canSaveAsNotice(MailOutcome::Held, 0))->toBeTrue()
        ->and(MailFlow::canSaveAsNotice(MailOutcome::Discarded, 0))->toBeTrue()
        ->and(MailFlow::canSaveAsNotice(MailOutcome::Discarded, 1))->toBeFalse()
        ->and(MailFlow::canSaveAsNotice(MailOutcome::Saved, 0))->toBeFalse()
        ->and(MailFlow::canSaveAsNotice(MailOutcome::Empty, 0))->toBeFalse();
});
