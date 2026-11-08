<?php

declare(strict_types=1);

use App\Support\Mail\IncomingMail;
use App\Support\Mail\NoticeText;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    // The notice is written without a person, in the language of the installation, whatever the test environment says.
    config(['app.locale' => 'it']);
});

/**
 * A mail with the fields the notice reads.
 */
function noticeMail(string $subject = 'Your bill', string $senderAddress = 'billing@utility.test'): IncomingMail
{
    return new IncomingMail('evt-1', 'Utility <Billing@Utility.test>', $senderAddress, 'me@example.test', ['me@example.test'], $subject, null, '', '', []);
}

describe('textOf', function (): void {
    test('the limit is 20,000 characters of text', function (): void {
        expect(NoticeText::MAX_CHARS)->toBe(20_000);
    });

    test('\\r\\n and a lone \\r become \\n', function (): void {
        expect(NoticeText::textOf("one\r\ntwo\rthree", ''))->toBe("one\ntwo\nthree");
    });

    test('three or more new lines in a row become one empty line, in the plain text and in the html', function (): void {
        expect(NoticeText::textOf("a\n\n\n\n\nb\n\nc", ''))->toBe("a\n\nb\n\nc");
        expect(NoticeText::textOf('', '<p>a</p><p></p><p></p><p>b</p>'))->toBe("a\n\nb");
    });

    test('a numeric entity out of range (zero, above U+10FFFF) is left as written', function (): void {
        expect(NoticeText::textOf('', '<p>&#0; &#1114112; &#x110000; &#x41;</p>'))->toBe('&#0; &#1114112; &#x110000; A');
    });

    test('a plain text of at least MIN_PLAIN_CHARS is taken as it is', function (): void {
        $plain = str_repeat('p', NoticeText::MIN_PLAIN_CHARS);
        expect(NoticeText::MIN_PLAIN_CHARS)->toBe(80);
        expect(NoticeText::textOf($plain, '<p>from the html</p>'))->toBe($plain);
    });

    test('a plain text of 79 characters gives way to the html', function (): void {
        expect(NoticeText::textOf(str_repeat('p', 79), '<p>from the html</p>'))->toBe('from the html');
    });

    test('a placeholder text is replaced by the text of the html', function (): void {
        expect(NoticeText::textOf('Open this mail in a browser', '<div>Dear user,<br>your bill is ready.</div>'))
            ->toBe("Dear user,\nyour bill is ready.");
    });

    test('a short plain text is kept when there is no html', function (): void {
        expect(NoticeText::textOf('short', ''))->toBe('short');
    });

    test('the html loses head, style, script and comments, and decodes the entities', function (): void {
        $html = '<html><head><title>t</title></head><style>p{color:red}</style><!-- note --><p>Total:&nbsp;12&euro; &amp; &#65;&#x42;</p><script>x()</script></html>';
        expect(NoticeText::textOf('', $html))->toBe('Total: 12€ & AB');
    });

    test('table cells are separated by a space and rows by a new line', function (): void {
        expect(NoticeText::textOf('', '<table><tr><td>Due</td><td>10</td></tr><tr><td>Paid</td><td>5</td></tr></table>'))
            ->toBe("Due 10\nPaid 5");
    });

    test('returns null when both are empty', function (): void {
        expect(NoticeText::textOf('  ', '<p> </p>'))->toBeNull();
        expect(NoticeText::textOf(null, null))->toBeNull();
        expect(NoticeText::textOf('', ''))->toBeNull();
    });

    test('a text over MAX_CHARS is cut and marked', function (): void {
        expect(NoticeText::textOf(str_repeat('a', NoticeText::MAX_CHARS + 5), ''))
            ->toBe(str_repeat('a', NoticeText::MAX_CHARS)."\n[testo troncato]");
    });

    test('the marker of a cut text is in English when the installation is in English', function (): void {
        config(['app.locale' => 'en']);

        expect(NoticeText::textOf(str_repeat('a', NoticeText::MAX_CHARS + 5), ''))
            ->toBe(str_repeat('a', NoticeText::MAX_CHARS)."\n[text cut]");
    });
});

