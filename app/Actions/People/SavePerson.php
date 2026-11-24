<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Enums\PersonRole;
use App\Models\Person;
use App\Support\Initials;
use Illuminate\Support\Str;

/**
 * Creates a person of the family or updates one: the only place that does it.
 */
final class SavePerson
{
    /**
     * Saves the person. Initials come from the name unless they are given, and the tax code is stored in upper case.
     *
     * @param  array{first_name: string, last_name: string, role?: PersonRole|string, birth_date?: ?string, tax_code?: ?string, initials?: ?string, user_id?: ?int, notes?: ?string}  $data  the validated columns
     * @param  Person|null  $person  the person to update; a new one is created if null
     * @return Person the saved person
     */
    public function handle(array $data, ?Person $person = null): Person
    {
        if (blank($data['initials'] ?? null)) {
            $data['initials'] = Initials::of($data['first_name'], $data['last_name']);
        }

        if (isset($data['tax_code'])) {
            $data['tax_code'] = Str::upper($data['tax_code']);
        }

        $person ??= new Person;
        $person->fill($data)->save();

        return $person;
    }
}
