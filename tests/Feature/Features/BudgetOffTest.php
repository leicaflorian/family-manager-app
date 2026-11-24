<?php

declare(strict_types=1);

use App\Contracts\BankFeed;
use App\Enums\BankConnectionStatus;
use App\Enums\BankMatchSource;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\SyncBank;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Family;
use App\Models\PlannedExpense;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Services\Banks\FakeBankFeed;
use App\Services\DocumentLinks;
use App\Support\PresetLink;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

/**
 * Turns the Budget off or on for the family of an account.
 *
 * @param  User  $user  the account
 * @param  bool  $on  whether the Budget is on
 */
function budgetOffSwitch(User $user, bool $on): void
{
    $user->family->forceFill(['budget_enabled' => $on])->save();
}

beforeEach(function (): void {
    $this->admin = registerFamily();
});

test('every route of the Budget, of the banks and of the expense categories sits behind the Budget feature', function (): void {
    $names = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => preg_match('/^(budget(\.|$)|banks\.|expense-categories\.|cards\.payments$)/', (string) $route->getName()) === 1);

    expect($names->count())->toBeGreaterThan(30);

    foreach ($names as $route) {
        expect(in_array('feature:budget', $route->gatherMiddleware(), true))->toBeTrue("{$route->getName()} is not behind feature:budget");
    }
});

test('the pages of the Budget answer 404 with the Budget off and work with it on', function (string $url): void {
    $this->actingAs($this->admin)->get($url)->assertStatus(in_array($url, ['/budget'], true) ? 302 : 200);

    budgetOffSwitch($this->admin, false);

    $this->actingAs($this->admin->fresh())->get($url)->assertNotFound();
})->with([
    'the Budget' => '/budget',
    'movements' => '/budget/movements',
    'new movement' => '/budget/movements/create',
    'planned' => '/budget/planned',
    'comparison' => '/budget/comparison',
    'expense categories' => '/settings/expense-categories',
]);

test('the search has no movements nor dues with the Budget off, and asking for them is refused', function (): void {
    $this->actingAs($this->admin)->get('/search?q=luce&type=movements')->assertOk();

    budgetOffSwitch($this->admin, false);
    $admin = $this->admin->fresh();

    $this->actingAs($admin)->get('/search?q=luce&type=movements')->assertSessionHasErrors('type');
    $this->actingAs($admin)->get('/search?q=luce&type=dues')->assertSessionHasErrors('type');
    $this->actingAs($admin)->get('/search?q=luce')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('results.groups', fn ($groups): bool => collect($groups)->pluck('group')->intersect(['movements', 'dues'])->isEmpty()));
});

test('the settings do not count the expense categories nor the banks with the Budget off', function (): void {
    budgetOffSwitch($this->admin, false);

    $this->actingAs($this->admin->fresh())->get('/settings')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('mainExpenseCategories', 0)->where('bankCount', 0));
});

test('banks:sync skips a family with the bank on and the Budget off', function (): void {
    Queue::fake();
    $family = Family::factory()->create(['bank_feed_enabled' => true, 'budget_enabled' => false]);
    BankConnection::factory()->create(['family_id' => $family->id, 'status' => BankConnectionStatus::Active]);

    $this->artisan('banks:sync')->assertSuccessful();

    Queue::assertNothingPushed();
    expect($family->hasBankFeedOn())->toBeFalse();
});

test('a read of a bank queued before the Budget was turned off does nothing', function (): void {
    $this->app->instance(BankFeed::class, new FakeBankFeed);
    $family = Family::factory()->create(['bank_feed_enabled' => true, 'budget_enabled' => false]);
    $connection = BankConnection::factory()->create(['family_id' => $family->id, 'status' => BankConnectionStatus::Active]);

    dispatch_sync(new SyncBank($connection->id, $family->id));

    expect(BankTransaction::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('the payment of the Agenda answers 404 with the Budget off', function (): void {
    $planned = insideFamilyOf($this->admin, fn (): PlannedExpense => PlannedExpense::factory()->create([
        'family_id' => $this->admin->family_id,
        'is_recurring' => false,
        'start_date' => '2026-11-30',
    ]));
    $partial = fn () => $this->actingAs($this->admin->fresh())->getJson("/agenda?payment=payment:{$planned->id}:2026-11-30", [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(Request::create('/')),
        'X-Inertia-Partial-Component' => 'Agenda',
        'X-Inertia-Partial-Data' => 'payment',
    ]);

    $partial()->assertOk();

    budgetOffSwitch($this->admin, false);

    $partial()->assertNotFound();
});

test('an upload does not come from an expense with the Budget off', function (): void {
    $expense = insideFamilyOf($this->admin, fn (): Expense => Expense::factory()->create(['family_id' => $this->admin->family_id]));

    expect(insideFamilyOf($this->admin, fn (): ?array => PresetLink::findUpload('expense', $expense->id)))->toBe(['type' => 'expense', 'id' => $expense->id]);

    budgetOffSwitch($this->admin, false);

    expect(insideFamilyOf($this->admin->fresh(), fn (): ?array => PresetLink::findUpload('expense', $expense->id)))->toBeNull();
});

test('the menu of the links of a document has no expenses nor income with the Budget off', function (): void {
    insideFamilyOf($this->admin, fn (): Expense => Expense::factory()->create(['family_id' => $this->admin->family_id]));
    $groups = fn (): array => collect(insideFamilyOf($this->admin->fresh(), fn (): array => resolve(DocumentLinks::class)->groups()))->pluck('type')->all();

    expect($groups())->toContain('expense');

    budgetOffSwitch($this->admin, false);

    expect($groups())->not->toContain('expense')->not->toContain('income');
});

test('the bell has no notices of the bank with the Budget off', function (): void {
    $notice = insideFamilyOf($this->admin, function (): ReminderNotice {
        $expense = Expense::factory()->create(['family_id' => $this->admin->family_id]);
        $transaction = BankTransaction::factory()->create(['family_id' => $this->admin->family_id, 'expense_id' => $expense->id, 'matched_by' => BankMatchSource::Rule]);

        return ReminderNotice::factory()->create([
            'family_id' => $this->admin->family_id,
            'user_id' => $this->admin->id,
            'reminder_id' => null,
            'due_date' => null,
            'offset' => null,
            'bank_transaction_id' => $transaction->id,
            'lines' => ['72,40 €'],
            'sent_at' => CarbonImmutable::now(),
        ]);
    });
    $count = fn (): int => $this->actingAs($this->admin->fresh())->get('/notices')->viewData('page')['props']['unread_notices'];

    expect($count())->toBe(1);

    budgetOffSwitch($this->admin, false);
    $this->actingAs($this->admin->fresh())->get('/notices')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('unread', [])->where('seen', [])->where('hasNotices', false)->where('unread_notices', 0));
    expect($notice->exists)->toBeTrue();
});
