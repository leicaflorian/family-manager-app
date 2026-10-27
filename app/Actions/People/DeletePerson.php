<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Models\Person;
use App\Support\Linkables;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a person of the family. A person with an account is not deleted:
 * the account goes first.
 */
final readonly class DeletePerson
{
    /**
     * The records that cannot exist without their person: the database
     * deletes them with the person, and their links to the documents go here.
     */
    private const array OWNED = ['employment', 'health_event', 'enrollment'];

    /**
     * Deletes the person. Their documents and the other records stay, without the person.
     *
     * @param  Person  $person  the person
     *
     * @throws ValidationException if the person has an account
     */
    public function handle(Person $person): void
    {
        if ($person->user_id !== null) {
            throw ValidationException::withMessages([
                'person' => __(':name has an account: to delete the person, the account must be deleted first.', ['name' => $person->first_name]),
            ]);
        }

        DB::transaction(static function () use ($person): void {
            foreach (self::OWNED as $type) {
                $ids = Linkables::TYPES[$type]::query()->where('person_id', $person->id)->pluck('id');
                DB::table('documentables')->where('documentable_type', $type)->whereIn('documentable_id', $ids)->delete();
            }

            $person->delete();
        });
    }
}
