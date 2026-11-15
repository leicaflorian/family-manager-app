<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Agenda\SaveAgendaFilters;
use App\Data\AgendaCalendarData;
use App\Data\AgendaData;
use App\Data\AgendaEntryData;
use App\Data\AgendaFiltersData;
use App\Data\PersonData;
use App\Http\Middleware\LimitVisit;
use App\Http\Requests\AgendaRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Inertia;

/**
 * The props that the page *Agenda* needs, built in one place: the Agenda itself and the task form shown over it
 * (`ReminderController`) send the same ones.
 */
final readonly class AgendaPage
{
    public function __construct(
        private Agenda $agenda,
        private AgendaTasks $tasks,
        private AgendaCalendar $calendar,
        private FamilyAreas $familyAreas,
        private SaveAgendaFilters $save,
    ) {}

    /**
     * The `agenda`, `calendar`, `done` and `done_next_page` props of `?months=`, `?view=` and `?month=` of the
     * address. The period and the view (list or calendar) asked are used and remembered, the remembered ones are
     * used when the address asks for none, and one month when it asks for another period. The props are closures,
     * so a partial reload that does not ask for them does not count anything.
     *
     * @param  AgendaRequest  $request  the address
     * @param  User  $user  who looks at the page, whose filters are remembered
     * @return array{agenda: callable(): AgendaData, calendar: callable(): ?AgendaCalendarData, done: mixed, done_next_page: callable(): ?int}
     */
    public function props(AgendaRequest $request, User $user): array
    {
        $today = CarbonImmutable::today('Europe/Rome');
        $filters = AgendaFiltersData::fromUser($user);
        $asked = $request->askedMonths();
        $askedView = $request->askedView();
        $calendar = $askedView === null || $askedView === 'done' ? $filters->calendar : $askedView === 'calendar';

        if (($asked !== null && $asked !== $filters->months) || $calendar !== $filters->calendar) {
            $filters = new AgendaFiltersData($asked ?? $filters->months, $filters->person_id, $filters->hidden_areas, $calendar);

            // A visit of the owner shows the period asked but writes nothing in the account it looks as
            if (! LimitVisit::isOpen($request)) {
                $this->save->handle($user, $filters);
            }
        }

        $months = $request->months($filters->months);
        // The page shows the period in use, which is not the remembered one when the address asked for a wrong period.
        $shown = new AgendaFiltersData($months, $filters->person_id, $filters->hidden_areas, $filters->calendar);
        $until = $this->agenda->period($today, $months);
        // The view of the address, else the one remembered: the done view is never remembered
        $view = $askedView ?? ($filters->calendar ? 'calendar' : 'todo');
        /** @var array{entries: list<AgendaEntryData>, next: int|null}|null $donePage */
        $donePage = null;
        $done = function () use (&$donePage, $view, $today, $request, $shown): ?array {
            if ($view === 'done') {
                $donePage ??= $this->tasks->done($today, $request->donePage(), $shown->person_id);
            }

            return $donePage;
        };

        return [
            'agenda' => fn (): AgendaData => new AgendaData(
                entries: $this->calendar->open($today, $until),
                months: $months,
                from: $today->toDateString(),
                to: $until->toDateString(),
                people: PersonData::ofFamily($user),
                filters: $shown,
                areas: AgendaData::areas($this->familyAreas),
                view: $view,
                family_hidden: $this->familyAreas->hiddenValues(),
            ),
            'calendar' => fn (): ?AgendaCalendarData => $view === 'calendar' ? $this->calendar->month($today, $request->month($today)) : null,
            // Own props of the page, because only a top-level prop can merge: the pages of the done rows add up
            'done' => Inertia::merge(fn (): array => $done()['entries'] ?? []),
            'done_next_page' => fn (): ?int => $done()['next'] ?? null,
        ];
    }
}
