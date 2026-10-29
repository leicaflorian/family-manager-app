<?php

declare(strict_types=1);

use App\Actions\Budget\ChangeOccurrence;
use App\Enums\ExceptionType;
use App\Enums\Role;
use App\Http\Requests\Budget\ChangeOccurrenceRequest;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\User;
use App\Services\Deadlines;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->seed(BianchiFamilySeeder::class);
});

/** Applies a change to one due date of a planned item found by its title. */
function changeDue(PlannedExpense|PlannedIncome $planned, string $day, bool $skip, ?float $amount, ?string $date, ?string $notes = null): void
{
    resolve(ChangeOccurrence::class)->handle(
        $planned,
        CarbonImmutable::parse($day),
        $skip,
        $amount,
        $date === null ? null : CarbonImmutable::parse($date),
        $notes,
    );
}

test('it changes the amount of one due date only', function (): void {
    insideBianchi(function (): void {
        $retta = PlannedExpense::query()->where('title', 'Retta danza')->sole();

        changeDue($retta, '2026-12-05', false, 60.0, null, 'Comprende la quota del saggio di Natale');

        $deadlines = resolve(Deadlines::class);
        $december = $deadlines->find($retta->fresh(), CarbonImmutable::parse('2026-12-05'));
        $november = $deadlines->find($retta->fresh(), CarbonImmutable::parse('2026-11-05'));

        expect($december->occurrence->amount)->toBe(60.0)
            ->and($november->occurrence->amount)->toBe(45.0)
            ->and($retta->exceptions()->where('type', ExceptionType::AmountOverride)->where('occurrence_date', '2026-12-05')->sole()->notes)
            ->toBe('Comprende la quota del saggio di Natale');
    });
});

test('it moves a due date and keeps its original date as the key', function (): void {
    insideBianchi(function (): void {
        $retta = PlannedExpense::query()->where('title', 'Retta danza')->sole();

        changeDue($retta, '2026-12-05', false, 60.0, '2026-12-10', 'Nota');

        $rows = $retta->exceptions()->where('occurrence_date', '2026-12-05')->get();
        $due = resolve(Deadlines::class)->find($retta->fresh(), CarbonImmutable::parse('2026-12-05'));

        expect($rows)->toHaveCount(2)
            ->and($rows->pluck('notes')->unique()->all())->toBe(['Nota'])
            ->and($due->occurrence->date->toDateString())->toBe('2026-12-10')
            ->and($due->occurrence->originalDate->toDateString())->toBe('2026-12-05');
    });
});

test('it skips a due date and ignores the other choices', function (): void {
    insideBianchi(function (): void {
        $retta = PlannedExpense::query()->where('title', 'Retta danza')->sole();

        changeDue($retta, '2026-12-05', true, 60.0, '2026-12-10');

        $rows = $retta->exceptions()->where('occurrence_date', '2026-12-05')->get();

        expect($rows)->toHaveCount(1)
            ->and($rows->first()?->type)->toBe(ExceptionType::Skip)
            ->and($rows->first()?->amount_override)->toBeNull();
    });
});

test('it removes the exceptions when nothing is chosen', function (): void {
    insideBianchi(function (): void {
        $retta = PlannedExpense::query()->where('title', 'Retta danza')->sole();
        $before = $retta->exceptions()->count();

        changeDue($retta, '2026-12-05', false, 60.0, null);
        changeDue($retta, '2026-12-05', false, null, null, 'ignored');

        expect($retta->exceptions()->where('occurrence_date', '2026-12-05')->count())->toBe(0)
            ->and($retta->exceptions()->count())->toBe($before - 1)
            ->and($retta->exceptions()->where('occurrence_date', '2026-07-05')->count())->toBe(1);
    });
});

test('it works the same for a planned income', function (): void {
    insideBianchi(function (): void {
        $income = PlannedIncome::query()->firstOrFail();
        $due = resolve(Deadlines::class)->next([$income], CarbonImmutable::parse('2026-10-26'));
        $original = $due?->occurrence->originalDate->toDateString() ?? '';

        changeDue($income, $original, false, 3000.0, '2026-11-30', 'Bonus');

        $rows = $income->exceptions()->where('occurrence_date', $original)->get();

        expect($rows)->toHaveCount(2)
            ->and($rows->pluck('type')->all())->toContain(ExceptionType::AmountOverride, ExceptionType::DateOverride);
    });
});

/** Builds the request of a planned item with the user and the route parameter a controller would give it. */
function changeRequest(User $user, PlannedExpense $planned): ChangeOccurrenceRequest
{
    $request = ChangeOccurrenceRequest::create('/x', 'POST');
    $request->setUserResolver(fn (): User => $user);
    $route = new Route('POST', '/x', []);
    $route->bind($request);
    $route->setParameter('planned', $planned);
    $request->setRouteResolver(static fn (): Route => $route);

    return $request;
}

test('only an admin may change a due date', function (): void {
    insideBianchi(function (): void {
        $planned = PlannedExpense::query()->where('title', 'Retta danza')->sole();
        $admin = User::query()->where('role', Role::Admin)->firstOrFail();
        $member = User::factory()->create(['family_id' => $admin->family_id, 'role' => Role::Member]);

        expect(changeRequest($admin, $planned)->authorize())->toBeTrue()
            ->and(changeRequest($member, $planned)->authorize())->toBeFalse();
    });
});

test('the request asks for the amount and the date only when chosen', function (): void {
    $rules = (new ChangeOccurrenceRequest)->rules();

    expect(Validator::make(['change_amount' => true], $rules)->errors()->keys())->toContain('amount')
        ->and(Validator::make(['change_date' => true], $rules)->errors()->keys())->toContain('date')
        ->and(Validator::make(['skip' => true, 'change_amount' => true], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['change_amount' => true, 'amount' => -1], $rules)->errors()->keys())->toContain('amount')
        ->and(Validator::make([], $rules)->passes())->toBeTrue();
});
