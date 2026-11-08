<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Mail\RecordMailFilterCall;
use App\Contracts\DocumentReader;
use App\Data\Mail\MailScreeningAnswer;
use App\Data\Reading\Extraction;
use App\Data\Reading\Reading;
use App\Exceptions\Reading\ReadingFailed;
use App\Services\Reading\ReadingContext;
use App\Services\Reading\ReadingSource;

/**
 * A reader that never calls a service, for the tests of what comes before and after a reading: it answers with
 * the extraction it was given, or fails with the error it was given, and counts the readings.
 *
 * Bound in a test with `app()->instance(DocumentReader::class, $reader = (new FakeDocumentReader)->returns(…))`.
 */
final class FakeDocumentReader implements DocumentReader
{
    /** What a reading gives, until `returns()` says otherwise: an empty extraction of an unknown paper. */
    private Extraction $extraction;

    /** The error every reading throws, if any. */
    private ?ReadingFailed $failure = null;

    /**
     * The error one reading throws, by its number from 1, while the others answer.
     *
     * @var array{reading: int, failure: ReadingFailed}|null
     */
    private ?array $failureAt = null;

    /** Whether the reading is on. */
    private bool $active = true;

    /** What a screening of a mail gives, until `screens()` says otherwise: unsure. */
    private MailScreeningAnswer $screening;

    /** The screenings asked so far. */
    private int $screenings = 0;

    /** The readings asked so far. */
    private int $reads = 0;

    /**
     * Starts with an empty extraction of an unknown paper.
     */
    public function __construct()
    {
        $this->extraction = Extraction::from(['kind' => 'other', 'confidence' => 'low']);
        $this->screening = new MailScreeningAnswer('unsure', null);
    }

    /**
     * Every reading gives this extraction.
     *
     * @param  Extraction  $extraction  what the model reads
     */
    public function returns(Extraction $extraction): self
    {
        $this->extraction = $extraction;
        $this->failure = null;

        return $this;
    }

    /**
     * Every screening of a mail gives this answer.
     *
     * @param  MailScreeningAnswer  $answer  what the model answers
     */
    public function screens(MailScreeningAnswer $answer): self
    {
        $this->screening = $answer;
        $this->failure = null;

        return $this;
    }

    /**
     * Every reading fails with this error.
     *
     * @param  ReadingFailed  $failure  the error of the reading
     */
    public function fails(ReadingFailed $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    /**
     * Only the reading with this number (from 1) fails, with this error; the others answer.
     *
     * @param  int  $reading  the number of the reading that fails
     * @param  ReadingFailed  $failure  its error
     */
    public function failsAt(int $reading, ReadingFailed $failure): self
    {
        $this->failureAt = ['reading' => $reading, 'failure' => $failure];

        return $this;
    }

    /**
     * The reading is off, as without a key.
     */
    public function off(): self
    {
        $this->active = false;

        return $this;
    }

    /**
     * How many readings were asked.
     */
    public function reads(): int
    {
        return $this->reads;
    }

    /**
     * How many screenings of a mail were asked.
     */
    public function screenings(): int
    {
        return $this->screenings;
    }

    /**
     * Whether the reading is on: true unless `off()` was called.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Counts the reading, then gives the extraction of `returns()` or throws the error of `fails()`. The reading
     * is made by Gemini 3.8 Flash, with 1200 tokens in and 300 out.
     *
     * @param  ReadingSource  $source  the file as the model reads it
     * @param  ReadingContext  $context  the lists the model picks from
     * @param  string  $locale  the language of who uploaded the file
     *
     * @throws ReadingFailed when `fails()` was called, or this is the reading of `failsAt()`
     */
    public function read(ReadingSource $source, ReadingContext $context, string $locale): Reading
    {
        $this->reads++;

        if ($this->failure instanceof ReadingFailed) {
            throw $this->failure;
        }

        if ($this->failureAt !== null && $this->failureAt['reading'] === $this->reads) {
            throw $this->failureAt['failure'];
        }

        return new Reading($this->extraction, 'gemini', 'gemini-3.8-flash', inputTokens: 1200, outputTokens: 300, cacheReadTokens: 0);
    }

    /**
     * Counts the screening, then gives the answer of `screens()` or throws the error of `fails()`. As the real one,
     * it records the call (Gemini 3.8 Flash, 300 tokens in and 40 out) in the current family.
     *
     * @param  string  $sender  the sender of the mail
     * @param  string  $subject  the subject of the mail
     * @param  string  $locale  the language of the reason
     *
     * @throws ReadingFailed when `fails()` was called
     */
    public function screen(string $sender, string $subject, string $locale): MailScreeningAnswer
    {
        $this->screenings++;

        if ($this->failure instanceof ReadingFailed) {
            throw $this->failure;
        }

        (new RecordMailFilterCall)->handle('gemini', 'gemini-3.8-flash', 300, 40, 0);

        return $this->screening;
    }
}
