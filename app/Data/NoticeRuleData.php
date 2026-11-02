<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One notice of a group as the settings page edits it.
 */
#[TypeScript]
final class NoticeRuleData extends Data
{
    /**
     * @param  string  $name  the name saved with the row, kept when the page saves it back
     * @param  int  $days  the days from the deadline, 0 for the day itself
     * @param  string  $timing  `before` or `after`
     * @param  string  $channels  `app`, `email` or `both`
     * @param  list<string|int>  $recipients  `['all']` or the ids of the people
     */
    public function __construct(
        public readonly string $name,
        public readonly int $days,
        public readonly string $timing,
        public readonly string $channels,
        public readonly array $recipients,
    ) {}
}
