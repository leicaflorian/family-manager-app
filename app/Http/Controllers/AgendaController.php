<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Agenda\SaveAgendaFilters;
use App\Actions\Agenda\UpdateDeadline;
use App\Data\CalendarSettingsData;
use App\Data\DeadlineData;
use App\Http\Middleware\LimitVisit;
use App\Http\Requests\Agenda\UpdateDeadlineRequest;
use App\Http\Requests\AgendaFiltersRequest;
use App\Http\Requests\AgendaRequest;
use App\Models\User;
use App\Services\AgendaPage;
use App\Services\BudgetFormOptions;
use App\Services\Deadlines;
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
    public function __construct(private readonly Deadlines $deadlines) {}

    /**
     * The Agenda of `?months=1|3|6|12`: the period asked is used and remembered, the remembered one is used when
     * the address asks for none, and one month when it asks for another period. The panel of a payment or income
     * asks, with a partial reload, for `payment` (the due date of `?payment=<key of the entry>`) and the `accounts`
     * to pay with, for the form it holds. The calendar asks for `calendar_link`, the address to subscribe to.
     *
     * @throws NotFoundHttpException if `payment` is asked for a key that is not a payment or income of the family
     */
    public function __invoke(AgendaRequest $request, #[CurrentUser] User $user, AgendaPage $page): Response
    {
        $today = CarbonImmutable::today('Europe/Rome');

        return Inertia::render('Agenda', [
            ...$page->props($request, $user),
            'payment' => Inertia::optional(fn (): DeadlineData => DeadlineData::fromDeadline($this->payment($request->string('payment')->toString()), $today)),
            'accounts' => Inertia::optional(BudgetFormOptions::accounts(...)),
            'calendar_link' => Inertia::optional(fn (): CalendarSettingsData => CalendarSettingsData::fromFamily($user->family, $user, LimitVisit::isOpen($request))),
        ]);
    }

    /**
     * The due date of a payment or income entry of the Agenda, from its key
     * `payment:<planned expense id>:<original date>` or `income:<planned income id>:<original date>`.
     *
     * @param  string  $key  the key of the entry
     *
     * @throws NotFoundHttpException if the key is not one of a payment or income, or its due date is not of the family
     */
    private function payment(string $key): Deadline
    {
        if (preg_match('/^(payment|income):(\d+):([0-9-]+)$/', $key, $parts) !== 1) {
            throw new NotFoundHttpException;
        }

        return $this->deadlines->findByAddress($parts[1] === 'income' ? 'income' : 'expense', $parts[2], $parts[3]);
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
