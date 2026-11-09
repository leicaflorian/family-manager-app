<?php

declare(strict_types=1);

namespace App\Actions\Mail;

use App\Enums\ReadingPurpose;
use App\Models\AiReading;
use App\Support\Reading\ReadingCost;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Writes in `ai_readings` an AI call that has no file, in the current family: by default the one that asked about a
 * mail (no account), or the one that looked for the edges of a sheet (`ReadingPurpose::Crop`, with the account). The
 * call is paid already: a row that cannot be written is reported and the answer stays.
 */
final class RecordMailFilterCall
{
    /**
     * @param  string  $provider  the service
     * @param  string  $model  the model that answered
     * @param  int  $inputTokens  the tokens sent
     * @param  int  $outputTokens  the tokens of the answer
     * @param  int  $cacheReadTokens  the tokens read from the cache of the service
     * @param  ReadingPurpose  $purpose  what the call was for
     * @param  int|null  $userId  the account that made the call, if any
     */
    public function handle(string $provider, string $model, int $inputTokens, int $outputTokens, int $cacheReadTokens, ReadingPurpose $purpose = ReadingPurpose::MailFilter, ?int $userId = null): void
    {
        try {
            AiReading::query()->create([
                'user_id' => $userId,
                'purpose' => $purpose,
                'provider' => $provider,
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cache_read_tokens' => $cacheReadTokens,
                'cost_usd' => ReadingCost::estimate($model, $inputTokens, $outputTokens, CarbonImmutable::now('Europe/Rome')),
            ]);
        } catch (Throwable $error) {
            report($error);
        }
    }
}
