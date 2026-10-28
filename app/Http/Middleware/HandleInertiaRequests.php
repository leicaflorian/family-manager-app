<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\FamilyData;
use App\Models\User;
use App\Services\CardSummaries;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @param  CardSummaries  $summaries  the cards of the family, for the counts of the sidebar
     */
    public function __construct(private readonly CardSummaries $summaries) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'family' => fn (): ?FamilyData => $this->family($request),
            'areas' => fn (): array => $request->user() instanceof User ? $this->summaries->counts() : [],
            'locale' => app()->getLocale(),
        ];
    }

    /**
     * The family of whoever signed in, or null for a guest.
     *
     * @param  Request  $request  the request
     */
    private function family(Request $request): ?FamilyData
    {
        $user = $request->user();

        return $user instanceof User ? FamilyData::fromFamily($user->family) : null;
    }
}
