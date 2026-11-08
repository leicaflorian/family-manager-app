<?php

declare(strict_types=1);

namespace App\Actions\Mail;

use App\Actions\Reading\QueueReading;
use App\Enums\MailOutcome;
use App\Enums\ReadingPurpose;
use App\Enums\ReadingStatus;
use App\Jobs\ReadFile;
use App\Models\File;
use App\Models\ReceivedMail;
use App\Support\Mail\MailFlow;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * *Salva come avviso*: a mail the model discarded, or left under review, becomes its notice in *To sort* by hand.
 * The notice is read, and no notice goes to the bell: whoever pressed the button knows already. The mail keeps no
 * answer of the model, so a job still to come takes the notice as saved by hand and does nothing.
 */
final readonly class SaveAsNotice
{
    /**
     * @param  SaveMailNotice  $notices  finds and saves the notice
     * @param  QueueReading  $readings  marks the notice to read
     */
    public function __construct(private SaveMailNotice $notices, private QueueReading $readings) {}

    /**
     * Saves the notice of the mail and sends its reading.
     *
     * @param  ReceivedMail  $mail  the mail, of the family
     * @return File the notice saved
     *
     * @throws ValidationException when the notice is there already, or the mail has no notice to save
     * @throws Throwable when the notice is not saved
     */
    public function handle(ReceivedMail $mail): File
    {
        try {
            $notice = DB::transaction(function () use ($mail): File {
                $mail = ReceivedMail::query()->whereKey($mail->id)->lockForUpdate()->firstOrFail();

                if ($this->notices->find($mail) instanceof File) {
                    throw $this->alreadySaved();
                }

                $notice = MailFlow::canSaveAsNotice($mail->outcome, count($mail->savedEntries()))
                    ? $this->notices->handle($mail)
                    : null;

                if (! $notice instanceof File) {
                    throw ValidationException::withMessages(['notice' => __('This mail has no notice to save.')]);
                }

                $mail->forceFill(['outcome' => MailOutcome::Saved])->save();

                return $notice;
            });
        } catch (UniqueConstraintViolationException) {
            // the job saved the same notice after the check above: the unique key of the mail said no
            throw $this->alreadySaved();
        }

        // The notice was written without a person, in the language of the installation: so is its reading
        $locale = Config::string('app.locale');

        if ($this->readings->prepare($notice, $locale) === ReadingStatus::Queued) {
            dispatch(new ReadFile($notice->id, $notice->family_id, null, $locale, ReadingPurpose::MailNotice));
        }

        return $notice;
    }

    /**
     * The answer when the notice of the mail is in *To sort* already.
     */
    private function alreadySaved(): ValidationException
    {
        return ValidationException::withMessages(['notice' => __('The notice is already in To sort')]);
    }
}
