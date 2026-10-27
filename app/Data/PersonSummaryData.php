<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Person;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A person of the family, just enough for a list or for their circle.
 */
#[TypeScript]
final class PersonSummaryData extends Data
{
    /**
     * @param  int  $id  the id of the person
     * @param  string  $first_name  the first name
     * @param  string  $last_name  the last name
     * @param  string  $initials  the initials of the circle
     */
    public function __construct(
        public readonly int $id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $initials,
    ) {}

    /**
     * The data of a person.
     *
     * @param  Person  $person  the person
     */
    public static function fromPerson(Person $person): self
    {
        return new self($person->id, $person->first_name, $person->last_name, $person->initials);
    }
}
