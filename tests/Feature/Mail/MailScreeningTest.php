<?php

declare(strict_types=1);

use App\Ai\Agents\MailScreeningAgent;
use App\Contracts\DocumentReader;
use App\Data\Mail\MailScreeningAnswer;
use App\Enums\ReadingPurpose;
use App\Exceptions\Reading\ReadingFailed;
use App\Models\AiReading;
use App\Models\Family;
use App\Models\Organization;
use App\Models\ReceivedMail;
use App\Services\Mail\MailScreening;
use App\Support\CurrentFamily;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Tests\Support\FakeDocumentReader;

/**
 * The answer a mail gets from the service in the family of the mail.
 *
 * @param  ReceivedMail  $mail  the mail
 */
function screeningOf(ReceivedMail $mail): ?MailScreeningAnswer
{
    return CurrentFamily::using($mail->family_id, static fn (): ?MailScreeningAnswer => resolve(MailScreening::class)->ask($mail));
}

/**
 * The reader bound in the container.
 *
 * @param  FakeDocumentReader  $reader  the fake
 */
function bindReader(FakeDocumentReader $reader): FakeDocumentReader
{
    app()->instance(DocumentReader::class, $reader);

    return $reader;
}

describe('parse', function (): void {
    test('a valid answer is returned as it is', function (): void {
        $answer = MailScreeningAgent::parse('{"verdict":"discard","reason":"offerta commerciale"}');

        expect($answer->verdict)->toBe('discard')->and($answer->reason)->toBe('offerta commerciale');
    });

    test('empty text, broken JSON and a verdict out of the list are unsure', function (): void {
        foreach ([null, '', '{"verdict":', '{"verdict":"maybe","reason":"x"}', '{"verdict":"import","reason":"x"}'] as $text) {
            $answer = MailScreeningAgent::parse($text);

            expect($answer->verdict)->toBe('unsure')->and($answer->reason)->toBeNull();
        }
    });

    test('a reason over 200 characters is cut to 200', function (): void {
        $answer = MailScreeningAgent::parse(json_encode(['verdict' => 'keep', 'reason' => str_repeat('a', 300)]));

        expect($answer->verdict)->toBe('keep')->and($answer->reason)->toHaveLength(200);
    });

    test('a missing reason is empty', function (): void {
        $answer = MailScreeningAgent::parse('{"verdict":"keep"}');

        expect($answer->verdict)->toBe('keep')->and($answer->reason)->toBeNull();
    });

    test('a structured answer is read as the text is', function (): void {
        $answer = MailScreeningAgent::parse(['verdict' => 'discard', 'reason' => 'newsletter']);

        expect($answer->verdict)->toBe('discard')->and($answer->reason)->toBe('newsletter');
    });
});

describe('request', function (): void {
    test('carries the sender and the subject', function (): void {
        $text = MailScreeningAgent::request('Enel <noreply@enel.it>', 'La tua bolletta', 'it');

        expect($text)->toContain('Enel <noreply@enel.it>')->and($text)->toContain('La tua bolletta');
    });

    test('an empty subject says "senza oggetto"', function (): void {
        expect(MailScreeningAgent::request('a@b.it', '  ', 'it'))->toContain('senza oggetto');
    });
});

test('the schema asks for verdict and reason and nothing else', function (): void {
    $schema = new MailScreeningAgent('gemini-3.8-flash')->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['verdict', 'reason'])
        ->and($schema['verdict']->toArray()['enum'])->toBe(['keep', 'discard', 'unsure']);
});

describe('ask', function (): void {
    beforeEach(function (): void {
        $this->family = Family::factory()->create();
        $this->mail = ReceivedMail::factory()->for($this->family)->create(['sender_address' => 'promo@offerte-energia.it', 'subject' => 'Nuove offerte luce e gas']);
    });

    test('an unknown sender is asked about, with sender and subject, and the call is recorded', function (): void {
        $reader = bindReader((new FakeDocumentReader)->screens(new MailScreeningAnswer('discard', 'offerta commerciale')));

        $answer = screeningOf($this->mail);

        $row = AiReading::query()->withoutGlobalScopes()->sole();
        expect($answer?->verdict)->toBe('discard')
            ->and($reader->screenings())->toBe(1)
            ->and($row->purpose)->toBe(ReadingPurpose::MailFilter)
            ->and($row->file_id)->toBeNull()
            ->and($row->user_id)->toBeNull()
            ->and($row->family_id)->toBe($this->family->id);
    });

    test('a known sender is not asked about and gives its organization to the mail', function (): void {
        $enel = Organization::factory()->for($this->family)->create(['name' => 'Enel Energia', 'email' => null, 'website' => 'https://www.enel.it']);
        $mail = ReceivedMail::factory()->for($this->family)->create(['sender_address' => 'fatture@clienti.enel.it']);
        $reader = bindReader(new FakeDocumentReader);

        expect(screeningOf($mail))->toBeNull()
            ->and($reader->screenings())->toBe(0)
            ->and($mail->fresh()->organization_id)->toBe($enel->id)
            ->and(AiReading::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    test('with the reading off the model is not asked', function (): void {
        $reader = bindReader((new FakeDocumentReader)->off());

        expect(screeningOf($this->mail))->toBeNull()->and($reader->screenings())->toBe(0);
    });

    test('with the readings of the month used up the model is not asked', function (): void {
        config(['document_reader.monthly_limit' => 1]);
        AiReading::factory()->for($this->family)->create(['created_at' => now()]);
        $reader = bindReader(new FakeDocumentReader);

        expect(screeningOf($this->mail))->toBeNull()->and($reader->screenings())->toBe(0);
    });

    test('a failing service makes the answer unsure', function (): void {
        bindReader((new FakeDocumentReader)->fails(ReadingFailed::refused()));

        $answer = screeningOf($this->mail);

        expect($answer?->verdict)->toBe('unsure')->and($answer?->reason)->toBeNull();
    });
});
