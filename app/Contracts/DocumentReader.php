<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\Mail\MailScreeningAnswer;
use App\Data\Reading\Reading;
use App\Exceptions\Reading\ReadingFailed;
use App\Services\Reading\ReadingContext;
use App\Services\Reading\ReadingSource;
use LogicException;

/**
 * Reads a document with an AI service: what is on the paper, as the fields of an extraction.
 */
interface DocumentReader
{
    /**
     * Whether the reading is on: a service is chosen and has its key (or, for a model of one's own, its address).
     */
    public function isActive(): bool;

    /**
     * Reads a file. Called only when `isActive()` is true.
     *
     * @param  ReadingSource  $source  the file as the model reads it
     * @param  ReadingContext  $context  the lists the model picks from and the notes of the file
     * @param  string  $locale  the language of who uploaded the file (`it` or `en`)
     *
     * @throws ReadingFailed when the service gives no extraction
     * @throws LogicException when the reading is off
     */
    public function read(ReadingSource $source, ReadingContext $context, string $locale): Reading;

    /**
     * Asks whether a mail is worth reading, from its sender and subject only, and records the call in `ai_readings`
     * (purpose `mail_filter`, no file, no account) in the current family. Called only when `isActive()` is true.
     *
     * @param  string  $sender  the sender, as the mail shows it
     * @param  string  $subject  the subject
     * @param  string  $locale  the language of the reason (`it` or `en`)
     *
     * @throws ReadingFailed when the service gives no answer
     * @throws LogicException when the reading is off
     */
    public function screen(string $sender, string $subject, string $locale): MailScreeningAnswer;
}
