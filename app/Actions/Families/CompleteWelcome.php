<?php

declare(strict_types=1);

namespace App\Actions\Families;

use App\Actions\Cards\SaveCard;
use App\Actions\People\SavePerson;
use App\Cards\Types\HomeCard;
use App\Enums\HomeStatus;
use App\Models\Family;
use Illuminate\Support\Facades\DB;

/**
 * Closes the *Welcome*: adds the people and the home, and marks the family as welcomed.
 */
final readonly class CompleteWelcome
{
    /**
     * @param  SavePerson  $savePerson  saves each person
     * @param  SaveCard  $saveCard  saves the home as a *Residence* card
     */
    public function __construct(private SavePerson $savePerson, private SaveCard $saveCard) {}

    /**
     * Creates the people and the home in the current family and fills in `welcomed_at`.
     *
     * @param  Family  $family  the family of whoever signed in
     * @param  list<array{first_name: string, last_name: string, role: string, birth_date?: ?string, tax_code?: ?string}>  $people  the people to add
     * @param  array{name: string, address?: string|null, city?: string|null, is_owned?: bool}|null  $home  the home, or null for later
     */
    public function handle(Family $family, array $people, ?array $home): void
    {
        DB::transaction(function () use ($family, $people, $home): void {
            foreach ($people as $person) {
                $this->savePerson->handle($person);
            }

            if ($home !== null) {
                $this->saveCard->handle(new HomeCard, [...$home, 'country' => __('Italy'), 'status' => HomeStatus::Active->value]);
            }

            $family->update(['welcomed_at' => now()]);
        });
    }
}
