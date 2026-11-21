<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Mail\RecordMailFilterCall;
use App\Contracts\DocumentReader;
use App\Data\Banks\BankRuleSuggestion;
use App\Data\Mail\MailScreeningAnswer;
use App\Data\Reading\ConsumptionReading;
use App\Data\Reading\Extraction;
use App\Data\Reading\Reading;
use App\Enums\ReadingPurpose;
use App\Exceptions\Reading\ReadingFailed;
use App\Services\Reading\ReadingContext;
use App\Services\Reading\ReadingSource;
use App\Support\Corners;
use Illuminate\Support\Facades\Auth;

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

    /**
     * What the model finds as the corners of a sheet, before the check.
     *
     * @var list<array{x: float, y: float}>|null
     */
    private ?array $corners = null;

    /** What the model writes as the rule of a bank payment, until `suggestsRule()` says otherwise. */
    private BankRuleSuggestion $rule;

    /**
     * What the short reading of the consumption finds, by the id of the file read; a file not in the map is not a bill.
     *
     * @var array<int, ConsumptionReading>
     */
    private array $consumptions = [];

    /** The readings of the consumption asked so far. */
    private int $consumptionReads = 0;

    /** The rules asked so far. */
    private int $rules = 0;

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
        $this->rule = new BankRuleSuggestion('', '');
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
     * Every request for the rule of a bank payment gives this suggestion.
     *
     * @param  BankRuleSuggestion  $suggestion  what the model writes
     */
    public function suggestsRule(BankRuleSuggestion $suggestion): self
    {
        $this->rule = $suggestion;
        $this->failure = null;

        return $this;
    }

    /**
     * Every search of the corners finds these points (checked as the real reader checks them), or none for null.
     *
     * @param  list<array{x: float, y: float}>|null  $corners  what the model finds, from 0 to 1
     */
    public function findsCorners(?array $corners): self
    {
        $this->corners = $corners;
        $this->failure = null;

        return $this;
    }

    /**
     * The reading of the consumption of this file finds this.
     *
     * @param  int  $fileId  the file read
     * @param  ConsumptionReading  $reading  what the model finds
     */
    public function readsConsumption(int $fileId, ConsumptionReading $reading): self
    {
        $this->consumptions[$fileId] = $reading;

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
     * How many readings of the consumption were asked.
     */
    public function consumptionReads(): int
    {
        return $this->consumptionReads;
    }

    /**
     * How many screenings of a mail were asked.
     */
    public function screenings(): int
    {
        return $this->screenings;
    }

    /**
     * How many rules of a bank payment were asked.
     */
    public function rules(): int
    {
        return $this->rules;
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

        $extraction = $this->extraction;

        // a bill by its file name answers with a consumption, unless the extraction already has one
        if (str_contains(mb_strtolower($context->fileName ?? ''), 'bolletta') && $extraction->consumption_quantity === null) {
            $extraction = $extraction->toArray();
            $extraction = Extraction::from([
                ...$extraction,
                'consumption_quantity' => 312.0,
                'consumption_unit' => 'kwh',
                'period_start' => '2026-08-01',
                'period_end' => '2026-09-30',
            ]);
        }

        return new Reading($extraction, 'gemini', 'gemini-3.8-flash', inputTokens: 1200, outputTokens: 300, cacheReadTokens: 0);
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

    /**
     * Gives the corners of `findsCorners()` after the same check as the real reader, or throws the error of `fails()`.
     * As the real one, it records the call (Gemini 3.8 Flash, 1100 tokens in and 80 out) as a crop of the account
     * signed in, in the current family.
     *
     * @param  string  $jpeg  the photo
     *
     * @throws ReadingFailed when `fails()` was called
     */
    public function corners(string $jpeg): ?array
    {
        if ($this->failure instanceof ReadingFailed) {
            throw $this->failure;
        }

        (new RecordMailFilterCall)->handle('gemini', 'gemini-3.8-flash', 1100, 80, 0, ReadingPurpose::Crop, Auth::id() === null ? null : (int) Auth::id());

        return $this->corners === null ? null : Corners::valid($this->corners);
    }

    /**
     * Counts the request, then gives the suggestion of `suggestsRule()` or throws the error of `fails()`. As the real
     * one, it records the call (Gemini 3.8 Flash, 200 tokens in and 30 out) as a `bank_rule` of the account signed in, unless the error is one with no answer (`ReadingFailed::$paid` false).
     *
     * @param  list<string>  $examples  the texts of the transactions
     * @param  string  $locale  the language of the explanation
     *
     * @throws ReadingFailed when `fails()` was called
     */
    public function bankRule(array $examples, string $locale): BankRuleSuggestion
    {
        $this->rules++;

        $userId = Auth::guard('web')->id();

        // as the real reader, an answer that was paid for is recorded before it is found unusable
        $failure = $this->failure;

        if (! $failure instanceof ReadingFailed || $failure->paid) {
            (new RecordMailFilterCall)->handle('gemini', 'gemini-3.8-flash', 200, 30, 0, ReadingPurpose::BankRule, $userId === null ? null : (int) $userId);
        }

        if ($failure instanceof ReadingFailed) {
            throw $failure;
        }

        return $this->rule;
    }

    /**
     * Counts the reading, then gives what `readsConsumption()` set for the file (not a bill, otherwise) or throws the
     * error of `fails()`. As the real one, it records the call (Gemini 3.8 Flash, 1400 tokens in and 90 out) as a
     * `consumption` of the file and of the account given, unless the error is one with no answer
     * (`ReadingFailed::$paid` false).
     *
     * @param  ReadingSource  $source  the file as the model reads it
     * @param  int  $fileId  the file read
     * @param  string  $locale  the language of who asked
     * @param  int|null  $userId  the account that asked
     *
     * @throws ReadingFailed when `fails()` was called
     */
    public function consumption(ReadingSource $source, int $fileId, string $locale, ?int $userId = null): ConsumptionReading
    {
        $this->consumptionReads++;

        $failure = $this->failure;

        if (! $failure instanceof ReadingFailed || $failure->paid) {
            (new RecordMailFilterCall)->handle('gemini', 'gemini-3.8-flash', 1400, 90, 0, ReadingPurpose::Consumption, $userId, $fileId);
        }

        if ($failure instanceof ReadingFailed) {
            throw $failure;
        }

        return $this->consumptions[$fileId] ?? new ConsumptionReading(isBill: false);
    }
}
