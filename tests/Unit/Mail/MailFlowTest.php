<?php

declare(strict_types=1);

use App\Data\Mail\MailScreeningAnswer;
use App\Support\Mail\IncomingMail;
use App\Support\Mail\MailFlow;

beforeEach(function (): void {
    // The notes are written without a person, in the language of the installation.
    config(['app.locale' => 'it']);
});

/**
 * A mail with the fields the Gmail confirmation reads.
 */
function flowMail(string $sender = 'forwarding-noreply@google.com', string $subject = '', string $plainText = ''): IncomingMail
{
    return new IncomingMail('evt-1', $sender, $sender, 'me@example.test', ['me@example.test'], $subject, null, $plainText, '', []);
}

describe('afterScreen', function (): void {
    test('stops a held notice the model discards, with the reason', function (): void {
        expect(MailFlow::afterScreen(new MailScreeningAnswer('discard', 'offerta commerciale'), true))
            ->toBe(['action' => 'discard', 'note' => 'scartata: offerta commerciale']);
    });

    test('keeps the attachments of a discarded mail unread, with the reason', function (): void {
        expect(MailFlow::afterScreen(new MailScreeningAnswer('discard', 'newsletter'), false))
            ->toBe(['action' => 'unread', 'note' => 'non letti: newsletter']);
    });

    test('says only "scartata" or "non letti" when the model gives no reason', function (): void {
        expect(MailFlow::afterScreen(new MailScreeningAnswer('discard', ''), true)['note'])->toBe('scartata')
            ->and(MailFlow::afterScreen(new MailScreeningAnswer('discard', '  '), false)['note'])->toBe('non letti')
            ->and(MailFlow::afterScreen(new MailScreeningAnswer('discard', null), false)['note'])->toBe('non letti');
    });

    test('reads on keep and unsure', function (): void {
        expect(MailFlow::afterScreen(new MailScreeningAnswer('keep', 'bolletta'), true))->toBe(['action' => 'read', 'note' => null])
            ->and(MailFlow::afterScreen(new MailScreeningAnswer('unsure', null), false))->toBe(['action' => 'read', 'note' => null]);
    });

    test('reads when the screening failed (null), as with unsure', function (): void {
        expect(MailFlow::afterScreen(null, true))->toBe(['action' => 'read', 'note' => null])
            ->and(MailFlow::afterScreen(null, false))->toBe(['action' => 'read', 'note' => null]);
    });
});

describe('readFailure', function (): void {
    test('names the file and the message', function (): void {
        expect(MailFlow::readFailure('A2A.pdf', 'timeout'))->toBe('lettura AI non riuscita: A2A.pdf: timeout');
    });
});

describe('alreadyHeld', function (): void {
    test('is true for an outcome held or discarded', function (): void {
        expect(MailFlow::alreadyHeld('held'))->toBeTrue()
            ->and(MailFlow::alreadyHeld('discarded'))->toBeTrue();
    });

    test('is false otherwise', function (): void {
        foreach (['empty', 'saved', 'error', 'confirmation', 'limited'] as $outcome) {
            expect(MailFlow::alreadyHeld($outcome))->toBeFalse();
        }
    });
});

describe('Gmail confirmation', function (): void {
    test('the sender is the one of Gmail forwarding', function (): void {
        expect(MailFlow::GMAIL_FORWARDING)->toBe('forwarding-noreply@google.com')
            ->and(MailFlow::isGmailConfirmation(flowMail()))->toBeTrue();
    });

    test('another sender of google.com is not a confirmation', function (): void {
        expect(MailFlow::isGmailConfirmation(flowMail('no-reply@google.com')))->toBeFalse();
    });

    test('the code is the first number of 9 digits in the subject', function (): void {
        expect(MailFlow::gmailCode(flowMail(subject: '(#123456789) Conferma inoltro Gmail', plainText: 'code 987654321')))->toBe('123456789');
    });

    test('without a code in the subject the text is read', function (): void {
        expect(MailFlow::gmailCode(flowMail(subject: 'Conferma inoltro Gmail', plainText: 'Il codice è 987654321.')))->toBe('987654321');
    });

    test('a number of another length is not a code', function (): void {
        expect(MailFlow::gmailCode(flowMail(subject: 'Conferma 12345678', plainText: 'n. 1234567890')))->toBeNull();
    });
});
