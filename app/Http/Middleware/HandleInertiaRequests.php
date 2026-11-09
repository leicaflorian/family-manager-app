<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\FamilyData;
use App\Enums\Theme;
use App\Models\File;
use App\Models\ReminderNotice;
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
        if ($request->hasSession() && $request->session()->has('linked_device_id')) {
            return $this->linkedPhoneShare($request);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // The scan offers *Sign in with the password* only to a phone linked with a code
            'linked_phone' => false,
            'family' => fn (): ?FamilyData => $this->family($request),
            'areas' => fn (): array => $request->user() instanceof User ? $this->summaries->counts() : [],
            'unread_notices' => fn (): int => $request->user() instanceof User ? ReminderNotice::query()->where('user_id', $request->user()->id)->unread()->count() : 0,
            // The number on *Upload*: the files waiting for their document, the copies left out
            'to_sort_count' => fn (): int => $request->user() instanceof User ? File::query()->toSort()->notCopies()->count() : 0,
            'locale' => app()->getLocale(),
            // The theme of the account (`system` for a guest): the layout puts it on <html>
            'theme' => $request->user()?->theme->value ?? Theme::System->value,
        ];
    }

    /**
     * What a phone linked with a code is given: the scan needs only the name of the account, the name of
     * the family, the language, the theme and the messages. No areas, counts or notices.
     *
     * @param  Request  $request  the request
     * @return array<string, mixed>
     */
    private function linkedPhoneShare(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user instanceof User ? ['name' => $user->name] : null,
            ],
            'linked_phone' => true,
            'family' => fn (): ?FamilyData => $this->family($request),
            'locale' => app()->getLocale(),
            'theme' => $user?->theme->value ?? Theme::System->value,
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
