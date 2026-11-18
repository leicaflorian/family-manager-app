<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Expense;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\PlannedKind;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function (): void {
    Storage::fake('local');
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);
});

/** The address of a due date of a planned expense of the Bianchi, found by its title. */
function deadlineAddress(string $title, string $day, string $suffix = ''): string
{
    $planned = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', $title)->sole());

    return "/budget/planned/expense/{$planned->id}/occurrences/{$day}{$suffix}";
}

/** The address of the page of a planned expense of the Bianchi, found by its title. */
function plannedAddress(string $title, string $suffix = ''): string
{
    $planned = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', $title)->sole());

    return "/budget/planned/expense/{$planned->id}{$suffix}";
}

test('the address of a due date leads to its planned expense, which opens it', function (): void {
    $this->actingAs($this->marco)
        ->get(deadlineAddress('Assicurazione auto', '2026-11-21'))
        ->assertRedirect(plannedAddress('Assicurazione auto', '?deadline=2026-11-21'));
});

test('it keeps the request to pay and the page the visit came from', function (): void {
    $this->actingAs($this->marco)
        ->from('http://localhost/cards/vehicle/3?tab=1')
        ->get(deadlineAddress('Assicurazione auto', '2026-11-21', '?pay=1'))
        ->assertRedirect(plannedAddress('Assicurazione auto', '?deadline=2026-11-21&pay=1&from='.rawurlencode('/cards/vehicle/3?tab=1')));
});

test('it does not keep a page of another site, or the planned expense itself, as where it came from', function (string $from): void {
    $this->actingAs($this->marco)
        ->from(strtr($from, ['{planned}' => plannedAddress('Assicurazione auto')]))
        ->get(deadlineAddress('Assicurazione auto', '2026-11-21'))
        ->assertRedirect(plannedAddress('Assicurazione auto', '?deadline=2026-11-21'));
})->with([
    'another site' => 'https://example.com/agenda',
    'the planned expense' => 'http://localhost{planned}',
]);

test('a paid due date leads to the movement that paid it', function (): void {
    $expense = insideBianchi(fn (): Expense => Expense::query()->where('occurrence_date', '2026-11-05')->sole());

    $this->actingAs($this->marco)
        ->get(deadlineAddress('Retta danza', '2026-11-05'))
        ->assertRedirect("/budget/movements/expense/{$expense->id}");
});

test('the address of a due date of an income leads to the planned income', function (): void {
    $planned = insideBianchi(fn (): PlannedIncome => PlannedIncome::query()->where('title', 'Stipendio di Marco')->sole());

    $this->actingAs($this->marco)
        ->get("/budget/planned/income/{$planned->id}/occurrences/2026-10-27")
        ->assertRedirect("/budget/planned/income/{$planned->id}?deadline=2026-10-27");
});

test('the planned expense sends the due date to open, the request to pay and where to go back', function (): void {
    $this->actingAs($this->marco)
        ->get(plannedAddress('Assicurazione auto', '?deadline=2026-11-21&pay=1&from=%2Fagenda'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/PlannedItem')
            ->where('opening.date', '2026-11-21')
            ->where('opening.amount', 418)
            ->where('opening.card.label', 'RC auto Panda')
            ->where('pay', true)
            ->where('back', '/agenda'));
});

test('without a due date, or with a day that is not one, the planned expense opens nothing', function (string $suffix): void {
    $this->actingAs($this->marco)
        ->get(plannedAddress('Assicurazione auto', $suffix))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('opening', null)
            ->where('pay', false)
            ->where('back', null));
})->with([
    'nothing' => '',
    'not a due date' => '?deadline=2026-11-22',
    'not a date' => '?deadline=soon',
]);

test('the planned expense goes back only to a page of the app', function (string $from): void {
    $this->actingAs($this->marco)
        ->get(plannedAddress('Assicurazione auto', '?deadline=2026-11-21&from='.rawurlencode($from)))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('back', null));
})->with([
    'another site' => 'https://example.com',
    'no scheme' => '//example.com',
    'a backslash' => '/\\example.com',
    'not a path' => 'agenda',
]);

test('it preselects the default account when the planned expense has none', function (): void {
    insideBianchi(fn (): int => PlannedExpense::query()->where('title', 'Assicurazione auto')->update(['financial_account_id' => null]));

    $this->actingAs($this->marco)
        ->get(plannedAddress('Assicurazione auto', '?deadline=2026-11-21'))
        ->assertInertia(function (AssertableInertia $page): void {
            $default = insideBianchi(fn (): FinancialAccount => FinancialAccount::query()->where('is_default', true)->firstOrFail());

            $page->where('opening.account_id', $default->id)->where('opening.account', $default->name);
        });
});

