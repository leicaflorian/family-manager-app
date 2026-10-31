<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Person;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The filters of the Agenda: the period, the person and the areas turned off.
 */
#[TypeScript]
final class AgendaFiltersData extends Data
{
    /**
     * @param  int  $months  the period, 1, 3, 6 or 12 months
     * @param  int|null  $person_id  the person whose entries are shown; null for the whole family
     * @param  list<string>  $hidden_areas  the areas turned off, for example `work`
     */
    public function __construct(
        public readonly int $months = 1,
        public readonly ?int $person_id = null,
        public readonly array $hidden_areas = [],
    ) {}

    /**
     * The filters saved in an account, the defaults when it has none. A person who is gone is dropped.
     *
     * @param  User  $user  the account
     */
    public static function fromUser(User $user): self
    {
        $saved = $user->agenda_filters;

        if ($saved === null) {
            return new self;
        }

        $personId = $saved['person_id'];

        return new self(
            $saved['months'],
            $personId !== null && Person::query()->whereKey($personId)->exists() ? $personId : null,
            $saved['hidden_areas'],
        );
    }
}
