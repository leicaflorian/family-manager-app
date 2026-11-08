<?php

declare(strict_types=1);

use App\Enums\SkipReason;
use App\Support\Mail\AttachmentRules;
use App\Support\Mail\IncomingAttachment;

const RULES_MAX_BYTES = 10 * 1024 * 1024;

/**
 * An attachment as Mailgun uploaded it, with a made-up temporary path.
 */
function ruleAttachment(string $filename, string $contentType, int $size): IncomingAttachment
{
    return new IncomingAttachment($filename, $contentType, $size, '/tmp/none', 0);
}

/**
 * The first bytes of a PDF, a JPEG, a PNG or a HEIC file.
 */
function ruleBytes(string $kind): string
{
    return match ($kind) {
        'pdf' => "%PDF-1.7\n",
        'jpg' => "\xff\xd8\xff\xe0\x00",
        'png' => "\x89PNG\r\n\x1a\n\x00",
        'heic' => "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00",
        default => 'Hello, this is a text file',
    };
}

describe('sniff', function (): void {
    test('recognises a PDF', function (): void {
        expect(AttachmentRules::sniff(ruleBytes('pdf')))->toBe('pdf');
    });

    test('recognises a JPEG', function (): void {
        expect(AttachmentRules::sniff(ruleBytes('jpg')))->toBe('jpg');
    });

    test('recognises a PNG', function (): void {
        expect(AttachmentRules::sniff(ruleBytes('png')))->toBe('png');
    });

    test('recognises a HEIC by each of its brands', function (): void {
        foreach (['heic', 'heix', 'mif1', 'msf1'] as $brand) {
            expect(AttachmentRules::sniff("\x00\x00\x00\x18ftyp{$brand}\x00\x00\x00\x00"))->toBe('heic');
        }
    });

    test('an ftyp box of another brand is nothing', function (): void {
        expect(AttachmentRules::sniff("\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00"))->toBeNull();
    });

    test('a text file is nothing', function (): void {
        expect(AttachmentRules::sniff(ruleBytes('txt')))->toBeNull();
    });

    test('an empty buffer is nothing', function (): void {
        expect(AttachmentRules::sniff(''))->toBeNull();
    });
});

describe('verdict', function (): void {
    test('a file bigger than the upload limit of PHP is too large, whatever arrived of it', function (int $error): void {
        $attachment = new IncomingAttachment('huge.pdf', 'application/pdf', 0, '', 1, $error);

        expect(AttachmentRules::verdict($attachment, '', RULES_MAX_BYTES))->toBe(SkipReason::TooLarge);
    })->with(['ini size' => UPLOAD_ERR_INI_SIZE, 'form size' => UPLOAD_ERR_FORM_SIZE]);

    test('a PDF is a document, even a small one', function (): void {
        expect(AttachmentRules::verdict(ruleAttachment('bill.pdf', 'application/pdf', 1000), ruleBytes('pdf'), RULES_MAX_BYTES))->toBeNull();
    });

    test('an image just below MIN_IMAGE_BYTES is not a document', function (): void {
        expect(AttachmentRules::MIN_IMAGE_BYTES)->toBe(30720);
        expect(AttachmentRules::verdict(ruleAttachment('logo.png', 'image/png', 30719), ruleBytes('png'), RULES_MAX_BYTES))->toBe(SkipReason::TooSmall);
    });

    test('an image of exactly MIN_IMAGE_BYTES is a document', function (): void {
        expect(AttachmentRules::verdict(ruleAttachment('photo.jpg', 'image/jpeg', 30720), ruleBytes('jpg'), RULES_MAX_BYTES))->toBeNull();
    });

    test('a type that is not allowed is not a document', function (): void {
        expect(AttachmentRules::verdict(ruleAttachment('notes.txt', 'text/plain', 50000), ruleBytes('txt'), RULES_MAX_BYTES))->toBe(SkipReason::NotADocument);
    });

    test('a file over maxBytes is not a document', function (): void {
        expect(AttachmentRules::verdict(ruleAttachment('big.pdf', 'application/pdf', RULES_MAX_BYTES + 1), ruleBytes('pdf'), RULES_MAX_BYTES))->toBe(SkipReason::TooLarge);
    });

    test('a file of exactly maxBytes is a document', function (): void {
        expect(AttachmentRules::verdict(ruleAttachment('big.pdf', 'application/pdf', RULES_MAX_BYTES), ruleBytes('pdf'), RULES_MAX_BYTES))->toBeNull();
    });

    test('an octet-stream with a .pdf name is decided by the content', function (): void {
        expect(AttachmentRules::verdict(ruleAttachment('bill.PDF', 'application/octet-stream', 1000), ruleBytes('pdf'), RULES_MAX_BYTES))->toBeNull();
    });

    test('the parameters of the content type are ignored', function (): void {
        expect(AttachmentRules::verdict(ruleAttachment('bill', 'Application/PDF; name="bill"', 1000), ruleBytes('pdf'), RULES_MAX_BYTES))->toBeNull();
    });

    test('a HEIC of 2 MB is a document', function (): void {
        expect(AttachmentRules::verdict(ruleAttachment('photo.heic', 'image/heic', 2 * 1024 * 1024), ruleBytes('heic'), RULES_MAX_BYTES))->toBeNull();
    });

    test('a HEIC of 10 KB is too small', function (): void {
        expect(AttachmentRules::verdict(ruleAttachment('photo.heic', 'image/heic', 10 * 1024), ruleBytes('heic'), RULES_MAX_BYTES))->toBe(SkipReason::TooSmall);
    });

    test('a .pdf that is really a PNG is judged as a PNG', function (): void {
        expect(AttachmentRules::verdict(ruleAttachment('bill.pdf', 'application/pdf', 1000), ruleBytes('png'), RULES_MAX_BYTES))->toBe(SkipReason::TooSmall);
    });

    test('a PDF declared as an image is judged as a PDF', function (): void {
        expect(AttachmentRules::verdict(ruleAttachment('bill.png', 'image/png', 1000), ruleBytes('pdf'), RULES_MAX_BYTES))->toBeNull();
    });
});