test('it records the payment and goes back to the planned expense', function (): void {
    $address = deadlineAddress('Assicurazione auto', '2026-11-21');

    $this->actingAs($this->marco)
        ->withHeader('Accept-Language', 'it')
        ->post("{$address}/payment", ['date' => '2026-10-26', 'amount' => 418, 'financial_account_id' => null])
        ->assertRedirect(plannedAddress('Assicurazione auto'))
        ->assertInertiaFlash('toast.type', 'success');

    $expense = insideBianchi(fn (): Expense => Expense::query()->where('occurrence_date', '2026-11-21')->sole());
    expect($expense->title)->toBe('Assicurazione auto novembre 2026');
});

test('it goes back to the page the due date was opened from, only a page of the app', function (string $how): void {
    $address = deadlineAddress('Retta danza', '2027-01-05');
    $this->actingAs($this->marco);

    $response = $how === 'payment'
        ? $this->post("{$address}/payment?back=%2Fcards%2Fvehicle%2F3", ['date' => '2026-10-26', 'amount' => 45])
        : $this->put("{$address}/exceptions?back=%2Fcards%2Fvehicle%2F3", ['skip' => false, 'change_amount' => true, 'amount' => 50]);
    $response->assertRedirect('/cards/vehicle/3');

    $this->put("{$address}/exceptions?back=%2F%2Fexample.com", ['skip' => false, 'change_amount' => true, 'amount' => 55])
        ->assertRedirect(plannedAddress('Retta danza'));
})->with(['payment', 'exceptions']);

test('it goes back to the agenda after paying from it', function (): void {
    $address = deadlineAddress('Assicurazione auto', '2026-11-21');

    $this->actingAs($this->marco)
        ->from(route('agenda'))
        ->post("{$address}/payment", ['date' => '2026-10-26', 'amount' => 418, 'from_agenda' => true])
        ->assertRedirect(route('agenda'))
        ->assertInertiaFlash('toast.type', 'success');

    $this->post("{$address}/payment", ['date' => '2026-10-26', 'amount' => 418, 'from_agenda' => 'nope'])->assertSessionHasErrors('from_agenda');
});

test('it refuses to pay twice and keeps one expense', function (): void {
    $address = deadlineAddress('Retta danza', '2026-11-05');

    $this->actingAs($this->marco)
        ->post("{$address}/payment", ['date' => '2026-10-26', 'amount' => 45])
        ->assertSessionHasErrors('date');

    expect(insideBianchi(fn (): int => Expense::query()->where('occurrence_date', '2026-11-05')->count()))->toBe(1);
});

