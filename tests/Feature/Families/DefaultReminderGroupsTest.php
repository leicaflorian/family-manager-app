<?php

declare(strict_types=1);

use App\Actions\Families\CreateFamily;
use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use App\Models\ReminderGroup;
use App\Support\CurrentFamily;
use App\Support\Reminders\GroupRules;
use Illuminate\Support\Facades\App;

/**
 * Registers a family and runs the callback inside it.
 */
function insideNewlyRegisteredFamily(callable $callback): mixed
{
    $account = resolve(CreateFamily::class)->handle('Rossi', 'Anna', 'Rossi', 'anna@example.com', 'a-long-password');

    return CurrentFamily::using($account->family_id, $callback);
}

test('a new family starts with the five groups of the board', function (): void {
    $groups = insideNewlyRegisteredFamily(fn () => ReminderGroup::query()->orderBy('id')->get());

    expect($groups->pluck('name')->all())->toBe(['Policies', 'Vehicles', 'Documents and health', 'Subscriptions', 'Default'])
        ->and($groups->where('is_default', true)->pluck('name')->all())->toBe(['Default'])
        ->and($groups[1]->deadline_types)->toBe(['vehicles.road_tax_due_date', 'vehicles.inspection_due_date'])
        ->and($groups[1]->notifications)->toHaveCount(4)
        ->and($groups[1]->notifications[3])->toEqual(['name' => 'Reminder', 'days' => 7, 'timing' => 'after', 'channels' => 'both', 'recipients' => ['all']])
        ->and(collect($groups[2]->notifications)->map(fn (array $n): string => $n['days'].$n['channels'])->all())->toBe(['90email', '7both', '1both'])
        ->and(collect($groups[3]->notifications)->pluck('channels')->unique()->all())->toBe(['app'])
        ->and($groups[4]->deadline_types)->toBe([]);
});

test('the starting groups pass the rules of the settings and name real kinds of deadline', function (): void {
    $groups = insideNewlyRegisteredFamily(fn () => ReminderGroup::query()->get()->map(fn (ReminderGroup $group): array => $group->only(['id', 'name', 'is_default', 'deadline_types', 'notifications']))->all());
    $fields = array_map(fn (DeadlineSource $source): string => $source->field, DeadlineSources::all());

    expect(GroupRules::check($groups, $fields))->toBe([])
        ->and(collect($groups)->pluck('deadline_types')->flatten()->diff($fields)->all())->toBe([]);
});

test('the group names are in the language of whoever registers', function (): void {
    App::setLocale('it');

    $names = insideNewlyRegisteredFamily(fn () => ReminderGroup::query()->orderBy('id')->pluck('name')->all());

    expect($names)->toBe(['Polizze', 'Veicoli', 'Documenti e salute', 'Abbonamenti', 'Predefinito']);
});
