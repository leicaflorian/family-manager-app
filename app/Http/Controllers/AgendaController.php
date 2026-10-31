<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Agenda\SaveAgendaFilters;
use App\Data\AgendaData;
use App\Data\AgendaFiltersData;
use App\Data\PersonData;
use App\Http\Requests\AgendaFiltersRequest;
use App\Http\Requests\AgendaRequest;
use App\Models\User;
use App\Services\Agenda;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The *Agenda*: what expires and what is to pay or to arrive from today to the end of a period. Everyone of the
 * family sees it.
 */
final class AgendaController extends Controller
{
    public function __construct(private readonly Agenda $agenda) {}

    /**
     * The Agenda of `?months=1|3|6|12`: the period asked is used and remembered, the remembered one is used when
     * the address asks for none, and one month when it asks for another period.
     */
    public function __invoke(AgendaRequest $request, #[CurrentUser] User $user, SaveAgendaFilters $save): Response
    {
        $today = CarbonImmutable::today('Europe/Rome');
        $filters = AgendaFiltersData::fromUser($user);
        $asked = $request->askedMonths();

        if ($asked !== null && $asked !== $filters->months) {
            $filters = new AgendaFiltersData($asked, $filters->person_id, $filters->hidden_areas);
            $save->handle($user, $filters);
        }

        $months = $request->months($filters->months);
        // The page shows the period in use, which is not the remembered one when the address asked for a wrong period.
        $shown = new AgendaFiltersData($months, $filters->person_id, $filters->hidden_areas);
        $until = $this->agenda->period($today, $months);

        return Inertia::render('Agenda', [
            'agenda' => new AgendaData(
                entries: $this->agenda->entries($today, $until),
                months: $months,
                from: $today->toDateString(),
                to: $until->toDateString(),
                people: PersonData::ofFamily($user),
                filters: $shown,
            ),
        ]);
    }

    /**
     * Remembers the filters of the Agenda in the account and goes back to the page.
     */
    public function filters(AgendaFiltersRequest $request, #[CurrentUser] User $user, SaveAgendaFilters $save): RedirectResponse
    {
        $save->handle($user, $request->filters());

        return back();
    }
}
