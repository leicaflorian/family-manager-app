<?php

declare(strict_types=1);

use App\Models\Person;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Models\User;
use App\Support\Reminders\Channel;
use App\Support\Reminders\Delivery;
use App\Support\Reminders\Notices;
use App\Support\Reminders\NotificationRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A day in Rome.
 */
function noticeDay(string $day): CarbonImmutable
{
    return CarbonImmutable::parse($day, 'Europe/Rome');
}

/**
 * A rule for everybody, in the app and by email unless said otherwise.
 *
 * @param  list<int>|null  $people
 */
function rule(int $offset, Channel $channels = Channel::Both, ?array $people = null): NotificationRule
{
    return new NotificationRule($offset, $channels, $people);
}

/**
 * An account of a person, as the morning round loads it.
 */
function account(int $userId, int $personId): User
{
    $user = (new User)->forceFill(['id' => $userId]);
    $user->setRelation('person', (new Person)->forceFill(['id' => $personId]));

    return $user;
}

/**
 * A saved row of a group.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function savedRow(array $overrides = []): array
{
    return ['offset' => -7, 'channels' => 'both', 'recipients' => ['all'], ...$overrides];
}

/**
 * A group of the family.
 *
 * @param  list<NotificationRule>  $rules
 * @param  list<string>  $types
 */
function noticeGroup(int $id, bool $default, array $types, int $offset): ReminderGroup
{
    return (new ReminderGroup)->forceFill([
        'id' => $id,
        'is_default' => $default,
        'deadline_types' => $types,
        'notifications' => [savedRow(['offset' => $offset])],
    ]);
}

/**
 * The offsets of some rules.
 *
 * @param  list<NotificationRule>  $rules
 * @return list<int>
 */
function offsets(array $rules): array
{
    return array_map(static fn (NotificationRule $rule): int => $rule->offset, $rules);
}

describe('notificationsOf', function (): void {
    test('days before and after are an offset, ids as text become numbers', function (): void {
        $rules = NotificationRule::listOf([
            savedRow(),
            savedRow(['offset' => 2, 'recipients' => ['1', 1, '3']]),
        ]);

        expect($rules)->toEqual([rule(-7), rule(2, Channel::Both, [1, 3])]);
    });

    test('an unknown channel is both, nothing is an empty list', function (): void {
        expect(NotificationRule::listOf([savedRow(['channels' => 'zzz'])])[0]->channels)->toBe(Channel::Both)
            ->and(NotificationRule::listOf(null))->toBe([])
            ->and(NotificationRule::listOf('not a list'))->toBe([]);
    });

    test('a row that cannot be read is left out', function (): void {
        $rules = NotificationRule::listOf([
            savedRow(),
            savedRow(['offset' => 'x']),
            savedRow(['offset' => 1.5]),
            savedRow(['recipients' => []]),
            savedRow(['recipients' => ['all', 3]]),
            savedRow(['recipients' => ['x']]),
            'not a row',
            savedRow(['offset' => 1]),
        ]);

        expect(offsets($rules))->toBe([-7, 1]);
    });
});

describe('notificationsFor', function (): void {
    // Built inside the tests: a model needs the booted app.
    $auto = static fn (): ReminderGroup => noticeGroup(1, false, ['vehicles.road_tax_due_date'], -30);
    $default = static fn (): ReminderGroup => noticeGroup(2, true, [], -3);

    test('an automatic reminder has the group of its kind of deadline, or the default one', function () use ($auto, $default): void {
        $groups = collect([$auto(), $default()]);

        $road = (new Reminder)->forceFill(['is_automatic' => true, 'source_field' => 'vehicles.road_tax_due_date']);
        $inspection = (new Reminder)->forceFill(['is_automatic' => true, 'source_field' => 'vehicles.inspection_due_date']);

        expect(offsets(Notices::rulesFor($road, $groups)))->toBe([-30])
            ->and(offsets(Notices::rulesFor($inspection, $groups)))->toBe([-3]);
    });

    test('a manual reminder has the group chosen in its field, or the default one', function () use ($auto, $default): void {
        $groups = collect([$auto(), $default()]);

        $chosen = (new Reminder)->forceFill(['is_automatic' => false, 'reminder_group_id' => 1]);
        $none = (new Reminder)->forceFill(['is_automatic' => false, 'reminder_group_id' => null]);

        expect(offsets(Notices::rulesFor($chosen, $groups)))->toBe([-30])
            ->and(offsets(Notices::rulesFor($none, $groups)))->toBe([-3]);
    });

    test('without a default group: a week before, the day before, the day', function () use ($auto): void {
        $none = (new Reminder)->forceFill(['is_automatic' => false, 'reminder_group_id' => null]);

        expect(offsets(Notices::rulesFor($none, collect([$auto()]))))->toBe([-7, -1, 0])
            ->and(Notices::fallback())->toEqual([rule(-7), rule(-1), rule(0)]);
    });
});

