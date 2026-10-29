<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\ComparisonRequest;
use App\Services\Comparison;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *Planned and spent*: a month of expenses against the plan, by category. Everyone of the family sees it.
 */
final class ComparisonController extends Controller
{
    public function __construct(private readonly Comparison $comparison) {}

    /**
     * The comparison of `?month=2026-10`, the month in progress when none is asked.
     *
     * @throws NotFoundHttpException if the month of the address is wrong
     */
    public function index(ComparisonRequest $request): Response
    {
        return Inertia::render('budget/Comparison', [
            'comparison' => $this->comparison->of($request->month(), CarbonImmutable::today('Europe/Rome')),
        ]);
    }
}