describe('sameFileKey', function (): void {
    test('joins name, lower case type and size', function (): void {
        expect(AttachmentRules::sameFileKey(ruleAttachment('bill.pdf', 'Application/PDF', 1234)))->toBe("bill.pdf\napplication/pdf\n1234");
    });

    test('two entries of the same file have the same key', function (): void {
        expect(AttachmentRules::sameFileKey(ruleAttachment('bill.pdf', 'application/pdf', 1234)))
            ->toBe(AttachmentRules::sameFileKey(ruleAttachment('bill.pdf', 'APPLICATION/PDF', 1234)));
    });

    test('a different size gives a different key', function (): void {
        expect(AttachmentRules::sameFileKey(ruleAttachment('bill.pdf', 'application/pdf', 1234)))
            ->not->toBe(AttachmentRules::sameFileKey(ruleAttachment('bill.pdf', 'application/pdf', 1235)));
    });

    test('is null when the size is not known', function (): void {
        expect(AttachmentRules::sameFileKey(ruleAttachment('bill.pdf', 'application/pdf', 0)))->toBeNull();
    });
});

describe('mailKey', function (): void {
    test('is a SHA-256 hex digest', function (): void {
        expect(AttachmentRules::mailKey('mailgun:abc@host:me@example.test', 0))->toMatch('/^[0-9a-f]{64}$/');
    });

    // pinned: this key is how the files already saved are recognised when the same mail is delivered again
    test('gives a fixed value for a fixed mail and index', function (): void {
        expect(AttachmentRules::mailKey('mail-1', 2))->toBe(hash('sha256', 'mail-inbox:mail-1:2'));
    });

    test('is the same for the same mail and index', function (): void {
        expect(AttachmentRules::mailKey('mail-1', 2))->toBe(AttachmentRules::mailKey('mail-1', 2));
    });

    test('differs for different indexes', function (): void {
        expect(AttachmentRules::mailKey('mail-1', 0))->not->toBe(AttachmentRules::mailKey('mail-1', 1));
    });

    test('differs for different mails', function (): void {
        expect(AttachmentRules::mailKey('mail-1', 0))->not->toBe(AttachmentRules::mailKey('mail-2', 0));
    });

    test('the notice of a mail has the index -1', function (): void {
        expect(AttachmentRules::mailKey('mail-1', -1))->toBe(hash('sha256', 'mail-inbox:mail-1:-1'));
    });
});

describe('fileName', function (): void {
    test('adds the extension to a name without one', function (): void {
        expect(AttachmentRules::fileName('bill', 'pdf'))->toBe('bill.pdf');
    });

    test('replaces the extension', function (): void {
        expect(AttachmentRules::fileName('scan.jpeg', 'jpg'))->toBe('scan.jpg');
    });

    test('drops folders', function (): void {
        expect(AttachmentRules::fileName('C:\\Users\\me\\bill.pdf', 'pdf'))->toBe('bill.pdf');
        expect(AttachmentRules::fileName('../../etc/bill.pdf', 'pdf'))->toBe('bill.pdf');
    });

    test('an empty name becomes "allegato"', function (): void {
        expect(AttachmentRules::fileName('', 'pdf'))->toBe('allegato.pdf');
    });

    test('a name that is only an extension becomes "allegato"', function (): void {
        expect(AttachmentRules::fileName('.pdf', 'pdf'))->toBe('allegato.pdf');
    });

    test('control characters become spaces', function (): void {
        expect(AttachmentRules::fileName("bill\x00one.pdf", 'pdf'))->toBe('bill one.pdf');
    });

    test('the base is cut at 150 characters', function (): void {
        expect(AttachmentRules::fileName(str_repeat('a', 200).'.pdf', 'pdf'))->toBe(str_repeat('a', 150).'.pdf');
    });

    test('keeps accented letters whole when cutting', function (): void {
        expect(AttachmentRules::fileName(str_repeat('è', 200).'.pdf', 'pdf'))->toBe(str_repeat('è', 150).'.pdf');
    });
});

describe('toRead', function (): void {
    /**
     * A saved file as the reader gets it.
     *
     * @return array{id: int, type: string}
     */
    function ruleFile(int $id, string $type): array
    {
        return ['id' => $id, 'type' => $type];
    }

    test('PDFs win over photos', function (): void {
        expect(AttachmentRules::toRead([ruleFile(1, 'image/png'), ruleFile(2, 'application/pdf')]))->toBe([2]);
    });

    test('without PDFs, all the photos', function (): void {
        expect(AttachmentRules::toRead([ruleFile(1, 'image/jpeg'), ruleFile(2, 'image/heic')]))->toBe([1, 2]);
    });

    test('at most MAX_READS_PER_MAIL, in the given order', function (): void {
        $pdfs = array_map(fn (int $i): array => ruleFile($i, 'application/pdf'), range(10, 16));

        expect(AttachmentRules::MAX_READS_PER_MAIL)->toBe(5);
        expect(AttachmentRules::toRead($pdfs))->toBe([10, 11, 12, 13, 14]);
    });

    test('nothing to read', function (): void {
        expect(AttachmentRules::toRead([]))->toBe([]);
        expect(AttachmentRules::toRead([ruleFile(1, 'text/plain')]))->toBe([]);
    });
});
