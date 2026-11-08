<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Contracts\DocumentReader;
use App\Data\Mail\MailScreeningAnswer;
use App\Exceptions\Reading\ReadingFailed;
use App\Models\Organization;
use App\Models\ReceivedMail;
use App\Services\Reading\ReadingAllowance;
use App\Support\Mail\KnownSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * Decides when the model is asked about a mail, from its sender and subject only, and what a failure means.
 * Runs in the family of the mail.
 */
final readonly class MailScreening
{
    /**
     * @param  DocumentReader  $reader  the model, and whether the reading is on
     * @param  ReadingAllowance  $allowance  the readings left this month
     */
    public function __construct(private DocumentReader $reader, private ReadingAllowance $allowance) {}

    /**
     * Asks about a mail. A known sender is never asked about: the mail takes its organization and the answer is
     * null, as when the reading is off or the readings of the month are used up. A service that fails gives an
     * unsure answer, so that the mail goes on to a person.
     *
     * @param  ReceivedMail  $mail  the mail
     * @return MailScreeningAnswer|null the answer, null when the model is not to be asked
     */
    public function ask(ReceivedMail $mail): ?MailScreeningAnswer
    {
        $organization = KnownSender::organizationOf($mail->sender_address, Organization::query()->get());

        if ($organization instanceof Organization) {
            $mail->forceFill(['organization_id' => $organization->id])->save();

            return null;
        }

        $now = CarbonImmutable::now('Europe/Rome');

        if (! $this->reader->isActive() || $this->allowance->left($now) === 0) {
            return null;
        }

        try {
            return $this->reader->screen($mail->sender, (string) $mail->subject, Config::string('app.locale'));
        } catch (ReadingFailed) {
            // A mail is never thrown away because the model could not answer
            return new MailScreeningAnswer('unsure', null);
        }
    }
}
