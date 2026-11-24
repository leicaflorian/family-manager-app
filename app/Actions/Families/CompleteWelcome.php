<?php

declare(strict_types=1);

namespace App\Actions\Families;

use App\Actions\Cards\SaveCard;
use App\Actions\People\SavePerson;
use App\Cards\Types\HomeCard;
use App\Enums\HomeStatus;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Closes the *Welcome*: completes the person of whoever signed in, adds the people and the home, writes the
 * switches of the features and marks the family as welcomed.
 */
final readonly class CompleteWelcome
{
    /**
     * @param  SavePerson  $savePerson  saves each person
     * @param  SaveCard  $saveCard  saves the home as a *Residence* card
     * @param  ChangeFeatures  $changeFeatures  writes the switches of the Budget and of the mail
     */
    public function __construct(private SavePerson $savePerson, private SaveCard $saveCard, private ChangeFeatures $changeFeatures) {}

    /**
     * Saves everything in the current family and fills in `welcomed_at`. The switches are written only when whoever
     * sends them can manage the features of the family; otherwise they are ignored.
     *
     * @param  User  $user  whoever signed in
     * @param  array{birth_date?: ?string, tax_code?: ?string}  $me  the birth date and tax code of whoever signed in
     * @param  list<array{first_name: string, last_name: string, role: string, birth_date?: ?string, tax_code?: ?string}>  $people  the people to add
     * @param  array{name: string, address?: string|null, city?: string|null, is_owned?: bool}|null  $home  the home, or null for later
     * @param  array{budget: bool, mail: bool}|null  $features  the switches, or null to leave them as they are
     */
    public function handle(User $user, array $me, array $people, ?array $home, ?array $features): void
    {
        $family = $user->family;

        DB::transaction(function () use ($user, $family, $me, $people, $home, $features): void {
            $this->completeMe($user, $me);

            foreach ($people as $person) {
                $this->savePerson->handle($person);
            }

            if ($home !== null) {
                $this->saveCard->handle(new HomeCard, [...$home, 'country' => __('Italy'), 'status' => HomeStatus::Active->value]);
            }

            if ($features !== null && Gate::forUser($user)->allows('manageFeatures', $family)) {
                $this->changeFeatures->handle($family, $features['budget'], $features['mail']);
            }

            $family->update(['welcomed_at' => now()]);
        });
    }

    /**
     * Writes the birth date and the tax code of the person of whoever signed in, when there is one.
     *
     * @param  User  $user  whoever signed in
     * @param  array{birth_date?: ?string, tax_code?: ?string}  $me  the two fields
     */
    private function completeMe(User $user, array $me): void
    {
        $person = Person::query()->where('user_id', $user->id)->first();

        if ($person === null || $me === []) {
            return;
        }

        $this->savePerson->handle([
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'initials' => $person->initials,
            'birth_date' => $me['birth_date'] ?? null,
            'tax_code' => $me['tax_code'] ?? null,
        ], $person);
    }
}
