<?php

declare(strict_types=1);

namespace App\Actions\Families;

use App\Models\Family;
use App\Models\Home;
use App\Models\Person;
use App\Support\Initials;
use Illuminate\Support\Facades\DB;

/**
 * Closes the *Welcome*: adds the people and the home, and marks the family as welcomed.
 */
final class CompleteWelcome
{
    /**
     * Creates the people and the home in the current family and fills in `welcomed_at`.
     *
     * @param  Family  $family  the family of whoever signed in
     * @param  list<array{first_name: string, last_name: string, role: string}>  $people  the people to add
     * @param  array{name: string, address?: string|null, city?: string|null, is_owned?: bool}|null  $home  the home, or null for later
     */
    public function handle(Family $family, array $people, ?array $home): void
    {
        DB::transaction(function () use ($family, $people, $home): void {
            foreach ($people as $person) {
                Person::query()->create([
                    ...$person,
                    'initials' => Initials::of($person['first_name'], $person['last_name']),
                ]);
            }

            if ($home !== null) {
                Home::query()->create([...$home, 'country' => __('Italy')]);
            }

            $family->update(['welcomed_at' => now()]);
        });
    }
}
