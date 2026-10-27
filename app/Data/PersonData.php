<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Person;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A person in *People*: who they are, whether they have an account, and how
 * many documents and records they have.
 */
#[TypeScript]
final class PersonData extends Data
{
    /**
     * @param  int  $id  the id
     * @param  string  $first_name  the first name
     * @param  string  $last_name  the last name
     * @param  string  $initials  the initials of the circle
     * @param  string  $role_label  who they are in the family, for example «Parent»
     * @param  int|null  $age  the age in years, if the birth date is known
     * @param  string|null  $account_label  the level of the account, or null without one
     * @param  bool  $is_me  it is the person of whoever is looking
     * @param  int  $documents_count  how many documents are theirs
     * @param  int  $records_count  how many records name them
     */
    public function __construct(
        public readonly int $id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $initials,
        public readonly string $role_label,
        public readonly ?int $age,
        public readonly ?string $account_label,
        public readonly bool $is_me,
        public readonly int $documents_count,
        public readonly int $records_count,
    ) {}

    /**
     * A person of the list, with `user` loaded and the documents counted.
     *
     * @param  Person  $person  the person
     * @param  User  $viewer  whoever is looking
     * @param  int  $records  how many records name them, from `LinkedRecords::countByPerson()`
     */
    public static function fromPerson(Person $person, User $viewer, int $records): self
    {
        return new self(
            id: $person->id,
            first_name: $person->first_name,
            last_name: $person->last_name,
            initials: $person->initials,
            role_label: $person->role->label(),
            age: $person->birth_date?->age,
            account_label: $person->accountLabel(),
            is_me: $person->user_id === $viewer->id,
            documents_count: $person->documents_count ?? 0,
            records_count: $records,
        );
    }
}
