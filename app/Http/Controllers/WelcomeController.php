<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Families\CompleteWelcome;
use App\Data\FeatureSettingsData;
use App\Data\PersonDetailData;
use App\Enums\PersonRole;
use App\Http\Requests\Family\WelcomeRequest;
use App\Models\Person;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The *Welcome* after signing up: who you are, the home, what you use and how it works. Once finished, it does not
 * come back.
 */
final class WelcomeController extends Controller
{
    /**
     * The four steps, with the person of whoever signed in and the switches of the family.
     *
     * @param  User  $user  whoever signed in
     */
    public function show(#[CurrentUser] User $user): Response|RedirectResponse
    {
        $family = $user->family;

        if ($family->isWelcomed()) {
            return to_route('dashboard');
        }

        $me = Person::query()->where('user_id', $user->id)->first();

        return Inertia::render('welcome/Welcome', [
            'me' => $me === null ? null : PersonDetailData::fromPerson($me, $user),
            'roles' => PersonRole::optionData(),
            'features' => FeatureSettingsData::fromFamily($family),
        ]);
    }

    /**
     * *Done, let's go in*: saves you, the people, the home and the switches, and goes to the Home.
     *
     * @param  WelcomeRequest  $request  the four steps
     * @param  User  $user  whoever signed in
     * @param  CompleteWelcome  $completeWelcome  saves everything
     */
    public function store(WelcomeRequest $request, #[CurrentUser] User $user, CompleteWelcome $completeWelcome): RedirectResponse
    {
        if (! $user->family->isWelcomed()) {
            /** @var list<array{first_name: string, last_name: string, role: string, birth_date?: ?string, tax_code?: ?string}> $people */
            $people = $request->validated('people');
            /** @var array{name: string, address?: string|null, city?: string|null, is_owned?: bool}|null $home */
            $home = $request->validated('home');
            /** @var array{birth_date?: ?string, tax_code?: ?string} $me */
            $me = $request->validated('me') ?? [];
            /** @var array{budget: bool, mail: bool}|null $features */
            $features = $request->validated('features');

            $completeWelcome->handle($user, $me, $people, $home, $features === null ? null : [
                'budget' => (bool) $features['budget'],
                'mail' => (bool) $features['mail'],
            ]);
        }

        return to_route('dashboard');
    }
}
