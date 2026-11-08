<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\MailOutcome;
use App\Models\Document;
use App\Models\ReceivedMail;
use App\Support\Linkables;

/**
 * What the other pages need from *Received mail*: the latest mails that brought files, with the documents born from
 * those files and, through them, the cards the documents are linked to (the Home shows «collegata a Gas - Enel»).
 */
final class ReceivedMails
{
    /**
     * The latest mails that saved files, newest first. A mail counts when it was saved and at least one of its files is
     * still there: a discarded, empty or confirmation mail has nothing to show, and one whose files were all deleted
     * has nothing left to point at (it stays in *Received mail*). Everything is loaded together, whatever the number
     * of mails.
     *
     * @param  int  $limit  how many mails at most
     * @return list<array{mail: ReceivedMail, documents: list<Document>}>
     */
    public function recent(int $limit): array
    {
        $mails = ReceivedMail::query()
            ->where('outcome', MailOutcome::Saved)
            ->whereHas('files')
            ->with(['organization', 'files.document', ...$this->cardRelations()])
            ->latest('received_at')
            ->latest('id')
            ->limit($limit)
            ->get();

        $rows = [];

        foreach ($mails as $mail) {
            $documents = [];

            foreach ($mail->files as $file) {
                if ($file->document instanceof Document) {
                    $documents[] = $file->document;
                }
            }

            $rows[] = ['mail' => $mail, 'documents' => $documents];
        }

        return $rows;
    }

    /**
     * The relations that reach the cards of a document from a mail, with what each card's name reads.
     *
     * @return list<string>
     */
    private function cardRelations(): array
    {
        $relations = [];

        foreach (Linkables::WITH_DOCUMENTS as $type => $relation) {
            $class = Linkables::TYPES[$type];
            $path = 'files.document.'.$relation;
            $relations[] = $path;

            foreach ($class::labelRelations() as $labelRelation) {
                $relations[] = $path.'.'.$labelRelation;
            }
        }

        return $relations;
    }
}
