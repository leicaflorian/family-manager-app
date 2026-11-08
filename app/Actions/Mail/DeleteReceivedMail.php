<?php

declare(strict_types=1);

namespace App\Actions\Mail;

use App\Models\Document;
use App\Models\File;
use App\Models\ReceivedMail;
use App\Services\FileStore;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * *Delete the mail*: the mail goes with its text and its notices in the bell, and with its notice not worked on
 * (still in *To sort* and not a document). The attachments stay where they are and lose the link to the mail.
 */
final readonly class DeleteReceivedMail
{
    /**
     * @param  SaveMailNotice  $notices  finds the notice of the mail
     * @param  FileStore  $files  deletes the notice with its file on the disk
     */
    public function __construct(private SaveMailNotice $notices, private FileStore $files) {}

    /**
     * Deletes the mail.
     *
     * @param  ReceivedMail  $mail  the mail, of the family
     *
     * @throws Throwable when the mail or its notice is not deleted
     */
    public function handle(ReceivedMail $mail): void
    {
        DB::transaction(function () use ($mail): void {
            $notice = $this->notices->find($mail);

            if ($notice instanceof File && $notice->to_sort && ! $notice->document instanceof Document) {
                $this->files->delete($notice);
            }

            // the links of the attachments, of the log and of the notices of the bell follow the foreign keys
            $mail->delete();
        });
    }
}
