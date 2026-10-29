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

test('it shows a due date with its card and the next ones', function (): void {
    $this->actingAs($this->marco)
        ->get(deadlineAddress('Assicurazione auto', '2026-11-21'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/Deadline')
            ->where('deadline.title', 'Assicurazione auto')
            ->where('deadline.amount', 418)
            ->where('deadline.date', '2026-11-21')
            ->where('deadline.card.label', 'RC auto Panda')
            ->where('deadline.payment', null)
            ->has('next', 2)
            ->where('next.0.date', '2027-11-21')
            ->has('accounts', 2)
            ->where('canPay', true)
            ->where('canChange', true)
            ->where('pay', false));
});

test('it shows a paid due date with its payment', function (): void {
    $this->actingAs($this->marco)
        ->get(deadlineAddress('Retta danza', '2026-11-05'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('deadline.payment.date', '2026-10-20')
            ->where('deadline.payment.amount', 45)
            ->where('deadline.payment.account', 'Carta Laura')
            ->has('next', 3)
            ->where('next.0.date', '2026-12-05'));
});

test('it opens with the panel of the payment when asked', function (): void {
    $this->actingAs($this->marco)
        ->get(deadlineAddress('Assicurazione auto', '2026-11-21', '?pay=1'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('pay', true));
});

test('it preselects the default account when the planned expense has none', function (): void {
    insideBianchi(fn (): int => PlannedExpense::query()->where('title', 'Assicurazione auto')->update(['financial_account_id' => null]));

    $this->actingAs($this->marco)
        ->get(deadlineAddress('Assicurazione auto', '2026-11-21'))
        ->assertInertia(function (AssertableInertia $page): void {
            $default = insideBianchi(fn (): FinancialAccount => FinancialAccount::query()->where('is_default', true)->firstOrFail());

            $page->where('deadline.account_id', $default->id)->where('deadline.account', $default->name);
        });
});

test('it opens the due date of an income', function (): void {
    $planned = insideBianchi(fn (): PlannedIncome => PlannedIncome::query()->where('title', 'Stipendio di Marco')->sole());

    $this->actingAs($this->marco)
        ->get("/budget/planned/income/{$planned->id}/occurrences/2026-10-27")
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('deadline.kind', 'income')
            ->where('deadline.amount', 1850));
});

test('it records the payment and goes back to the due date', function (): void {
    $address = deadlineAddress('Assicurazione auto', '2026-11-21');

    $this->actingAs($this->marco)
        ->withHeader('Accept-Language', 'it')
        ->post("{$address}/payment", ['date' => '2026-10-26', 'amount' => 418, 'financial_account_id' => null])
        ->assertRedirect($address)
        ->assertInertiaFlash('toast.type', 'success');

    $expense = insideBianchi(fn (): Expense => Expense::query()->where('occurrence_date', '2026-11-21')->sole());
    expect($expense->title)->toBe('Assicurazione auto novembre 2026');

    $this->get($address)->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('deadline.payment.date', '2026-10-26'));
});

test('it refuses to pay twice and keeps one expense', function (): void {
    $address = deadlineAddress('Retta danza', '2026-11-05');

    $this->actingAs($this->marco)
        ->post("{$address}/payment", ['date' => '2026-10-26', 'amount' => 45])
        ->assertSessionHasErrors('date');

    expect(insideBianchi(fn (): int => Expense::query()->where('occurrence_date', '2026-11-05')->count()))->toBe(1);
});

test('it changes a due date and goes back to it', function (): void {
    $address = deadlineAddress('Retta danza', '2027-01-05');

    $this->actingAs($this->marco)
        ->put("{$address}/exceptions", ['skip' => false, 'change_amount' => true, 'amount' => 70, 'change_date' => false, 'notes' => 'Saggio'])
        ->assertRedirect($address);

    $this->get($address)->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('deadline.amount', 70)
        ->where('deadline.base_amount', 45)
        ->where('deadline.notes', 'Saggio'));
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

    $this->actingAs($this->rosa)
        ->get($address)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('canPay', true)
            ->where('canChange', false));

    $this->put("{$address}/exceptions", ['skip' => true])->assertForbidden();
    $this->post("{$address}/payment", ['date' => '2026-10-26', 'amount' => 418])->assertRedirect($address);
});

test('the planned kind finds only the planned items of the family', function (): void {
    $own = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', 'Retta danza')->sole());
    $this->actingAs($this->marco);

    $found = insideBianchi(fn (): PlannedExpense|PlannedIncome => PlannedKind::find('expense', (string) $own->id));

    expect($found->is($own))->toBeTrue()
        ->and(fn () => insideBianchi(fn (): PlannedExpense|PlannedIncome => PlannedKind::find('gift', '1')))->toThrow(NotFoundHttpException::class)
        ->and(fn () => insideBianchi(fn (): PlannedExpense|PlannedIncome => PlannedKind::find('income', '99999')))->toThrow(ModelNotFoundException::class);
});

/** Makes a planned expense in the family of a user and returns it. */
function plannedOfFamily(User $user): PlannedExpense
{
    return CurrentFamily::using($user->family_id, fn (): PlannedExpense => PlannedExpense::factory()->create(['family_id' => $user->family_id, 'start_date' => '2026-12-05']));
}