describe('name', function (): void {
    test('puts the subject after "Avviso - "', function (): void {
        expect(NoticeText::name('Your bill'))->toBe('Avviso - Your bill.txt');
    });

    test('removes forward and reply prefixes, also stacked', function (): void {
        expect(NoticeText::name('Fwd: RE: I: Your bill'))->toBe('Avviso - Your bill.txt');
        expect(NoticeText::name('R: Inoltro: fw: Your bill'))->toBe('Avviso - Your bill.txt');
    });

    test('replaces the characters a file name cannot have', function (): void {
        expect(NoticeText::name('Bill 1/2: "June" <final>?'))->toBe('Avviso - Bill 1-2- -June- -final--.txt');
    });

    test('an empty subject becomes "senza oggetto"', function (): void {
        expect(NoticeText::name(''))->toBe('Avviso - senza oggetto.txt');
        expect(NoticeText::name('Re:   '))->toBe('Avviso - senza oggetto.txt');
    });

    test('the subject is cut at 150 characters', function (): void {
        expect(NoticeText::name(str_repeat('s', 200)))->toBe('Avviso - '.str_repeat('s', 150).'.txt');
    });
});

describe('file', function (): void {
    test('writes the fixed lines, the sender, the subject, the date and the text', function (): void {
        $received = CarbonImmutable::parse('2026-10-13 09:30', 'Europe/Rome');

        expect(NoticeText::file(noticeMail(), $received, 'The text.'))->toBe("\xEF\xBB\xBF".implode("\n", [
            'Avviso arrivato per posta, senza allegato.',
            "Per caricare la fattura vera occorre accedere all'area privata del fornitore.",
            '',
            'Da: billing@utility.test',
            'Oggetto: Your bill',
            'Arrivato il: 13/10/2026 09:30',
            '',
            'The text.',
        ]));
    });

    test('an empty subject becomes "senza oggetto"', function (): void {
        $received = CarbonImmutable::parse('2026-10-13 09:30', 'Europe/Rome');

        expect(NoticeText::file(noticeMail(''), $received, 'T'))->toContain("\nOggetto: senza oggetto\n");
    });

    test('the hour of Rome is written, also for a mail that arrived on the last evening of the month in UTC', function (): void {
        $received = CarbonImmutable::parse('2026-10-31 22:30', 'UTC');

        expect(NoticeText::file(noticeMail(), $received, 'T'))->toContain("\nArrivato il: 31/10/2026 23:30\n");
    });
});

describe('readingLine', function (): void {
    // The amount is written as the rest of the app does: with a non-breaking space before the euro sign.
    test('issuer, amount and due date', function (): void {
        expect(NoticeText::readingLine('Edison', 84.2, '2026-10-30'))->toBe("Edison, 84,20\u{A0}€, scade il 30/10/2026");
    });

    test('formats thousands without depending on the locale', function (): void {
        expect(NoticeText::readingLine(null, 1234.5, null))->toBe("1.234,50\u{A0}€");
    });

    test('drops the missing parts', function (): void {
        expect(NoticeText::readingLine('Edison', null, null))->toBe('Edison');
        expect(NoticeText::readingLine(null, null, '2026-10-30'))->toBe('scade il 30/10/2026');
    });

    test('is null with nothing to say', function (): void {
        expect(NoticeText::readingLine(null, null, null))->toBeNull();
    });
});

describe('shouldNotify', function (): void {
    test('no file saved: no', function (): void {
        expect(NoticeText::shouldNotify(false, false, false))->toBeFalse();
    });

    test('attachments saved: yes', function (): void {
        expect(NoticeText::shouldNotify(true, false, true))->toBeTrue();
    });

    test('only the notice: no with attachmentsOnly, yes without', function (): void {
        expect(NoticeText::shouldNotify(true, true, true))->toBeFalse();
        expect(NoticeText::shouldNotify(true, true, false))->toBeTrue();
    });
});
