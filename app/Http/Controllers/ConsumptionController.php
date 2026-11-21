<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Consumption\QueueConsumptionReadings;
use App\Enums\UtilityType;
use App\Http\Requests\ConsumptionRequest;
use App\Models\User;
use App\Models\Utility;
use App\Services\ConsumptionPage;
use App\Services\Reading\ReadingAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *Consumption*, a page of the budget: 12 months of electricity, gas or water of one utility. Everyone of the family
 * sees it, and anyone of the family can have the bills already in the archive read (*Read the bills*).
 */
final class ConsumptionController extends Controller
{
    public function __construct(private readonly ConsumptionPage $page) {}

    /**
     * The page of `?utility=…&to=YYYY-MM&month=YYYY-MM`.
     *
     * @throws NotFoundHttpException if the utility or the months of the address are wrong
     */
    public function index(ConsumptionRequest $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('budget/Consumption', [
            'consumption' => $this->page->build(
                $user,
                $request->utilities(),
                $request->utility(),
                $request->to(),
                $request->detailMonth(),
                CarbonImmutable::today('Europe/Rome'),
                app()->getLocale(),
            ),
        ]);
    }

    /**
     * *Read the bills*: queues the reading of the bills of the utility still without consumption, then back to the page
     * with how many are being read.
     *
     * @param  Utility  $utility  the utility of the address, already found in the family
     * @param  QueueConsumptionReadings  $queue  queues the readings
     * @param  ReadingAvailability  $availability  whether a reading can start now
     *
     * @throws NotFoundHttpException if the utility has no consumption (internet, phone…)
     * @throws AuthorizationException if the person cannot see the utility
     * @throws ValidationException if the reading is off or the readings of the month are used up
     */
    public function read(Utility $utility, #[CurrentUser] User $user, QueueConsumptionReadings $queue, ReadingAvailability $availability): RedirectResponse
    {
        if (! in_array($utility->type, UtilityType::measured(), true)) {
            throw new NotFoundHttpException;
        }

        Gate::authorize('view', $utility);

        $locale = app()->getLocale();
        $unavailable = $availability->unavailable($locale);

        if ($unavailable !== null) {
            throw ValidationException::withMessages(['read' => $unavailable]);
        }

        $count = $queue->handle($utility, $user, $locale);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} There are no bills to read.|{1} I am reading :count bill.|[2,*] I am reading :count bills.', $count, ['count' => $count])]);

        return back();
    }
}
