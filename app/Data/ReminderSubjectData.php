<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The thing a reminder refers to: a card, a document or another record.
 */
#[TypeScript]
final class ReminderSubjectData extends Data
{
    /**
     * @param  string  $type  the type, as the morph map writes it (`vehicle`)
     * @param  int  $id  the id of the record
     * @param  string  $label  its name, for example «Fiat Panda»
     * @param  string|null  $href  its page, null for records without one
     * @param  string  $area  the area of its colour: the one of a card, `general` for a document or a person
     */
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $label,
        public readonly ?string $href,
        public readonly string $area,
    ) {}
}
