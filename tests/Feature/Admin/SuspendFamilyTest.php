<?php

declare(strict_types=1);

use App\Actions\Admin\ReactivateFamily;
use App\Actions\Admin\ScheduleFamilyDeletion;
use App\Actions\Admin\SuspendFamily;
use App\Enums\CallOutcome;
use App\Enums\OwnerActionType;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Models\Family;
use App\Models\FamilySetting;
use App\Models\File;
use App\Models\LinkedDevice;
use App\Models\MailInboxCall;
use App\Models\Owner;
use App\Models\OwnerAction;
use App\Models\Person;
use App\Models\ReceivedMail;
use App\Models\ReminderGroup;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\MailgunCall;

use function Pest\Laravel\actingAs;

const SUSPENDED_ADDRESS = 'rossi.k7m2x9@bollette.familymanager.test';

beforeEach(function (): void {
    $this->owner = Owner::factory()->withAuthenticator()->create(['email' => 'owner@example.com']);
    $this->family = Family::factory()->create(['name' => 'Rossi']);
    $this->laura = User::factory()->for($this->family)->create(['email' => 'laura@example.com', 'password' => 'secret-password']);
});

test('a suspended family cannot sign in', function (): void {
    resolve(SuspendFamily::class)->handle($this->family);

    $this->post(route('login.store'), ['email' => 'laura@example.com', 'password' => 'secret-password'])
        ->assertSessionHasErrors(['email' => 'Your account is suspended. Write to owner@example.com to know why.']);

    $this->assertGuest();
});

test('the message is in italian for an italian browser', function (): void {
    resolve(SuspendFamily::class)->handle($this->family);

    $this->withHeader('Accept-Language', 'it')
        ->post(route('login.store'), ['email' => 'laura@example.com', 'password' => 'secret-password'])
        ->assertSessionHasErrors(['email' => 'Il tuo account è sospeso. Scrivi a owner@example.com per sapere perché.']);
});

test('without an owner the message uses the address the app writes from', function (): void {
    Owner::query()->delete();
    config(['mail.from.address' => 'hello@example.test']);
    resolve(SuspendFamily::class)->handle($this->family);

    $this->post(route('login.store'), ['email' => 'laura@example.com', 'password' => 'secret-password'])
        ->assertSessionHasErrors(['email' => 'Your account is suspended. Write to hello@example.test to know why.']);
});

test('a wrong password on a suspended family is still the plain wrong credentials', function (): void {
    resolve(SuspendFamily::class)->handle($this->family);

    $this->post(route('login.store'), ['email' => 'laura@example.com', 'password' => 'not-it'])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);
});

