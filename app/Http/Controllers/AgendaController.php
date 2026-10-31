<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\AgendaData;
use App\Data\AgendaFiltersData;
use App\Data\PersonData;
use App\Http\Requests\AgendaRequest;
use App\Models\User;
use App\Services\Agenda;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
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
     * The Agenda of `?months=1|3|6|12`, one month when the address asks for another period.
     */
    public function __invoke(AgendaRequest $request, #[CurrentUser] User $user): Response
    {
        $today = CarbonImmutable::today('Europe/Rome');
        $months = $request->months();
        $until = $this->agenda->period($today, $months);

        return Inertia::render('Agenda', [
            'agenda' => new AgendaData(
                entries: $this->agenda->entries($today, $until),
                months: $months,
                from: $today->toDateString(),
                to: $until->toDateString(),
                people: PersonData::ofFamily($user),
                filters: new AgendaFiltersData,
            ),
        ]);
    }
}
