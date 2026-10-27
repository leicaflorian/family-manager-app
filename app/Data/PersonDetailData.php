<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Person;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A person as *A person* shows them and *Edit a person* changes them.
 */
#[TypeScript]
final class PersonDetailData extends Data
{
    /**
     * @param  int  $id  the id
     * @param  string  $first_name  the first name
     * @param  string  $last_name  the last name
     * @param  string  $initials  the initials of the circle
     * @param  string  $role  `parent`, `child` or `other`
     * @param  string  $role_label  who they are in the family, for example «Parent»
     * @param  string|null  $birth_date  the birth date, ISO
     * @param  int|null  $age  the age in years, if the birth date is known
     * @param  string|null  $tax_code  the tax code
     * @param  string|null  $notes  the notes
     * @param  string|null  $account_label  the level of the account, or null without one
     * @param  bool  $is_me  it is the person of whoever is looking
     * @param  bool  $can_edit  whoever is looking can change them
     * @param  bool  $can_delete  whoever is looking can delete them: an `admin`, and the person has no account
     */
    public function __construct(
        public readonly int $id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $initials,
        public readonly string $role,
        public readonly string $role_label,
        public readonly ?string $birth_date,
        public readonly ?int $age,
        public readonly ?string $tax_code,
        public readonly ?string $notes,
        public readonly ?string $account_label,
        public readonly bool $is_me,
        public readonly bool $can_edit,
        public readonly bool $can_delete,
    ) {}

    /**
     * The data of a person, for whoever is looking.
     *
     * @param  Person  $person  the person
     * @param  User  $viewer  whoever is looking
     */
    public static function fromPerson(Person $person, User $viewer): self
    {
        return new self(
            id: $person->id,
            first_name: $person->first_name,
            last_name: $person->last_name,
            initials: $person->initials,
            role: $person->role->value,
            role_label: $person->role->label(),
            birth_date: $person->birth_date?->toDateString(),
            age: $person->birth_date?->age,
            tax_code: $person->tax_code,
            notes: $person->notes,
            account_label: $person->accountLabel(),
            is_me: $person->user_id === $viewer->id,
            can_edit: $viewer->can('update', $person),
            can_delete: $person->user_id === null && $viewer->can('delete', $person),
        );
    }
}