test('an account already inside is sent out at the next request', function (): void {
    actingAs($this->laura)->get(route('account.edit'))->assertOk();

    resolve(SuspendFamily::class)->handle($this->family);

    $this->get(route('account.edit'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Your account is suspended. Write to owner@example.com to know why.']);

    $this->assertGuest();
});

test('a linked phone of a suspended family is sent out', function (): void {
    $device = LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['last_used_at' => now()]);
    $phone = fn () => $this->actingAs($this->laura)->withSession(['linked_device_id' => $device->id]);

    $phone()->get(route('scan'))->assertOk();

    resolve(SuspendFamily::class)->handle($this->family);

    $phone()->get(route('scan'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the calendar of a suspended family answers 404, the same as a wrong link', function (): void {
    $secret = 'a-calendar-secret-of-the-rossi';
    $this->family->forceFill(['calendar_token_hash' => hash('sha256', $secret)])->save();

    $this->get("calendar/{$secret}.ics")->assertOk();

    resolve(SuspendFamily::class)->handle($this->family);

    $blocked = $this->get("calendar/{$secret}.ics")->assertNotFound();
    $wrong = $this->get('calendar/not-a-secret-at-all.ics')->assertNotFound();

    expect($blocked->getContent())->toBe($wrong->getContent())
        ->and($blocked->headers->has('Set-Cookie'))->toBeFalse();
});

test('mail for a suspended family is not saved', function (): void {
    insideFamilyOf($this->laura, function (): void {
        FamilySetting::query()->firstOrCreate([])->forceFill(['mail_inbox_recipient' => SUSPENDED_ADDRESS])->save();
    });
    resolve(SuspendFamily::class)->handle($this->family);

    MailgunCall::to(SUSPENDED_ADDRESS)->attach('bill.pdf', "%PDF-1.7\n% bill\n", 'application/pdf')->send()->assertOk();

    $call = MailInboxCall::query()->sole();
    expect($call->outcome)->toBe(CallOutcome::Suspended)
        ->and($call->http_status)->toBe(200)
        ->and(insideFamilyOf($this->laura, fn (): int => ReceivedMail::query()->count() + File::query()->count()))->toBe(0);
});

test('the morning round skips a suspended family', function (): void {
    Mail::fake();
    Date::setTestNow(CarbonImmutable::parse('2026-11-25 10:00', 'Europe/Rome'));
    $other = Family::factory()->create(['name' => 'Verdi']);

    foreach ([$this->family, $other] as $family) {
        CurrentFamily::using($family->id, function () use ($family): void {
            $account = User::factory()->for($family)->admin()->create();
            Person::factory()->for($family)->create(['user_id' => $account->id]);
            ReminderGroup::factory()->for($family)->create([
                'name' => 'Veicoli',
                'deadline_types' => ['vehicles.inspection_due_date'],
                'notifications' => [['name' => '45 giorni', 'days' => 45, 'timing' => 'before', 'channels' => 'both', 'recipients' => ['all']]],
            ]);
            Vehicle::factory()->for($family)->create(['name' => 'Panda', 'inspection_due_date' => '2027-01-09']);
        });
    }

    resolve(SuspendFamily::class)->handle($this->family);

    $this->artisan('reminders:run')->assertSuccessful();
    Date::setTestNow();

    expect(CurrentFamily::using($this->family->id, fn (): int => ReminderNotice::query()->count()))->toBe(0)
        ->and(CurrentFamily::using($other->id, fn (): int => ReminderNotice::query()->count()))->toBeGreaterThan(0);
});

test('reactivate gives everything back', function (): void {
    resolve(SuspendFamily::class)->handle($this->family);
    resolve(ReactivateFamily::class)->handle($this->family->refresh());

    expect($this->family->refresh()->suspended_at)->toBeNull()
        ->and($this->family->isBlocked())->toBeFalse();

    $this->post(route('login.store'), ['email' => 'laura@example.com', 'password' => 'secret-password'])->assertRedirect();
    $this->assertAuthenticatedAs($this->laura);
});

test('each action writes the log', function (): void {
    actingAs($this->owner, 'owner');

    resolve(SuspendFamily::class)->handle($this->family);
    resolve(ReactivateFamily::class)->handle($this->family->refresh());

    $rows = OwnerAction::query()->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->action)->toBe(OwnerActionType::Suspended)
        ->and($rows[0]->owner_id)->toBe($this->owner->id)
        ->and($rows[0]->family_id)->toBe($this->family->id)
        ->and($rows[0]->family_name)->toBe('Rossi')
        ->and($rows[1]->action)->toBe(OwnerActionType::Reactivated);
});

test('the page of the family suspends and reactivates with a confirmation, and shows the log', function (): void {
    actingAs($this->owner, 'owner');

    Livewire::test(ViewFamily::class, ['record' => $this->family->getRouteKey()])
        ->callAction('suspend')
        ->assertHasNoActionErrors();

    expect($this->family->refresh()->suspended_at)->not->toBeNull();

    Livewire::test(ViewFamily::class, ['record' => $this->family->getRouteKey()])
        ->assertActionHidden('suspend')
        ->callAction('reactivate');

    expect($this->family->refresh()->suspended_at)->toBeNull()
        ->and(OwnerAction::query()->count())->toBe(2);
});

test('changing the reading limit writes the log', function (): void {
    actingAs($this->owner, 'owner');

    Livewire::test(ViewFamily::class, ['record' => $this->family->getRouteKey()])
        ->callAction('editReadingLimit', ['reading_limit' => 50]);

    expect(OwnerAction::query()->sole()->action)->toBe(OwnerActionType::ReadingLimitChanged);
});

test('an unchanged reading limit writes no log line', function (): void {
    $this->family->forceFill(['reading_limit' => 50])->save();
    actingAs($this->owner, 'owner');

    Livewire::test(ViewFamily::class, ['record' => $this->family->getRouteKey()])
        ->callAction('editReadingLimit', ['reading_limit' => 50]);

    expect(OwnerAction::query()->count())->toBe(0);
});

test('cancelling a deletion writes its log line', function (): void {
    actingAs($this->owner, 'owner');
    resolve(ScheduleFamilyDeletion::class)->handle($this->family);

    Livewire::test(ViewFamily::class, ['record' => $this->family->getRouteKey()])->callAction('cancelDeletion');

    expect(OwnerAction::query()->orderByDesc('id')->first()?->action)->toBe(OwnerActionType::DeletionCancelled);
});

test('a PUT of a suspended account goes to the sign-in with a 303', function (): void {
    actingAs($this->laura);
    resolve(SuspendFamily::class)->handle($this->family);

    $this->put(route('agenda.filters'))->assertStatus(303)->assertRedirect(route('login'));
    $this->assertGuest();
});

test('an old password hash is renewed at sign-in', function (): void {
    DB::table('users')->where('id', $this->laura->id)->update(['password' => Hash::make('secret-password', ['rounds' => 4])]);

    $this->post(route('login.store'), ['email' => 'laura@example.com', 'password' => 'secret-password']);

    expect(Hash::needsRehash($this->laura->refresh()->password))->toBeFalse();
});
