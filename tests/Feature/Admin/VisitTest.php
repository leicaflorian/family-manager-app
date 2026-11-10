<?php

declare(strict_types=1);

use App\Actions\Admin\SuspendFamily;
use App\Actions\Calendar\TurnOnCalendar;
use App\Enums\OwnerActionType;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\UsersRelationManager;
use App\Http\Middleware\LimitVisit;
use App\Models\Family;
use App\Models\Invitation;
use App\Models\Owner;
use App\Models\OwnerAction;
use App\Models\User;
use App\Services\PhoneLinks;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Date::setTestNow('2026-11-09 10:00:00');
    $this->owner = Owner::factory()->withAuthenticator()->create();
    $this->family = Family::factory()->create(['name' => 'Bianchi', 'support_until' => now()->addHours(24)]);
    $this->laura = User::factory()->admin()->create(['family_id' => $this->family->id, 'name' => 'Laura Bianchi', 'locale' => 'en']);
});

/**
 * The address of *Enter as* for an account, signed as the button of the panel writes it.
 */
function visitUrl(User $user): string
{
    return visitUrlAt($user, now()->addMinutes(LimitVisit::LINK_MINUTES));
}

/**
 * The address of *Enter as* for an account, signed until a moment.
 */
function visitUrlAt(User $user, DateTimeInterface $until): string
{
    return URL::temporarySignedRoute('support.visit.start', $until, $user);
}

/**
 * Signs the owner into the panel and starts a visit as Laura.
 */
function startVisit(): void
{
    actingAs(test()->owner, 'owner');
    test()->get(visitUrl(test()->laura))->assertRedirect(route('dashboard'));

    // `actingAs(…, 'owner')` and `auth:owner` make `owner` the default guard of the application, which a test keeps
    // for the next requests; a real request starts again from `web`
    Auth::shouldUse('web');
}

test('enter as is offered only with the permission', function (): void {
    actingAs($this->owner, 'owner');

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->family, 'pageClass' => ViewFamily::class])
        ->assertTableActionVisible('enterAs', $this->laura)
        ->assertTableActionHasUrl('enterAs', visitUrl($this->laura), $this->laura);

    $this->family->forceFill(['support_until' => now()->subMinute()])->save();
    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->family->refresh(), 'pageClass' => ViewFamily::class])
        ->assertTableActionHidden('enterAs', $this->laura);
    $this->get(visitUrl($this->laura))->assertForbidden();

    $this->family->forceFill(['support_until' => now()->addHour(), 'suspended_at' => now()])->save();
    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->family->refresh(), 'pageClass' => ViewFamily::class])
        ->assertTableActionHidden('enterAs', $this->laura);
    $this->get(visitUrl($this->laura))->assertForbidden();

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(OwnerAction::query()->count())->toBe(0);
});

test('the owner sees the app as the account', function (): void {
    startVisit();

    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('impersonating', 'Laura Bianchi')
        ->where('auth.user.name', 'Laura Bianchi'));

    expect(session('visit'))->toBe(['owner_id' => $this->owner->id, 'user_id' => $this->laura->id, 'started_at' => now()->getTimestamp()])
        ->and(OwnerAction::query()->sole())
        ->action->toBe(OwnerActionType::VisitStarted)
        ->owner_id->toBe($this->owner->id)
        ->user_id->toBe($this->laura->id)
        ->family_id->toBe($this->family->id);
});

test('there is no impersonating outside a visit', function (): void {
    actingAs($this->laura)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('impersonating', null));
});

