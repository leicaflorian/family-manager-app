<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Families\CompleteWelcome;
use App\Data\OptionData;
use App\Data\PersonSummaryData;
use App\Enums\PersonRole;
use App\Http\Requests\Family\WelcomeRequest;
use App\Models\Person;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The *Welcome* after signing up: who you are and the home. Once finished, it does not come back.
 */
final class WelcomeController extends Controller
{
    /**
     * The two steps, with the people already there (whoever signed up).
     */
    public function show(#[CurrentUser] User $user): Response|RedirectResponse
    {
        if ($user->family->isWelcomed()) {
            return to_route('dashboard');
        }

        return Inertia::render('welcome/Welcome', [
            'people' => Person::query()->orderBy('id')->get()->map(PersonSummaryData::fromPerson(...))->values()->all(),
            'roles' => OptionData::list(PersonRole::options()),
        ]);
    }

    /**
     * *Done, let's go in*: saves people and home and goes to the Home.
     */
    public function store(WelcomeRequest $request, #[CurrentUser] User $user, CompleteWelcome $completeWelcome): RedirectResponse
    {
        if (! $user->family->isWelcomed()) {
            /** @var list<array{first_name: string, last_name: string, role: string}> $people */
            $people = $request->validated('people');
            /** @var array{name: string, address?: string|null, city?: string|null, is_owned?: bool}|null $home */
            $home = $request->validated('home');

            $completeWelcome->handle($user->family, $people, $home);
        }

        return to_route('dashboard');
    }
}
