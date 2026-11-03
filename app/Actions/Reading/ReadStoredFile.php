<?php

declare(strict_types=1);

namespace App\Actions\Reading;

use App\Contracts\DocumentReader;
use App\Data\Reading\Reading;
use App\Enums\ReadingPurpose;
use App\Enums\ReadingStatus;
use App\Exceptions\Reading\ReadingFailed;
use App\Exceptions\Reading\ReadingSkipped;
use App\Jobs\PreparePreview;
use App\Models\AiReading;
use App\Models\File;
use App\Models\User;
use App\Services\Reading\DocumentProposal;
use App\Services\Reading\ReadingAllowance;
use App\Services\Reading\ReadingContext;
use App\Services\Reading\ReadingSource;
use App\Support\CurrentFamily;
use App\Support\Reading\ReadingCost;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads a stored file with the AI service: the file is `reading`, then `done` with what was read and the proposal
 * of its document. The call is written in `ai_readings` and in the log as soon as the service answers, and the
 * preview of the file is prepared by a job of its own.
 */
final readonly class ReadStoredFile
{
    /**
     * @param  DocumentReader  $reader  the AI service
     * @param  DocumentProposal  $proposals  turns what was read into the values of the form of a document
     * @param  ReadingAllowance  $allowance  the readings left this month
     */
    public function __construct(
        private DocumentReader $reader,
        private DocumentProposal $proposals,
        private ReadingAllowance $allowance,
    ) {}

    /**
     * Reads the file. A file the model does not read ends `skipped`; a service switched off, or the readings of the
     * month used up, end `off`.
     *
     * @param  File  $file  the file to read
     * @param  User|null  $user  who uploaded it
     * @param  string  $locale  the language of who uploaded it (`it` or `en`)
     * @param  ReadingPurpose  $purpose  what the reading is for, as `ai_readings` records it
     * @param  CarbonImmutable  $now  the moment of the reading: its price and `read_at`
     *
     * @throws ReadingFailed when the service gives no extraction; the file stays `reading`
     */
    public function handle(File $file, ?User $user, string $locale, ReadingPurpose $purpose, CarbonImmutable $now): void
    {
        if (! $this->reader->isActive()) {
            $file->markReading(ReadingStatus::Off, __('Automatic reading is not on.'));

            return;
        }

        // The limit is checked again: other files of the family may have been read while this one waited
        if ($this->allowance->left($now) === 0) {
            $file->markReading(ReadingStatus::Off, $this->allowance->usedUpMessage($now, $locale));

            return;
        }

        $file->markReading(ReadingStatus::Reading);

        try {
            $source = ReadingSource::of($file);
        } catch (ReadingSkipped $skipped) {
            $file->markReading(ReadingStatus::Skipped, $skipped->getMessage());

            return;
        }

        $context = ReadingContext::for($file);
        $reading = $this->reader->read($source, $context, $locale);

        // The reading is paid from here: it is counted before anything else can fail
        $cost = ReadingCost::estimate($reading->model, $reading->inputTokens, $reading->outputTokens, $now);
        $this->record($file, $user, $purpose, $reading, $cost);
        $this->log($file, $reading, $cost);

        $proposal = $this->proposals->of($reading->extraction, $context, $locale);

        $file->forceFill([
            'reading_status' => ReadingStatus::Done,
            'reading_error' => null,
            'reading' => [
                'extraction' => $reading->extraction->toArray(),
                'proposal' => $proposal->toArray(),
                'provider' => $reading->provider,
                'model' => $reading->model,
                'read_at' => $now->toIso8601String(),
            ],
        ])->save();

        // In a job of its own: a slow conversion must not make the queue try the paid reading again
        dispatch(new PreparePreview($file->id, $file->family_id));
    }

    /**
     * Writes the call in `ai_readings`, in the family of the file. The reading is paid already: if the row cannot
     * be written, the error is reported and the reading stays.
     *
     * @param  File  $file  the file read
     * @param  User|null  $user  who uploaded it
     * @param  ReadingPurpose  $purpose  what the reading was for
     * @param  Reading  $reading  the reading, with its tokens
     * @param  float|null  $cost  the estimated cost in dollars, null for a model without a price
     */
    private function record(File $file, ?User $user, ReadingPurpose $purpose, Reading $reading, ?float $cost): void
    {
        try {
            CurrentFamily::using($file->family_id, static fn (): AiReading => AiReading::query()->create([
                'user_id' => $user?->id,
                'file_id' => $file->id,
                'purpose' => $purpose,
                'provider' => $reading->provider,
                'model' => $reading->model,
                'input_tokens' => $reading->inputTokens,
                'output_tokens' => $reading->outputTokens,
                'cache_read_tokens' => $reading->cacheReadTokens,
                'cost_usd' => $cost,
            ]));
        } catch (Throwable $error) {
            report($error);
        }
    }

    /**
     * Writes the line of the reading in the log, as the reader of Directus did.
     *
     * @param  File  $file  the file read
     * @param  Reading  $reading  the reading
     * @param  float|null  $cost  the estimated cost in dollars
     */
    private function log(File $file, Reading $reading, ?float $cost): void
    {
        $x = $reading->extraction;

        Log::info("[document-reader] file {$file->id} read with {$reading->provider}/{$reading->model}: "
            .($x->kind ?? 'unknown').', '.($x->confidence ?? 'unknown')
            ."; tokens in {$reading->inputTokens}, out {$reading->outputTokens}"
            .($cost === null ? ', no price for this model' : sprintf(', about $%.4f', $cost)));
    }
}