test('nothing can be changed during a visit', function (): void {
    startVisit();

    $this->post(route('reminders.store'), ['title' => 'Call the plumber'])->assertForbidden();
    $this->put(route('account.password'), [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertForbidden();

    expect(LimitVisit::ALLOWED)->toBe(['visit.leave'])
        ->and(Auth::guard('web')->id())->toBe($this->laura->id);
});

test('the language of the browser is not written in the account during a visit', function (): void {
    startVisit();

    $this->withHeader('Accept-Language', 'it')->get(route('dashboard'))->assertOk();

    expect($this->laura->fresh()->locale)->toBe('en');
});

test('leave ends the visit and goes back to the panel', function (): void {
    startVisit();

    $this->post(route('visit.leave'))->assertRedirect('/admin');

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(Auth::guard('owner')->id())->toBe($this->owner->id)
        ->and(session()->has('visit'))->toBeFalse()
        ->and(OwnerAction::query()->orderBy('id')->pluck('action')->all())
        ->toBe([OwnerActionType::VisitStarted, OwnerActionType::VisitEnded])
        ->and(OwnerAction::query()->latest('id')->first()?->user_id)->toBe($this->laura->id);
});

test('an inertia visit that ends the visit leaves the app for the panel', function (): void {
    startVisit();

    $this->post(route('visit.leave'), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/admin'));
});

test('the visit ends after 60 minutes', function (): void {
    startVisit();

    Date::setTestNow(now()->addMinutes(LimitVisit::MINUTES - 1));
    $this->get(route('dashboard'))->assertOk();

    Date::setTestNow(now()->addMinutes(1));
    $this->get(route('dashboard'))->assertRedirect('/admin');

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(Auth::guard('owner')->id())->toBe($this->owner->id)
        ->and(session()->has('visit'))->toBeFalse()
        ->and(OwnerAction::query()->latest('id')->first()?->action)->toBe(OwnerActionType::VisitEnded);
});

test('the visit ends when the permission expires', function (): void {
    startVisit();

    $this->family->forceFill(['support_until' => now()->subSecond()])->save();

    $this->get(route('dashboard'))->assertRedirect('/admin');

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(session()->has('visit'))->toBeFalse();
});

test('the visit ends when the family is suspended', function (): void {
    startVisit();

    resolve(SuspendFamily::class)->handle($this->family);

    $this->get(route('dashboard'))->assertRedirect('/admin');

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(Auth::guard('owner')->id())->toBe($this->owner->id)
        ->and(session()->has('visit'))->toBeFalse();
});

test('the visit ends when the owner signs out of the panel', function (): void {
    startVisit();

    // The real sign-out of Filament: it signs the owner out, then throws the whole session away
    $this->post(route('filament.admin.auth.logout'))->assertRedirect();

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(session()->has('visit'))->toBeFalse()
        ->and(OwnerAction::query()->orderBy('id')->pluck('action')->all())
        ->toBe([OwnerActionType::VisitStarted, OwnerActionType::VisitEnded])
        ->and(OwnerAction::query()->latest('id')->first())
        ->owner_id->toBe($this->owner->id)
        ->user_id->toBe($this->laura->id);

    Auth::shouldUse('web');
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('signing the owner out of the panel in any way ends the visit at once', function (): void {
    startVisit();

    Auth::guard('owner')->logout();

    expect(session()->has('visit'))->toBeFalse()
        ->and(Auth::guard('web')->check())->toBeFalse()
        ->and(OwnerAction::query()->latest('id')->first())
        ->action->toBe(OwnerActionType::VisitEnded)
        ->owner_id->toBe($this->owner->id);
});

test('an owner without the authenticator app cannot start a visit', function (): void {
    $owner = Owner::factory()->create();

    actingAs($owner, 'owner')->get(visitUrl($this->laura))->assertForbidden();

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(OwnerAction::query()->count())->toBe(0);
});

test('in the local environment an owner without the authenticator app can start a visit', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    $owner = Owner::factory()->create();

    actingAs($owner, 'owner')->get(visitUrl($this->laura))->assertRedirect(route('dashboard'));
});

test('linking a phone makes no code during a visit', function (): void {
    $code = resolve(PhoneLinks::class)->newCode($this->laura);
    $hash = Cache::get('phone-link-of:'.$this->laura->id);
    startVisit();

    $this->getJson(route('devices.index'))->assertOk()
        ->assertJsonPath('code', null)
        ->assertJsonPath('qr', null)
        ->assertJsonPath('expires_at', null)
        ->assertJsonPath('devices', []);

    // Laura's own code is still the one in force, and still works
    expect(Cache::get('phone-link-of:'.$this->laura->id))->toBe($hash)
        ->and(resolve(PhoneLinks::class)->find($code))->not->toBeNull();
});

test('the link of a pending invitation is hidden during a visit', function (): void {
    $invitation = Invitation::factory()->create(['family_id' => $this->family->id]);
    startVisit();

    $this->get(route('family.invitations.show', $invitation))->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('invitation.link', null)
            ->where('invitation.email', $invitation->email));
});

test('the address of the calendar is hidden during a visit', function (): void {
    resolve(TurnOnCalendar::class)->handle($this->family, 'en');
    startVisit();

    $this->get(route('calendar.edit'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('calendar.on', true)
            ->where('calendar.url', null));
});

test('a family user cannot start a visit', function (): void {
    actingAs($this->laura)->get(visitUrl($this->laura))
        ->assertRedirect(route('filament.admin.auth.login'));

    expect(OwnerAction::query()->count())->toBe(0);
});

test('a link without the signature of the panel does not start a visit', function (): void {
    actingAs($this->owner, 'owner');

    $this->get(route('support.visit.start', $this->laura))->assertForbidden();

    Date::setTestNow(now()->addMinutes(2));
    $this->get(visitUrlAt($this->laura, now()->subMinute()))->assertForbidden();

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(OwnerAction::query()->count())->toBe(0);
});

test('the panel still works in its tab during a visit', function (): void {
    startVisit();

    // The table of the accounts as the panel draws it, sent back to Livewire as the browser does on a click
    $snapshot = Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->family, 'pageClass' => ViewFamily::class])->snapshot;

    $this->postJson(route('default-livewire.update'), [
        'components' => [['snapshot' => json_encode($snapshot), 'updates' => [], 'calls' => []]],
    ], ['X-Livewire' => 'true'])->assertOk();

    // The exemption is the route, not the header: the app still refuses a change sent with it
    $this->post(route('reminders.store'), ['title' => 'Call the plumber'], ['X-Livewire' => 'true'])->assertForbidden();

    expect(session('visit.user_id'))->toBe($this->laura->id);
});

test('the agenda does not remember its period in the account during a visit', function (): void {
    startVisit();

    $this->get(route('agenda', ['months' => 6]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('agenda.months', 6));

    expect($this->laura->fresh()->agenda_filters)->toBeNull();
});
