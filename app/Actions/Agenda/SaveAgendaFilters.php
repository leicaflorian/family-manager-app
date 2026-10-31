<?php

declare(strict_types=1);

namespace App\Actions\Agenda;

use App\Data\AgendaFiltersData;
use App\Models\User;

/**
 * Remembers the filters of the Agenda in the account, so they come back on every device.
 */
final readonly class SaveAgendaFilters
{
    /**
     * Saves the filters, with each area once.
     *
     * @param  User  $user  the account
     * @param  AgendaFiltersData  $filters  the filters to remember
     */
    public function handle(User $user, AgendaFiltersData $filters): void
    {
        $user->forceFill(['agenda_filters' => [
            'months' => $filters->months,
            'person_id' => $filters->person_id,
            'hidden_areas' => array_values(array_unique($filters->hidden_areas)),
        ]])->save();
    }
}
