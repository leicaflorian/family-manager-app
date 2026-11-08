<?php

declare(strict_types=1);

use App\Services\Mailgun\MailgunForm;
use App\Support\Mail\IncomingAttachment;
use Illuminate\Http\UploadedFile;

/**
 * An uploaded file as PHP gives it for a multipart request.
 */
function mailgunUpload(string $name, string $mime, string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'mgtest');
    file_put_contents($path, $contents);

    return new UploadedFile($path, $name, $mime, null, true);
}

describe('headersOf', function (): void {
    test('reads the pairs of the JSON', function (): void {
        expect(MailgunForm::headersOf(['message-headers' => '[["Message-Id","<a@h>"],["To","me@example.test"]]']))
            ->toBe([['Message-Id', '<a@h>'], ['To', 'me@example.test']]);
    });

    test('is empty when the field is missing, broken or not a list', function (): void {
        expect(MailgunForm::headersOf([]))->toBe([])
            ->and(MailgunForm::headersOf(['message-headers' => '{not json']))->toBe([])
            ->and(MailgunForm::headersOf(['message-headers' => '{"a":"b"}']))->toBe([]);
    });

    test('skips the entries that are not pairs of strings', function (): void {
        expect(MailgunForm::headersOf(['message-headers' => '[["A","1"],["B",2],"C",["D","4"]]']))
            ->toBe([['A', '1'], ['D', '4']]);
    });
});