describe('noticesOf and dueNotices', function (): void {
    $deadline = noticeDay('2026-11-15');

    test('the key carries the deadline and the offset', function () use ($deadline): void {
        expect(Notices::key($deadline, rule(-7)))->toBe('2026-11-15:-7')
            ->and(Notices::key($deadline, rule(0)))->toBe('2026-11-15:0');
    });

    test('nothing before the first notice', function () use ($deadline): void {
        expect(Notices::due($deadline, Notices::fallback(), [], noticeDay('2026-11-07')))->toBe(['send' => [], 'sent' => []]);
    });

    test('a notice goes out on its day', function () use ($deadline): void {
        $due = Notices::due($deadline, Notices::fallback(), [], noticeDay('2026-11-08'));

        expect(offsets($due['send']))->toBe([-7])
            ->and($due['sent'])->toBe(['2026-11-15:-7']);
    });

    test('after days with the server down, only the notice of the latest day goes out', function () use ($deadline): void {
        $due = Notices::due($deadline, Notices::fallback(), [], noticeDay('2026-11-14'));

        expect(offsets($due['send']))->toBe([-1])
            ->and($due['sent'])->toBe(['2026-11-15:-7', '2026-11-15:-1']);
    });

    test('the latest day wins whatever the order of the rules', function () use ($deadline): void {
        $due = Notices::due($deadline, [rule(0), rule(-7), rule(-1)], [], noticeDay('2026-11-14'));

        expect(offsets($due['send']))->toBe([-1])
            ->and($due['sent'])->toBe(['2026-11-15:-7', '2026-11-15:-1']);
    });

    test('a notice already sent does not go out again', function () use ($deadline): void {
        $due = Notices::due($deadline, Notices::fallback(), ['2026-11-15:-1'], noticeDay('2026-11-14'));

        expect($due['send'])->toBe([]);
    });

    test('after the deadline only the notice of the day', function () use ($deadline): void {
        expect(offsets(Notices::due($deadline, Notices::fallback(), [], noticeDay('2026-11-20'))['send']))->toBe([0]);
    });

    test('a deadline moved to a new date is notified again', function (): void {
        $due = Notices::due(noticeDay('2026-11-22'), Notices::fallback(), ['2026-11-15:-7'], noticeDay('2026-11-15'));

        expect(offsets($due['send']))->toBe([-7]);
    });
});

describe('deliveriesOf', function (): void {
    /** @var Closure(): Collection<int, User> $accounts */
    $accounts = static fn (): Collection => collect([account(11, 1), account(12, 2)]);

    /**
     * The user ids and channels of some deliveries.
     *
     * @param  list<Delivery>  $deliveries
     * @return list<array{int, bool, bool}>
     */
    $shape = static fn (array $deliveries): array => array_map(
        static fn (Delivery $d): array => [$d->user->id, $d->app, $d->email],
        $deliveries,
    );

    test('one delivery per account, with the channels of all its notices', function () use ($accounts, $shape): void {
        $deliveries = Notices::deliveriesOf([rule(0, Channel::App), rule(0, Channel::Email, [1])], $accounts());

        expect($shape($deliveries))->toBe([[11, true, true], [12, true, false]]);
    });

    test('chosen people without an account: the notice goes to everybody', function () use ($accounts, $shape): void {
        expect($shape(Notices::deliveries(rule(0, Channel::Both, [9]), $accounts())))->toBe([[11, true, true], [12, true, true]]);
    });

    test('of the chosen people, those without an account are skipped', function () use ($accounts, $shape): void {
        expect($shape(Notices::deliveries(rule(0, Channel::Email, [2, 9]), $accounts())))->toBe([[12, false, true]]);
    });

    test('no account, no delivery', function (): void {
        expect(Notices::deliveries(rule(0), collect()))->toBe([])
            ->and(Notices::deliveriesOf([rule(0)], collect()))->toBe([]);
    });
});

describe('next', function (): void {
    $reminder = static fn (): Reminder => (new Reminder)->forceFill(['due_date' => noticeDay('2026-11-15')]);

    test('the first notice still to come', function () use ($reminder): void {
        expect(Notices::next($reminder(), Notices::fallback(), noticeDay('2026-11-01'))?->toDateString())->toBe('2026-11-08')
            ->and(Notices::next($reminder(), Notices::fallback(), noticeDay('2026-11-08'))?->toDateString())->toBe('2026-11-14');
    });

    test('no more notices once the last day has come', function () use ($reminder): void {
        expect(Notices::next($reminder(), Notices::fallback(), noticeDay('2026-11-15')))->toBeNull()
            ->and(Notices::next($reminder(), [], noticeDay('2026-11-01')))->toBeNull();
    });
});
