<?php

declare(strict_types=1);

use App\Data\DeadlineData;
use App\Data\MovementData;
use App\Enums\Role;
use App\Models\Expense;
use App\Models\Family;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Services\Deadlines;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->seed(BianchiFamilySeeder::class);
});

/** The data of a due date of the Retta danza of the Bianchi. */
function budgetDataRetta(string $day): DeadlineData
{
    return insideBianchi(function () use ($day): DeadlineData {
        $retta = PlannedExpense::query()->where('title', 'Retta danza')->sole();
        $deadline = resolve(Deadlines::class)->find($retta, CarbonImmutable::parse($day, 'Europe/Rome'));

        return DeadlineData::fromDeadline($deadline, CarbonImmutable::today('Europe/Rome'));
    });
}

test('it sends a due date with its payment', function (): void {
    $data = budgetDataRetta('2026-11-05');

    expect($data->kind)->toBe('expense')
        ->and($data->original_date)->toBe('2026-11-05')
        ->and($data->amount)->toBe(45.0)
        ->and($data->late)->toBeFalse()
        ->and($data->payment?->date)->toBe('2026-10-20')
        ->and($data->payment?->amount)->toBe(45.0)
        ->and($data->payment?->account)->toBe('Carta Laura')
        ->and($data->card?->type)->toBe('enrollment')
        ->and($data->person)->toBe('Giulia');
});

test('it sends a changed amount with the old one', function (): void {
    $data = budgetDataRetta('2026-12-05');

    expect($data->amount)->toBe(60.0)
        ->and($data->base_amount)->toBe(45.0)
        ->and($data->notes)->toBe('Comprende la quota del saggio di Natale')
        ->and($data->payment)->toBeNull();
});

test('it lets a member edit a movement but not delete it', function (): void {
    $member = User::factory()->create(['family_id' => Family::query()->where('name', 'Bianchi')->sole()->id, 'role' => Role::Member]);

    $data = insideBianchi(fn (): MovementData => MovementData::fromMovement(Expense::query()->where('title', 'Visita dal dentista')->sole(), $member));

    expect($data->kind)->toBe('expense')
        ->and($data->amount)->toBe(80.0)
        ->and($data->category)->not->toBeNull()
        ->and($data->can_edit)->toBeTrue()
        ->and($data->can_delete)->toBeFalse();
});
