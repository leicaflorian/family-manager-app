<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HomeSummary;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The *Home*: what to pay in the next 30 days, the deadlines, the incomes on
 * their way, the files waiting and, for a new family, how to begin. The
 * notices of the bell are a shared optional prop (`bell_notices`).
 */
final class HomeController extends Controller
{
    /**
     * The *Home* of whoever signed in, with today in Rome.
     */
    public function __invoke(HomeSummary $summary, #[CurrentUser] User $user): Response
    {
        $today = CarbonImmutable::today('Europe/Rome');

        return Inertia::render('Home', [
            'home' => $summary->for($user, $today),
        ]);
    }
}