describe('parse', function (): void {
    test('keeps at most 255 characters of the id, the length of the column', function (): void {
        $long = str_repeat('a', 300).'@mail.example';
        $mail = MailgunForm::parse(['recipient' => 'bills@example.test', 'Message-Id' => "<{$long}>"], []);

        expect($mail?->eventId)->toBe(mb_substr("mailgun:{$long}:bills@example.test", 0, 255));
    });

    test('reads a complete form', function (): void {
        $mail = MailgunForm::parse([
            'recipient' => 'Bills@Example.test',
            'from' => 'Utility <billing@utility.test>',
            'subject' => 'Your bill',
            'message-headers' => json_encode([
                ['Message-Id', '<abc123@utility.test>'],
                ['To', 'Family <bills@example.test>, Dad <dad@example.test>'],
                ['Cc', 'mom@example.test'],
                ['Date', 'Mon, 12 Oct 2026 10:00:00 +0000'],
            ]),
            'body-plain' => 'plain text',
            'body-html' => '<p>html</p>',
        ], [
            'attachment-2' => $second = mailgunUpload('second.png', 'image/png', 'fake png'),
            'attachment-1' => $first = mailgunUpload('first.pdf', 'application/pdf', '%PDF-1.4 fake'),
            'other' => mailgunUpload('x.txt', 'text/plain', ''),
        ]);

        expect($mail)->not->toBeNull()
            ->and($mail->eventId)->toBe('mailgun:abc123@utility.test:bills@example.test')
            ->and($mail->sender)->toBe('Utility <billing@utility.test>')
            ->and($mail->senderAddress)->toBe('billing@utility.test')
            ->and($mail->recipient)->toBe('bills@example.test')
            ->and($mail->recipients)->toBe(['bills@example.test', 'dad@example.test', 'mom@example.test'])
            ->and($mail->subject)->toBe('Your bill')
            ->and($mail->date?->toIso8601String())->toBe('2026-10-12T10:00:00+00:00')
            ->and($mail->plainText)->toBe('plain text')
            ->and($mail->html)->toBe('<p>html</p>')
            ->and($mail->attachments)->toHaveCount(2)
            ->and($mail->attachments[0]->filename)->toBe('first.pdf')
            ->and($mail->attachments[0]->contentType)->toBe('application/pdf')
            ->and($mail->attachments[0]->size)->toBe(13)
            ->and($mail->attachments[0]->path)->toBe($first->getPathname())
            ->and($mail->attachments[0]->index)->toBe(1)
            ->and($mail->attachments[1]->filename)->toBe('second.png')
            ->and($mail->attachments[1]->size)->toBe(8)
            ->and($mail->attachments[1]->path)->toBe($second->getPathname())
            ->and($mail->attachments[1]->index)->toBe(2);
    });

    test('an attachment whose upload failed keeps the error of PHP', function (): void {
        $mail = MailgunForm::parse(['recipient' => 'bills@example.test'], [
            'attachment-1' => new UploadedFile('', 'huge.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true),
            'attachment-2' => mailgunUpload('fine.pdf', 'application/pdf', '%PDF-1.4'),
        ]);

        expect($mail?->attachments[0]->uploadError)->toBe(UPLOAD_ERR_INI_SIZE)
            ->and($mail?->attachments[0]->filename)->toBe('huge.pdf')
            ->and($mail?->attachments[1]->uploadError)->toBe(UPLOAD_ERR_OK);
    });

    test('the attachments are ordered by their number, not as text', function (): void {
        $files = [];
        foreach ([10, 2, 1] as $number) {
            $files['attachment-'.$number] = mailgunUpload("f{$number}.pdf", 'application/pdf', 'x');
        }

        $mail = MailgunForm::parse(['recipient' => 'bills@example.test'], $files);

        expect(array_map(fn (IncomingAttachment $a): int => $a->index, $mail->attachments))->toBe([1, 2, 10]);
    });

    test('the sender and the date are empty when the call has none', function (): void {
        $mail = MailgunForm::parse(['recipient' => 'bills@example.test', 'Date' => 'not a date'], []);

        expect($mail->sender)->toBe('')
            ->and($mail->senderAddress)->toBe('')
            ->and($mail->date)->toBeNull();
    });

    test('from and From are different fields', function (): void {
        $mail = MailgunForm::parse(['recipient' => 'bills@example.test', 'from' => 'a@example.test', 'From' => 'b@example.test'], []);

        expect($mail->sender)->toBe('a@example.test');
    });

    test('without a recipient there is no mail', function (): void {
        expect(MailgunForm::parse(['from' => 'a@example.test', 'subject' => 'x'], []))->toBeNull()
            ->and(MailgunForm::parse(['recipient' => '   ', 'from' => 'a@example.test'], []))->toBeNull();
    });

    test('without a Message-Id the id is a hash, the same for the same mail', function (): void {
        $entries = ['recipient' => 'bills@example.test', 'from' => 'a@example.test', 'subject' => 'x'];
        $first = MailgunForm::parse($entries, []);

        expect($first->eventId)->toMatch('/^mailgun:[0-9a-f]{64}$/')
            ->and(MailgunForm::parse($entries, [])->eventId)->toBe($first->eventId)
            ->and(MailgunForm::parse([...$entries, 'subject' => 'y'], [])->eventId)->not->toBe($first->eventId);
    });

    // pinned: the id is how a mail delivered again is recognised. sha256 of "from\nrecipient\nsubject\nDate"
    test('without a Message-Id the id is a fixed value for a fixed mail', function (): void {
        $entries = ['recipient' => 'bills@example.test', 'from' => 'a@example.test', 'subject' => 'x'];

        expect(MailgunForm::parse($entries, [])->eventId)
            ->toBe('mailgun:5805497431ab14a92c0955997b37fc9bda5e02db1de2c12855ed757973880d1e')
            ->and(MailgunForm::parse([...$entries, 'Date' => 'Mon, 12 Oct 2026 10:00:00 +0000'], [])->eventId)
            ->toBe('mailgun:9dcf12c8bbab416941697950ddfbc2f099db1345727d8cb3cab03a4de5c780e7');
    });

    test('the id does not depend on the case of the recipient, with or without a Message-Id', function (): void {
        $headers = json_encode([['Message-Id', '<abc123@utility.test>']]);
        $withId = fn (string $recipient): string => MailgunForm::parse(['recipient' => $recipient, 'message-headers' => $headers], [])->eventId;
        $noId = fn (string $recipient): string => MailgunForm::parse([
            'recipient' => $recipient, 'from' => 'a@b.test', 'subject' => 'S', 'Date' => 'Mon, 5 Jan 2026 10:00:00 +0000',
        ], [])->eventId;

        expect($withId('Bills@Example.test'))->toBe($withId('bills@example.test'))
            ->and($noId('Bills@Example.test'))->toBe($noId('bills@example.test'));
    });
});

describe('redact', function (): void {
    $secretToken = 'secret-token-value-777';
    $secretSignature = str_repeat('c', 64);
    $bodyText = 'private body text 4242';
    $call = fn (): array => [
        'token' => $secretToken,
        'signature' => $secretSignature,
        'timestamp' => '1800000000',
        'recipient' => 'bills@example.test',
        'sender' => 'billing@utility.test',
        'from' => 'Utility <billing@utility.test>',
        'subject' => 'Your bill',
        'attachment-count' => '1',
        'body-plain' => $bodyText,
        'body-html' => "<p>{$bodyText}</p>",
        'message-headers' => '[["Subject","Your bill"]]',
        'X-Unlisted' => 'unlisted value 99',
    ];

    test('keeps the listed fields and the names of the headers', function () use ($call): void {
        expect(MailgunForm::redact($call(), ['content-type', 'host']))->toBe([
            'recipient' => 'bills@example.test',
            'sender' => 'billing@utility.test',
            'from' => 'Utility <billing@utility.test>',
            'subject' => 'Your bill',
            'attachment-count' => '1',
            'timestamp' => '1800000000',
            'http-headers' => ['content-type', 'host'],
        ]);
    });

    test('leaves neither the token, the signature, the body nor an unlisted field in the result', function () use ($call, $secretToken, $secretSignature, $bodyText): void {
        $json = json_encode(MailgunForm::redact($call(), ['content-type']));

        expect($json)->not->toContain($secretToken)
            ->and($json)->not->toContain($secretSignature)
            ->and($json)->not->toContain($bodyText)
            ->and($json)->not->toContain('unlisted value 99');
    });

    test('a text longer than 2000 characters is kept as its length', function () use ($call): void {
        expect(MailgunForm::redact([...$call(), 'subject' => str_repeat('x', 2001)], [])['subject'])->toBe('string(2001)')
            ->and(MailgunForm::redact([...$call(), 'subject' => str_repeat('x', 2000)], [])['subject'])->toBe(str_repeat('x', 2000));
    });
});
