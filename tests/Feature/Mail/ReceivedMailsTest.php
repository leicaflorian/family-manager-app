<?php

declare(strict_types=1);

use App\Enums\MailOutcome;
use App\Models\Document;
use App\Models\File;
use App\Models\Organization;
use App\Models\ReceivedMail;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Mail\ReceivedMails;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->marco = registerFamily('Bianchi', 'marco@example.com');
});

afterEach(fn () => Date::setTestNow());

/**
 * A mail of the current family with a file saved from it, if asked.
 *
 * @param  array<string, mixed>  $attributes  columns of the mail
 * @param  bool  $withFile  whether a file was saved from it
 */
function mailWithFile(array $attributes = [], bool $withFile = true): ReceivedMail
{
    $mail = ReceivedMail::factory()->create(['family_id' => CurrentFamily::id(), ...$attributes]);

    if ($withFile) {
        $file = File::factory()->create(['family_id' => CurrentFamily::id(), 'received_mail_id' => $mail->id, 'mail_key' => $mail->event_id.':0']);
        $mail->recordSaved($file);
    }

    return $mail;
}

test('the latest mails with files come newest first, the discarded ones and those with no file left out', function (): void {
    $names = insideFamilyOf($this->marco, function (): array {
        mailWithFile(['subject' => 'Oldest', 'received_at' => '2026-10-20 08:00']);
        mailWithFile(['subject' => 'Newest', 'received_at' => '2026-10-25 09:41']);
        mailWithFile(['subject' => 'Advert', 'received_at' => '2026-10-25 10:00', 'outcome' => MailOutcome::Discarded], withFile: false);
        mailWithFile(['subject' => 'Empty', 'received_at' => '2026-10-25 11:00', 'outcome' => MailOutcome::Empty], withFile: false);
        $gone = mailWithFile(['subject' => 'All deleted', 'received_at' => '2026-10-25 12:00']);
        $gone->files()->delete();
        mailWithFile(['subject' => 'Middle', 'received_at' => '2026-10-23 08:00']);

        return array_map(static fn (array $row): ?string => $row['mail']->subject, resolve(ReceivedMails::class)->recent(2));
    });

    expect($names)->toBe(['Newest', 'Middle']);
});

test('a mail brings the document born from its file and the card that document is linked to', function (): void {
    $row = insideFamilyOf($this->marco, function (): array {
        $mail = mailWithFile(['subject' => 'La tua bolletta', 'organization_id' => Organization::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Enel Energia'])->id]);
        $document = Document::factory()->create(['family_id' => CurrentFamily::id(), 'title' => 'Bolletta', 'file_id' => $mail->files->sole()->id]);
        $document->utilities()->attach(Utility::factory()->create(['family_id' => CurrentFamily::id()]));
        $document->vehicles()->attach(Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Fiat Panda']));

        return resolve(ReceivedMails::class)->recent(3)[0];
    });

    expect($row['documents'])->toHaveCount(1)
        ->and($row['documents'][0]->title)->toBe('Bolletta')
        ->and($row['mail']->senderName())->toBe('Enel Energia')
        ->and($row['documents'][0]->relationLoaded('vehicles'))->toBeTrue();
});

test('a mail whose file is still waiting has no document', function (): void {
    $row = insideFamilyOf($this->marco, function (): array {
        mailWithFile();

        return resolve(ReceivedMails::class)->recent(3)[0];
    });

    expect($row['documents'])->toBe([]);
});

test('the mails of another family are not there', function (): void {
    $other = registerFamily('Rossi', 'rossi@example.com');
    insideFamilyOf($other, fn (): ReceivedMail => mailWithFile(['subject' => 'Theirs']));

    expect(insideFamilyOf($this->marco, fn (): array => resolve(ReceivedMails::class)->recent(3)))->toBe([]);
});

test('listing the mails does not ask the database once per mail', function (): void {
    /** Adds a mail with a document linked to a vehicle, then counts the queries of the list. */
    $count = static function (int $n): int {
        $mail = mailWithFile(['received_at' => "2026-10-0{$n} 08:00"]);
        Document::factory()->create(['family_id' => CurrentFamily::id(), 'file_id' => $mail->files->sole()->id])->vehicles()->attach(Vehicle::factory()->create(['family_id' => CurrentFamily::id()]));

        DB::flushQueryLog();
        DB::enableQueryLog();
        resolve(ReceivedMails::class)->recent(5);

        return count(DB::getQueryLog());
    };

    [$one, $two, $three] = insideFamilyOf($this->marco, fn (): array => [$count(1), $count(2), $count(3)]);

    expect($two)->toBe($one)->and($three)->toBe($one);
});

test('the latest mails never read the text of the mails', function (): void {
    $columns = [];
    ReceivedMail::retrieved(function (ReceivedMail $mail) use (&$columns): void {
        $columns = [...$columns, ...array_keys($mail->getAttributes())];
    });

    insideFamilyOf($this->marco, function (): void {
        mailWithFile();
        resolve(ReceivedMails::class)->recent(3);
    });

    expect($columns)->not->toBeEmpty()
        ->not->toContain('body')
        ->not->toContain('screening');
});
