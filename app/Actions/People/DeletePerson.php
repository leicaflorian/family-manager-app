<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Actions\Reminders\ForgetRemindable;
use App\Actions\Reminders\SyncReminders;
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
     * @param  ForgetRemindable  $forget  Takes the person and their records out of the reminders.
     * @param  SyncReminders  $reminders  Removes the open automatic reminders of the records the database deletes.
     */
    public function __construct(private ForgetRemindable $forget, private SyncReminders $reminders) {}

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

        DB::transaction(function () use ($person): void {
            foreach (self::OWNED as $type) {
                // The database deletes these without events, so their reminders are taken care of here
                foreach (Linkables::TYPES[$type]::query()->where('person_id', $person->id)->get() as $record) {
                    DB::table('documentables')->where('documentable_type', $type)->where('documentable_id', $record->id)->delete();
                    $this->reminders->forget($record);
                    $this->forget->handle($record);
                }
            }

            $person->delete();
            $this->forget->handle($person);
        });
    }
}
