<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\Mail\MailScreeningAnswer;
use App\Data\Reading\ConsumptionReading;
use App\Data\Reading\Reading;
use App\Exceptions\Reading\ReadingFailed;
use App\Services\Reading\ReadingContext;
use App\Services\Reading\ReadingSource;
use App\Support\Reading\BankFilterSuggestion;
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

    /**
     * Asks where the sheet of paper is in a photo, and records the call in `ai_readings` (purpose `crop`, the account
     * signed in, no file) in the current family before the answer is checked. Called only when `isActive()` is true.
     *
     * @param  string  $jpeg  the photo, a small JPEG
     * @return list<array{x: float, y: float}>|null the four corners from 0 to 1, checked and clockwise from the top left one, or null when the model finds no sheet
     *
     * @throws ReadingFailed when the service gives no answer
     * @throws LogicException when the reading is off
     */
    public function corners(string $jpeg): ?array;

    /**
     * Asks the model for the words of a bank filter that takes the transactions to keep and leaves the ones to drop,
     * and, when names are given, for a title, a category and an organization; records the call in `ai_readings`
     * (purpose `bank_filter`, the account signed in, no file) in the current family before the answer is checked.
     * Called only when `isActive()` is true.
     *
     * @param  list<string>  $keep  the texts of the transactions the filter must take, already normalised
     * @param  list<string>  $drop  the texts of the transactions it must leave, already normalised
     * @param  array{categories: list<string>, organizations: list<string>, planned: list<string>}  $names  the names of the family the model picks from; empty lists when only the words are asked
     * @param  string  $locale  the language of the title and the explanation (`it` or `en`)
     *
     * @throws ReadingFailed when the service gives no answer
     * @throws LogicException when the reading is off
     */
    public function bankFilter(array $keep, array $drop, array $names, string $locale): BankFilterSuggestion;

    /**
     * Reads only the consumption of a bill already in the archive (*Read the bills*), and records the call in
     * `ai_readings` (purpose `consumption`, the file, the account given) in the current family before the answer is
     * checked. Called only when `isActive()` is true.
     *
     * @param  ReadingSource  $source  the file as the model reads it
     * @param  int  $fileId  the file, for the row of the call
     * @param  string  $locale  the language of who asked (`it` or `en`)
     * @param  int|null  $userId  the account that asked, for the row of the call (a job has nobody signed in)
     *
     * @throws ReadingFailed when the service gives no answer, or one that cannot be used
     * @throws LogicException when the reading is off
     */
    public function consumption(ReadingSource $source, int $fileId, string $locale, ?int $userId = null): ConsumptionReading;
}
