<?php

declare(strict_types=1);

use App\Support\Reminders\GroupRules;

/** The kinds of deadline that exist, as the page sends them back. */
const GROUP_KINDS = ['vehicles.road_tax_due_date', 'vehicles.inspection_due_date', 'documents.expiry_date'];

/**
 * A group as the page sends it.
 *
 * @param  array<string, mixed>  $state  the fields to set
 * @return array<string, mixed>
 */
function aGroup(array $state = []): array
{
    return ['id' => null, 'name' => 'Vehicles', 'is_default' => false, 'deadline_types' => [], 'notifications' => [], ...$state];
}

test('the whole list: a group left out is deleted, one without an id is new', function (): void {
    $edits = GroupRules::edits([aGroup(['id' => 1]), aGroup(['name' => 'New'])], [1, 2]);

    expect($edits['create'])->toHaveCount(1)
        ->and($edits['create'][0]['name'])->toBe('New')
        ->and($edits['update'])->toHaveCount(1)
        ->and($edits['update'][0]['id'])->toBe(1)
        ->and($edits['delete'])->toBe([2]);
});

test('a kind of deadline is in one group only', function (): void {
    $errors = GroupRules::check([
        aGroup(['id' => 1, 'name' => 'Cars', 'is_default' => true, 'deadline_types' => ['vehicles.inspection_due_date']]),
        aGroup(['id' => 2, 'name' => 'Inspection', 'deadline_types' => ['vehicles.inspection_due_date']]),
    ], GROUP_KINDS);

    expect($errors)->toBe(['Inspection is both in the group «Cars» and in «Inspection»: a kind of deadline is in one group only.']);
});

test('there is always one default group', function (): void {
    expect(GroupRules::check([aGroup(['id' => 1])], GROUP_KINDS))->toBe(['There must always be a default group: the one of the deadlines that are in no group. To change it, mark another group as the default.'])
        ->and(GroupRules::check([], GROUP_KINDS))->toHaveCount(1);
});

test('only one default group: two at once are refused', function (): void {
    $errors = GroupRules::check([aGroup(['name' => 'A', 'is_default' => true]), aGroup(['name' => 'B', 'is_default' => true])], GROUP_KINDS);

    expect($errors)->toBe(['The default group is only one: choose between «A» and «B».']);
});

test('a valid list has no errors', function (): void {
    $notice = ['name' => 'Reminder', 'days' => 7, 'timing' => 'before', 'channels' => 'app', 'recipients' => ['all']];

    expect(GroupRules::check([aGroup(['is_default' => true, 'notifications' => [$notice]])], GROUP_KINDS))->toBe([]);
});

test('the rules of a notice are the ones of the row', function (): void {
    $errors = GroupRules::check([aGroup(['is_default' => true, 'notifications' => [
        ['name' => 'Early', 'days' => -1, 'timing' => 'before', 'channels' => 'app', 'recipients' => ['all']],
    ]])], GROUP_KINDS);

    expect($errors)->toBe(['«Early»: days must be a whole number, from 0 up (0 is the day of the deadline).']);
});

test('a recipient is needed and Everybody excludes the people', function (): void {
    $row = ['days' => 3, 'timing' => 'before', 'channels' => 'app'];

    expect(GroupRules::check([aGroup(['is_default' => true, 'notifications' => [[...$row, 'recipients' => []]]])], GROUP_KINDS))->toHaveCount(1)
        ->and(GroupRules::check([aGroup(['is_default' => true, 'notifications' => [[...$row, 'recipients' => ['all', 4]]]])], GROUP_KINDS))->toHaveCount(1);
});

test('the kinds that do not exist are dropped, and the rows come back in the saved shape', function (): void {
    $groups = GroupRules::clean([aGroup(['is_default' => true, 'deadline_types' => ['vehicles.road_tax_due_date', 'ghost.field'], 'notifications' => [
        ['days' => '3', 'timing' => 'after', 'channels' => 'nonsense', 'recipients' => ['4']],
    ]])], GROUP_KINDS);

    expect($groups[0]['deadline_types'])->toBe(['vehicles.road_tax_due_date'])
        ->and($groups[0]['notifications'])->toBe([['name' => 'Promemoria', 'days' => 3, 'timing' => 'after', 'channels' => 'both', 'recipients' => [4]]]);
});

test('a dropped kind does not count as a duplicate', function (): void {
    $errors = GroupRules::check([
        aGroup(['name' => 'A', 'is_default' => true, 'deadline_types' => ['ghost.field']]),
        aGroup(['name' => 'B', 'deadline_types' => ['ghost.field']]),
    ], GROUP_KINDS);

    expect($errors)->toBe([]);
});
