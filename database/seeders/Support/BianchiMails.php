<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Actions\Mail\SaveMailNotice;
use App\Data\Mail\MailScreeningAnswer;
use App\Enums\FileSource;
use App\Enums\MailOutcome;
use App\Models\FamilySetting;
use App\Models\Organization;
use App\Models\ReceivedMail;
use App\Services\FileStore;
use App\Support\Mail\AttachmentRules;
use App\Support\Mail\MailAddress;
use App\Support\Mail\MailFlow;
use App\Support\Mail\NoticeText;
use App\Support\Mail\StoredBody;
use Carbon\CarbonImmutable;

/**
 * The address for the bills of the Bianchis and the six mails of the canvas *Posta ricevuta*, with the files they
 * saved in *To sort*. Everything is made with the real classes of the mail inbox, in the current family and in the
 * language of the seeder, with no call to Mailgun and no data of a real person.
 */
final readonly class BianchiMails
{
    /** The part before the `@` of the address of the Bianchis, on a local machine and in the tests. */
    public const string ADDRESS_NAME = 'bianchi.k7m2x9';

    /** The domain used when `MAIL_INBOX_DOMAIN` is empty. */
    private const string DEFAULT_DOMAIN = 'bollette.familymanager.test';

    /**
     * @param  FileStore  $files  where the attachments are saved
     * @param  SaveMailNotice  $notices  saves the text of a mail without attachments as a file
     */
    public function __construct(private FileStore $files, private SaveMailNotice $notices) {}

    /**
     * Gives the family its address and writes the six mails, the newest first on the canvas.
     *
     * @param  array<string, Organization>  $organizations  the organizations of the family, by name
     */
    public function handle(array $organizations): void
    {
        $address = $this->address();
        FamilySetting::query()->firstOrCreate([])->forceFill(['mail_inbox_recipient' => $address])->save();

        foreach ($this->mails() as $mail) {
            $record = ReceivedMail::query()->create([
                'event_id' => 'bianchi-'.$mail['key'].'@mail.example',
                'sender' => $mail['sender'],
                'sender_address' => $mail['address'],
                'recipient' => $address,
                'recipients' => [$address],
                'subject' => $mail['subject'],
                'received_at' => CarbonImmutable::parse($mail['at'], 'Europe/Rome'),
                'deliveries' => $mail['deliveries'] ?? 1,
                'outcome' => $mail['outcome'],
                'notes' => $mail['notes'] ?? [],
                'skipped' => $mail['skipped'] ?? [],
                'body' => StoredBody::of($mail['text'], null),
                'organization_id' => isset($mail['organization']) ? ($organizations[$mail['organization']]->id ?? null) : null,
                'screening' => $mail['screening'] ?? null,
            ]);

            $this->saveFiles($record, $mail['files'] ?? []);

            if ($mail['notice'] ?? false) {
                $this->notices->handle($record);
            }
        }
    }

    /**
     * The address of the family. On a local machine and in the tests the fixed one of the README, on the domain of
     * the installation. Anywhere else the README is public, so the family keeps the random address it got at sign-up,
     * or gets a random one when there is no domain.
     */
    private function address(): string
    {
        $domain = config('services.mailgun.inbox_domain');
        $domain = is_string($domain) && trim($domain) !== '' ? trim($domain) : self::DEFAULT_DOMAIN;

        if (app()->environment('local', 'testing')) {
            return self::ADDRESS_NAME.'@'.$domain;
        }

        $given = FamilySetting::query()->value('mail_inbox_recipient');

        return is_string($given) && $given !== '' ? $given : MailAddress::make('Bianchi', $domain);
    }

    /**
     * Saves the attachments of a mail in *To sort*, as `ReceiveMail` does.
     *
     * @param  ReceivedMail  $mail  the mail
     * @param  array<string, string>  $files  the name each file arrived with, by the name in `database/seeders/files/`
     */
    private function saveFiles(ReceivedMail $mail, array $files): void
    {
        $note = NoticeText::note($mail->sender_address, $mail->subject, $mail->received_at);
        $saved = [];
        $index = 1;

        foreach ($files as $stored => $name) {
            $saved[] = $this->files->storePath(
                __DIR__.'/../files/'.$stored,
                $name,
                'application/pdf',
                true,
                FileSource::Mail,
                $note,
                ['received_mail_id' => $mail->id, 'mail_key' => AttachmentRules::mailKey($mail->event_id, $index++)],
            );
        }

        $mail->recordSaved(...$saved);
    }

    /**
     * The six mails of the canvas, the newest first.
     *
     * @return list<array{key: string, sender: string, address: string, subject: string, at: string, outcome: MailOutcome, text: string, organization?: string, deliveries?: int, files?: array<string, string>, notice?: bool, skipped?: list<array{name: string, reason: string}>, notes?: list<string>, screening?: array{verdict: string, reason: string|null}}>
     */
    private function mails(): array
    {
        $advert = new MailScreeningAnswer('discard', 'offerta commerciale');

        return [
            [
                'key' => 'enel', 'sender' => 'Enel Energia <bollette@enel.it>', 'address' => 'bollette@enel.it', 'organization' => 'Enel Energia',
                'subject' => 'La tua bolletta', 'at' => '2026-10-25 09:41', 'outcome' => MailOutcome::Saved,
                'text' => "Gentile cliente,\nin allegato la tua bolletta del gas di ottobre.\nEnel Energia",
                'files' => ['bolletta-enel-ottobre.pdf' => 'Bolletta Enel ottobre.pdf'],
            ],
            [
                'key' => 'fastweb', 'sender' => 'Fastweb <fatture@fastweb.it>', 'address' => 'fatture@fastweb.it', 'organization' => 'Fastweb',
                'subject' => 'Fattura disponibile', 'at' => '2026-10-25 09:12', 'outcome' => MailOutcome::Saved,
                'text' => "Gentile cliente,\nla fattura di ottobre è disponibile in allegato.\nFastweb",
                'files' => ['fattura-fastweb-ottobre.pdf' => 'Fattura Fastweb ottobre.pdf'],
            ],
            [
                'key' => 'hera', 'sender' => 'Hera <servizioclienti@hera.it>', 'address' => 'servizioclienti@hera.it', 'organization' => 'Hera',
                'subject' => 'La tua fattura è disponibile', 'at' => '2026-10-24 17:30', 'outcome' => MailOutcome::Saved,
                'text' => "Gentile cliente,\nla tua fattura dell'acqua è disponibile nell'area clienti.\nHera",
                'notice' => true,
            ],
            [
                'key' => 'comune', 'sender' => 'Comune di Bologna <tributi@comune.bologna.it>', 'address' => 'tributi@comune.bologna.it', 'organization' => 'Comune di Bologna',
                'subject' => 'TARI 2026 - avviso di pagamento', 'at' => '2026-10-23 11:05', 'outcome' => MailOutcome::Saved,
                'text' => "Gentile contribuente,\nin allegato trova l'avviso di pagamento della TARI 2026 per l'immobile di via Roma 12. L'importo di 196,00 € si può pagare in un'unica soluzione entro il 02/12/2026 con il modello F24 allegato.\nServizio Tributi\nComune di Bologna",
                'files' => ['avviso-tari-2026.pdf' => 'Avviso TARI 2026.pdf'],
                'skipped' => [['name' => 'logo_comune.png', 'reason' => 'too_small']],
            ],
            [
                'key' => 'promo', 'sender' => 'promo@offerte-energia.it', 'address' => 'promo@offerte-energia.it',
                'subject' => 'Nuove offerte luce e gas', 'at' => '2026-10-22 08:03', 'outcome' => MailOutcome::Discarded,
                'text' => "Solo per te: nuove offerte luce e gas.\nPassa al mercato libero oggi stesso.",
                'screening' => ['verdict' => $advert->verdict, 'reason' => $advert->reason],
                'notes' => [(string) MailFlow::afterScreen($advert, true)['note']],
            ],
            [
                'key' => 'edison', 'sender' => 'Edison <fatture@edison.it>', 'address' => 'fatture@edison.it', 'organization' => 'Edison',
                'subject' => 'Bolletta di settembre', 'at' => '2026-10-14 07:58', 'outcome' => MailOutcome::Saved, 'deliveries' => 2,
                'text' => "Gentile cliente,\nla bolletta della luce di settembre è in allegato.\nEdison",
                'files' => ['bolletta-edison-settembre.pdf' => 'Bolletta Edison settembre.pdf'],
            ],
        ];
    }
}
