<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Agenda\SaveAgendaFilters;
use App\Data\AgendaData;
use App\Data\AgendaEntryData;
use App\Data\AgendaFiltersData;
use App\Data\PersonData;
use App\Enums\AgendaReminderState;
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
        private FamilyAreas $familyAreas,
        private SaveAgendaFilters $save,
    ) {}

    /**
     * The `agenda`, `done` and `done_next_page` props of `?months=` and `?view=` of the address. The period asked is
     * used and remembered, the remembered one is used when the address asks for none, and one month when it asks for
     * another period. The props are closures, so a partial reload that does not ask for them does not count anything.
     *
     * @param  AgendaRequest  $request  the address
     * @param  User  $user  who looks at the page, whose filters are remembered
     * @return array{agenda: callable(): AgendaData, done: mixed, done_next_page: callable(): ?int}
     */
    public function props(AgendaRequest $request, User $user): array
    {
        $today = CarbonImmutable::today('Europe/Rome');
        $filters = AgendaFiltersData::fromUser($user);
        $asked = $request->askedMonths();

        if ($asked !== null && $asked !== $filters->months) {
            $filters = new AgendaFiltersData($asked, $filters->person_id, $filters->hidden_areas);

            // A visit of the owner shows the period asked but writes nothing in the account it looks as
            if (! LimitVisit::isOpen($request)) {
                $this->save->handle($user, $filters);
            }
        }

        $months = $request->months($filters->months);
        // The page shows the period in use, which is not the remembered one when the address asked for a wrong period.
        $shown = new AgendaFiltersData($months, $filters->person_id, $filters->hidden_areas);
        $until = $this->agenda->period($today, $months);
        $view = $request->view();
        /** @var array{entries: list<AgendaEntryData>, next: int|null}|null $donePage */
        $donePage = null;
        $done = function () use (&$donePage, $view, $today, $request, $shown): ?array {
            if ($view === 'done') {
                $donePage ??= $this->tasks->done($today, $request->donePage(), $shown->person_id);
            }

            return $donePage;
        };

        return [
            'agenda' => function () use ($today, $until, $months, $user, $shown, $view): AgendaData {
                $entries = $this->entries($today, $until);

                return new AgendaData(
                    entries: $entries,
                    months: $months,
                    from: $today->toDateString(),
                    to: $until->toDateString(),
                    people: PersonData::ofFamily($user),
                    filters: $shown,
                    areas: AgendaData::areas($this->familyAreas),
                    overdue: count(array_filter($entries, static fn (AgendaEntryData $entry): bool => $entry->date < $today->toDateString())),
                    view: $view,
                    family_hidden: $this->familyAreas->hiddenValues(),
                );
            },
            // Own props of the page, because only a top-level prop can merge: the pages of the done rows add up
            'done' => Inertia::merge(fn (): array => $done()['entries'] ?? []),
            'done_next_page' => fn (): ?int => $done()['next'] ?? null,
        ];
    }

    /**
     * The things to do from the late ones to the end of the period, in order of day and title. Incomes are not things
     * to do, and a deadline whose automatic reminder is done belongs to the done view; late deadlines and unpaid
     * payments stay for 90 days, tasks without limit. The Home and the calendar keep the done deadlines, so the filter
     * stays here and not in `Agenda`.
     *
     * @param  CarbonImmutable  $today  today, in Rome
     * @param  CarbonImmutable  $until  the last day of the period
     * @return list<AgendaEntryData>
     */
    private function entries(CarbonImmutable $today, CarbonImmutable $until): array
    {
        $entries = [
            ...array_filter($this->agenda->entries($today, $until, 90, 90), static fn (AgendaEntryData $entry): bool => $entry->kind !== 'income'
                && ! ($entry->kind === 'deadline' && $entry->reminder?->state === AgendaReminderState::Done)),
            ...$this->tasks->open($today, $until),
        ];
        usort($entries, static fn (AgendaEntryData $a, AgendaEntryData $b): int => [$a->date, mb_strtolower($a->title)] <=> [$b->date, mb_strtolower($b->title)]);

        return $entries;
    }
}
