<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Agenda\SaveAgendaFilters;
use App\Actions\Agenda\UpdateDeadline;
use App\Data\AgendaData;
use App\Data\AgendaEntryData;
use App\Data\AgendaFiltersData;
use App\Data\PersonData;
use App\Http\Middleware\LimitVisit;
use App\Http\Requests\Agenda\UpdateDeadlineRequest;
use App\Http\Requests\AgendaFiltersRequest;
use App\Http\Requests\AgendaRequest;
use App\Models\User;
use App\Services\Agenda;
use App\Services\AgendaTasks;
use App\Services\FamilyAreas;
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
    public function __construct(private readonly Agenda $agenda, private readonly AgendaTasks $tasks, private readonly FamilyAreas $familyAreas) {}

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

            // A visit of the owner shows the period asked but writes nothing in the account it looks as
            if (! LimitVisit::isOpen($request)) {
                $save->handle($user, $filters);
            }
        }

        $months = $request->months($filters->months);
        // The page shows the period in use, which is not the remembered one when the address asked for a wrong period.
        $shown = new AgendaFiltersData($months, $filters->person_id, $filters->hidden_areas);
        $until = $this->agenda->period($today, $months);

        // Incomes are not things to do; late deadlines and unpaid payments stay for 90 days, tasks without limit
        $entries = [
            ...array_filter($this->agenda->entries($today, $until, 90, 90), static fn (AgendaEntryData $entry): bool => $entry->kind !== 'income'),
            ...$this->tasks->open($today, $until),
        ];
        usort($entries, static fn (AgendaEntryData $a, AgendaEntryData $b): int => [$a->date, mb_strtolower($a->title)] <=> [$b->date, mb_strtolower($b->title)]);

        return Inertia::render('Agenda', [
            'agenda' => new AgendaData(
                entries: $entries,
                months: $months,
                from: $today->toDateString(),
                to: $until->toDateString(),
                people: PersonData::ofFamily($user),
                filters: $shown,
                areas: AgendaData::areas($this->familyAreas),
                overdue: count(array_filter($entries, static fn (AgendaEntryData $entry): bool => $entry->date < $today->toDateString())),
                view: 'todo',
                family_hidden: $this->familyAreas->hiddenValues(),
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

    /**
     * *Update the date* and *Renewed*: the new date of a passed deadline.
     *
     * @param  UpdateDeadlineRequest  $request  the card of the address and the new date
     * @param  UpdateDeadline  $update  the action
     */
    public function updateDeadline(UpdateDeadlineRequest $request, UpdateDeadline $update): RedirectResponse
    {
        $update->handle(
            $request->source(),
            $request->card(),
            CarbonImmutable::parse($request->string('date')->toString(), 'Europe/Rome'),
            $request->boolean('renewed'),
            CarbonImmutable::today('Europe/Rome'),
        );

        return back();
    }
}
