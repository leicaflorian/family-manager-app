<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Enums\ReadingPurpose;
use App\Models\AiReading;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * How many automatic readings the current family has left this month: the limit of
 * `config('document_reader.monthly_limit')` less the AI calls of the month, except the ones that find the edges of a photo (they have their own limit a day). The month is that of Rome.
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
        [$from, $to] = $this->monthEdges($now);
        $used = AiReading::query()
            ->where('purpose', '!=', ReadingPurpose::Crop)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->count();

        return max(0, Config::integer('document_reader.monthly_limit') - $used);
    }

    /**
     * The first moment of the month of Rome that holds a moment and of the next one, in the time zone `created_at`
     * is written in (that of the app), so they compare correctly with the stored readings.
     *
     * @param  CarbonImmutable  $now  the moment, in any time zone
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} the start of the month (included) and of the next (not)
     */
    public function monthEdges(CarbonImmutable $now): array
    {
        $month = $now->setTimezone('Europe/Rome')->startOfMonth();
        $timezone = Config::string('app.timezone');

        return [$month->setTimezone($timezone), $month->addMonth()->setTimezone($timezone)];
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
