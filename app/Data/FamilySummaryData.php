<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The family at the top of *People* and *Organizations*: its name and how
 * many people and organizations it has, for the two views.
 */
#[TypeScript]
final class FamilySummaryData extends Data
{
    /**
     * @param  string  $name  the name of the family, for example «Bianchi»
     * @param  int  $people  how many people
     * @param  int  $organizations  how many organizations
     */
    public function __construct(
        public readonly string $name,
        public readonly int $people,
        public readonly int $organizations,
    ) {}

    /**
     * The family of whoever signed in.
     *
     * @param  User  $user  whoever signed in
     */
    public static function of(User $user): self
    {
        return new self($user->family->name, Person::query()->count(), Organization::query()->count());
    }
}