test('it changes a due date and goes back to the planned expense', function (): void {
    $address = deadlineAddress('Retta danza', '2027-01-05');

    $this->actingAs($this->marco)
        ->put("{$address}/exceptions", ['skip' => false, 'change_amount' => true, 'amount' => 70, 'change_date' => false, 'notes' => 'Saggio'])
        ->assertRedirect(plannedAddress('Retta danza'));

    $this->get(plannedAddress('Retta danza', '?deadline=2027-01-05'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('opening.amount', 70)
        ->where('opening.base_amount', 45)
        ->where('opening.notes', 'Saggio'));
});

test('it answers 404 to an address that is not a due date', function (string $path, string $how): void {
    $rossi = registerFamily('Rossi', 'rossi@example.com');
    $other = plannedOfFamily($rossi);
    $own = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', 'Retta danza')->sole());
    $path = strtr($path, ['{own}' => (string) $own->id, '{other}' => (string) $other->id]);

    $this->actingAs($this->marco);

    match ($how) {
        'get' => $this->get($path)->assertNotFound(),
        'post' => $this->post("{$path}/payment", ['date' => '2026-10-26', 'amount' => 45])->assertNotFound(),
        'put' => $this->put("{$path}/exceptions", ['skip' => true])->assertNotFound(),
        'post invalid' => $this->post("{$path}/payment", ['date' => 'not a date', 'amount' => 'much'])->assertNotFound(),
        'put invalid' => $this->put("{$path}/exceptions", ['change_amount' => true, 'amount' => 'much', 'notes' => str_repeat('x', 600)])->assertNotFound(),
    };
})->with(function (): array {
    $paths = [
        'a planned expense of another family' => '/budget/planned/expense/{other}/occurrences/2026-12-05',
        'a day that is not a due date' => '/budget/planned/expense/{own}/occurrences/2026-12-06',
        'an impossible date' => '/budget/planned/expense/{own}/occurrences/2026-13-45',
        'an unknown kind' => '/budget/planned/gift/{own}/occurrences/2026-12-05',
        'an id that does not exist' => '/budget/planned/expense/99999/occurrences/2026-12-05',
    ];
    $sets = [];

    foreach ($paths as $name => $path) {
        foreach (['get', 'post', 'put', 'post invalid', 'put invalid'] as $how) {
            $sets["{$name} on {$how}"] = [$path, $how];
        }
    }

    return $sets;
});

test('a member pays but cannot change a due date', function (): void {
    $address = deadlineAddress('Assicurazione auto', '2026-11-21');

    $this->actingAs($this->rosa);

    $this->put("{$address}/exceptions", ['skip' => true])->assertForbidden();
    $this->post("{$address}/payment", ['date' => '2026-10-26', 'amount' => 418])->assertRedirect(plannedAddress('Assicurazione auto'));
});

test('the planned kind finds only the planned items of the family', function (): void {
    $own = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', 'Retta danza')->sole());
    $this->actingAs($this->marco);

    $found = insideBianchi(fn (): PlannedExpense|PlannedIncome => PlannedKind::find('expense', (string) $own->id));

    expect($found->is($own))->toBeTrue()
        ->and(fn (): mixed => insideBianchi(fn (): PlannedExpense|PlannedIncome => PlannedKind::find('gift', '1')))->toThrow(NotFoundHttpException::class)
        ->and(fn (): mixed => insideBianchi(fn (): PlannedExpense|PlannedIncome => PlannedKind::find('income', '99999')))->toThrow(ModelNotFoundException::class);
});

/** Makes a planned expense in the family of a user and returns it. */
function plannedOfFamily(User $user): PlannedExpense
{
    return CurrentFamily::using($user->family_id, fn (): PlannedExpense => PlannedExpense::factory()->create(['family_id' => $user->family_id, 'start_date' => '2026-12-05']));
}

test('a skipped due date keeps its address and opens on the planned expense, saying it is not paid', function (): void {
    $this->actingAs($this->marco)
        ->get(deadlineAddress('Retta danza', '2026-07-05'))
        ->assertRedirect(plannedAddress('Retta danza', '?deadline=2026-07-05'));

    $this->get(plannedAddress('Retta danza', '?deadline=2026-07-05'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('opening.skipped', true)
            ->where('opening.late', false)
            ->where('opening.payment', null)
            ->where('opening.original_date', '2026-07-05')
            ->where('opening.notes', 'Vacanze'));
});

test('saving a skipped due date with no choice brings it back', function (): void {
    $address = deadlineAddress('Retta danza', '2026-07-05');

    $this->actingAs($this->marco)
        ->put("{$address}/exceptions", ['skip' => false, 'change_amount' => false, 'change_date' => false])
        ->assertRedirect(plannedAddress('Retta danza'));

    $this->get(plannedAddress('Retta danza', '?deadline=2026-07-05'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('opening.skipped', false)
        ->where('opening.late', true));
});

test('a skipped due date cannot be paid', function (): void {
    $address = deadlineAddress('Retta danza', '2026-07-05');

    $this->actingAs($this->marco)
        ->post("{$address}/payment", ['date' => '2026-10-26', 'amount' => 45])
        ->assertNotFound();

    expect(insideBianchi(fn (): int => Expense::query()->where('occurrence_date', '2026-07-05')->count()))->toBe(0);
});

test('it refuses an amount too big to be written', function (string $suffix, array $body, string $field): void {
    $address = deadlineAddress('Assicurazione auto', '2026-11-21');

    $this->actingAs($this->marco);
    $response = $suffix === '/payment' ? $this->post("{$address}{$suffix}", $body) : $this->put("{$address}{$suffix}", $body);

    $response->assertSessionHasErrors($field);
    expect(insideBianchi(fn (): int => Expense::query()->where('occurrence_date', '2026-11-21')->count()))->toBe(0);
})->with([
    'I paid it' => ['/payment', ['date' => '2026-10-26', 'amount' => 100000000], 'amount'],
    'Change this due date' => ['/exceptions', ['change_amount' => true, 'amount' => 100000000], 'amount'],
]);

test('I paid it sends the commission of the payment', function (): void {
    $this->actingAs($this->marco)
        ->post(deadlineAddress('Assicurazione auto', '2026-11-21').'/payment', ['date' => '2026-10-26', 'amount' => 419, 'fee_amount' => 1])
        ->assertSessionHasNoErrors();

    $expense = insideBianchi(fn (): Expense => Expense::query()->where('occurrence_date', '2026-11-21')->sole());

    expect((float) $expense->amount)->toBe(419.0)
        ->and((float) $expense->fee_amount)->toBe(1.0);
});

test('I paid it refuses a negative commission', function (): void {
    $this->actingAs($this->marco)
        ->post(deadlineAddress('Assicurazione auto', '2026-11-21').'/payment', ['date' => '2026-10-26', 'amount' => 419, 'fee_amount' => -1])
        ->assertSessionHasErrors('fee_amount');
});
