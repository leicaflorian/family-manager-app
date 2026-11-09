<?php

declare(strict_types=1);

use App\Actions\Admin\SuspendFamily;
use App\Enums\OwnerActionType;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\UsersRelationManager;
use App\Http\Middleware\LimitVisit;
use App\Models\Family;
use App\Models\Owner;
use App\Models\OwnerAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
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
 * Signs the owner into the panel and starts a visit as Laura.
 */
function startVisit(): void
{
    actingAs(test()->owner, 'owner');
    test()->get(route('support.visit.start', test()->laura))->assertRedirect(route('dashboard'));

    // `actingAs(…, 'owner')` and `auth:owner` make `owner` the default guard of the application, which a test keeps
    // for the next requests; a real request starts again from `web`
    Auth::shouldUse('web');
}

test('enter as is offered only with the permission', function (): void {
    actingAs($this->owner, 'owner');

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->family, 'pageClass' => ViewFamily::class])
        ->assertTableActionVisible('enterAs', $this->laura);

    $this->family->forceFill(['support_until' => now()->subMinute()])->save();
    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->family->refresh(), 'pageClass' => ViewFamily::class])
        ->assertTableActionHidden('enterAs', $this->laura);
    $this->get(route('support.visit.start', $this->laura))->assertForbidden();

    $this->family->forceFill(['support_until' => now()->addHour(), 'suspended_at' => now()])->save();
    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->family->refresh(), 'pageClass' => ViewFamily::class])
        ->assertTableActionHidden('enterAs', $this->laura);
    $this->get(route('support.visit.start', $this->laura))->assertForbidden();

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

    Auth::guard('owner')->logout();

    $this->get(route('dashboard'))->assertRedirect('/admin');

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(session()->has('visit'))->toBeFalse()
        // The owner is no longer in the guard: the log takes it from the visit
        ->and(OwnerAction::query()->latest('id')->first())
        ->action->toBe(OwnerActionType::VisitEnded)
        ->owner_id->toBe($this->owner->id);

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('a family user cannot start a visit', function (): void {
    actingAs($this->laura)->get(route('support.visit.start', $this->laura))
        ->assertRedirect(route('filament.admin.auth.login'));

    expect(OwnerAction::query()->count())->toBe(0);
});
