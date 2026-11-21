<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ConsumptionRequest;
use App\Models\User;
use App\Services\ConsumptionPage;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *Consumption*, a page of the budget: 12 months of electricity, gas or water of one utility. Everyone of the family
 * sees it.
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
                $request->utility(),
                $request->to(),
                $request->detailMonth(),
                CarbonImmutable::today('Europe/Rome'),
                app()->getLocale(),
            ),
        ]);
    }
}
