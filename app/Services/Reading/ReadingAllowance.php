<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Models\AiReading;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * How many automatic readings the current family has left this month: the limit of
 * `config('document_reader.monthly_limit')` less the AI calls of the month. The month is that of Rome.
 */
final readonly class ReadingAllowance
{
    /**
     * The readings left to the current family in the month of a moment, never less than 0.
     *
     * @param  CarbonImmutable  $now  the moment, in any time zone
     */
    public function left(CarbonImmutable $now): int
    {
        $month = $now->setTimezone('Europe/Rome')->startOfMonth();

        // `created_at` is written in the time zone of the app, so the edges of the month of Rome are turned into it
        $timezone = Config::string('app.timezone');
        $used = AiReading::query()
            ->where('created_at', '>=', $month->setTimezone($timezone))
            ->where('created_at', '<', $month->addMonth()->setTimezone($timezone))
            ->count();

        return max(0, Config::integer('document_reader.monthly_limit') - $used);
    }

    /**
     * What the person reads when the readings of the month are used up, with the name of the month of Rome.
     *
     * @param  CarbonImmutable  $now  the moment, in any time zone
     * @param  string  $locale  the language of the message and of the name of the month (`it` or `en`)
     */
    public function usedUpMessage(CarbonImmutable $now, string $locale): string
    {
        return __('You have used all the automatic readings of :month.', [
            'month' => $now->setTimezone('Europe/Rome')->settings(['locale' => $locale])->translatedFormat('F'),
        ], $locale);
    }
}
