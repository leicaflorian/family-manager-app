<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An alert group in the menu of the reminder form, with the people who get its notices.
 */
#[TypeScript]
final class ReminderGroupChoiceData extends Data
{
    /**
     * @param  string  $value  the id of the group, as text
     * @param  string  $label  the name of the group
     * @param  list<string>  $recipients  the first names of the accounts that get a notice of the group
     */
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly array $recipients,
    ) {}
}
