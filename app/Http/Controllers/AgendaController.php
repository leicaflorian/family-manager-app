<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Agenda\SaveAgendaFilters;
use App\Actions\Agenda\UpdateDeadline;
use App\Data\AgendaData;
use App\Data\AgendaEntryData;
use App\Data\AgendaFiltersData;
use App\Data\DeadlineData;
use App\Data\PersonData;
use App\Http\Middleware\LimitVisit;
use App\Http\Requests\Agenda\UpdateDeadlineRequest;
use App\Http\Requests\AgendaFiltersRequest;
use App\Http\Requests\AgendaRequest;
use App\Models\User;
use App\Services\Agenda;
use App\Services\AgendaTasks;
use App\Services\BudgetFormOptions;
use App\Services\Deadlines;
use App\Services\FamilyAreas;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The *Agenda*: what expires and what is to pay or to arrive from today to the end of a period. Everyone of the
 * family sees it.
 */
final class AgendaController extends Controller
{
    public function __construct(private readonly Agenda $agenda, private readonly AgendaTasks $tasks, private readonly FamilyAreas $familyAreas, private readonly Deadlines $deadlines) {}

    /**
     * The Agenda of `?months=1|3|6|12`: the period asked is used and remembered, the remembered one is used when
     * the address asks for none, and one month when it asks for another period. *I paid it* of a panel asks, with a
     * partial reload, for `payment` (the due date of `?payment=<key of the entry>`) and the `accounts` to pay with.
     *
     * @throws NotFoundHttpException if `payment` is asked for a key that is not a payment of the family
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

        $view = $request->view();
        /** @var array{entries: list<AgendaEntryData>, next: int|null}|null $donePage */
        $donePage = null;
        // The props are closures, so the partial reload of *I paid it* does not count the entries again
        $done = function () use (&$donePage, $view, $today, $request, $shown): ?array {
            if ($view === 'done') {
                $donePage ??= $this->tasks->done($today, $request->donePage(), $shown->person_id);
            }

            return $donePage;
        };

        return Inertia::render('Agenda', [
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
            'payment' => Inertia::optional(fn (): DeadlineData => DeadlineData::fromDeadline($this->payment($request->string('payment')->toString()), $today)),
            'accounts' => Inertia::optional(BudgetFormOptions::accounts(...)),
        ]);
    }

    /**
     * The things to do from the late ones to the end of the period, in order of day and title. Incomes are not things
     * to do; late deadlines and unpaid payments stay for 90 days, tasks without limit.
     *
     * @param  CarbonImmutable  $today  today, in Rome
     * @param  CarbonImmutable  $until  the last day of the period
     * @return list<AgendaEntryData>
     */
    private function entries(CarbonImmutable $today, CarbonImmutable $until): array
    {
        $entries = [
            ...array_filter($this->agenda->entries($today, $until, 90, 90), static fn (AgendaEntryData $entry): bool => $entry->kind !== 'income'),
            ...$this->tasks->open($today, $until),
        ];
        usort($entries, static fn (AgendaEntryData $a, AgendaEntryData $b): int => [$a->date, mb_strtolower($a->title)] <=> [$b->date, mb_strtolower($b->title)]);

        return $entries;
    }

    /**
     * The due date of a payment entry of the Agenda, from its key `payment:<planned expense id>:<original date>`.
     *
     * @param  string  $key  the key of the entry
     *
     * @throws NotFoundHttpException if the key is not one of a payment, or its due date is not of the family
     */
    private function payment(string $key): Deadline
    {
        if (preg_match('/^payment:(\d+):([0-9-]+)$/', $key, $parts) !== 1) {
            throw new NotFoundHttpException;
        }

        return $this->deadlines->findByAddress('expense', $parts[1], $parts[2]);
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
