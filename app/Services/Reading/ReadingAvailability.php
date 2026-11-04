<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Contracts\DocumentReader;
use Carbon\CarbonImmutable;

/**
 * Whether a reading can be asked right now, and if not why: the reading is off, or the family has used the
 * readings of the month. For a page that offers the reading and must say why it does not.
 */
final readonly class ReadingAvailability
{
    /**
     * @param  DocumentReader  $reader  says whether the reading is on
     * @param  ReadingAllowance  $allowance  the readings left this month
     */
    public function __construct(private DocumentReader $reader, private ReadingAllowance $allowance) {}

    /**
     * Why a reading cannot be asked, in the language given; null when it can.
     *
     * @param  string  $locale  the language of the sentence (`it` or `en`)
     */
    public function unavailable(string $locale): ?string
    {
        if (! $this->reader->isActive()) {
            return __('Automatic reading is not on.', [], $locale);
        }

        $now = CarbonImmutable::now('Europe/Rome');

        return $this->allowance->left($now) === 0 ? $this->allowance->usedUpMessage($now, $locale) : null;
    }
}
