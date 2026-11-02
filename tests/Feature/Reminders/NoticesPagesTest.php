<?php

declare(strict_types=1);

use App\Enums\ReminderStatus;
use App\Models\Family;
use App\Models\Reminder;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->laura = User::factory()->create(['family_id' => $this->marco->family_id, 'role' => 'member', 'email_verified_at' => now()]);
});

afterEach(fn () => Date::setTestNow());

/**
 * A notice of the Bianchi for an account.
 *
 * @param  User  $user  who it was sent to
 * @param  array<string, mixed>  $notice  the columns of the notice
 * @param  array<string, mixed>  $reminder  the columns of its reminder
 */
function noticeFor(User $user, array $notice = [], array $reminder = []): ReminderNotice
{
    return insideBianchi(fn (): ReminderNotice => ReminderNotice::factory()->create([
        'family_id' => $user->family_id,
        'user_id' => $user->id,
        'reminder_id' => Reminder::factory()->create([
            'family_id' => $user->family_id,
            'title' => 'Pagare il bollo',
            'due_date' => '2026-10-31',
            'is_automatic' => false,
            ...$reminder,
        ])->id,
        'due_date' => '2026-10-31',
        'sent_at' => CarbonImmutable::parse('2026-10-24 06:00', 'Europe/Rome'),
        ...$notice,
    ]));
}

/**
 * The props of the notices page as an account sees them.
 *
 * @return array<string, mixed>
 */
function noticesPage(User $user): array
{
    return test()->actingAs($user)->get('/notices')->assertOk()->viewData('page')['props'];
}

test('the page lists only the notices of the account, the new ones apart from the seen ones', function (): void {
    $new = noticeFor($this->marco);
    $seen = noticeFor($this->marco, ['read_at' => now()]);
    noticeFor($this->laura);

    $page = noticesPage($this->marco);

    expect(collect($page['unread'])->pluck('id')->all())->toBe([$new->id])
        ->and(collect($page['seen'])->pluck('id')->all())->toBe([$seen->id]);
});

test('a row has the title, the words of the deadline of that day, when it went out and where it leads', function (): void {
    $notice = noticeFor($this->marco);

    $row = noticesPage($this->marco)['unread'][0];

    expect($row['id'])->toBe($notice->id)
        ->and($row['title'])->toBe('Pagare il bollo')
        ->and($row['due_text'])->toBe('By 31/10/2026, in 7 days')
        ->and($row['sent_at'])->toBe('2026-10-24T06:00')
        ->and($row['read'])->toBeFalse()
        ->and($row['state'])->toBeNull()
        ->and($row['area'])->toBe('general')
        ->and($row['href'])->toBe("/reminders/{$notice->reminder_id}/edit");
});

test('a notice of an automatic reminder names the thing and leads to its page', function (): void {
    $reminder = insideBianchi(fn (): Reminder => Reminder::query()->where('is_automatic', true)->where('status', ReminderStatus::Open)->with('link')->firstOrFail());
    $notice = noticeFor($this->marco, ['reminder_id' => $reminder->id, 'due_date' => $reminder->due_date]);

    $row = collect(noticesPage($this->marco)['unread'])->firstWhere('id', $notice->id);

    expect($row['title'])->toContain(' · ')
        ->and($row['href'])->toStartWith('/')
        ->and($row['href'])->not->toContain('/reminders/')
        ->and($row['area'])->not->toBeNull();
});

test('the state is the one of the reminder today: expired when still open and late, done when done', function (): void {
    $late = noticeFor($this->marco, [], ['due_date' => '2026-10-20']);
    $done = noticeFor($this->marco, [], ['status' => ReminderStatus::Done]);
    $coming = noticeFor($this->marco);

    $rows = collect(noticesPage($this->marco)['unread']);

    expect($rows->firstWhere('id', $late->id)['state'])->toBe('expired')
        ->and($rows->firstWhere('id', $done->id)['state'])->toBe('done')
        ->and($rows->firstWhere('id', $coming->id)['state'])->toBeNull();
});

test('the page puts the most recent notice first', function (): void {
    $older = noticeFor($this->marco, ['sent_at' => CarbonImmutable::parse('2026-10-20 06:00', 'Europe/Rome')]);
    $newer = noticeFor($this->marco);

    expect(collect(noticesPage($this->marco)['unread'])->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

test('marking one read sets the date and goes to its page', function (): void {
    $notice = noticeFor($this->marco);

    $this->actingAs($this->marco)->post("/notices/{$notice->id}/read")
        ->assertRedirect("/reminders/{$notice->reminder_id}/edit")
        ->assertStatus(303);

    expect(insideBianchi(fn (): bool => ReminderNotice::query()->findOrFail($notice->id)->read_at !== null))->toBeTrue();
});

test('marking one read twice keeps the first date', function (): void {
    $notice = noticeFor($this->marco, ['read_at' => CarbonImmutable::parse('2026-10-25 08:00', 'Europe/Rome')]);

    $this->actingAs($this->marco)->post("/notices/{$notice->id}/read")->assertRedirect();

    expect(insideBianchi(fn (): string => ReminderNotice::query()->findOrFail($notice->id)->read_at->format('Y-m-d H:i')))->toBe('2026-10-25 08:00');
});

test('the Home sends the notices of the bell only when the menu asks for them', function (): void {
    $new = noticeFor($this->marco);
    $seen = noticeFor($this->marco, ['read_at' => now()]);
    noticeFor($this->laura);

    $this->actingAs($this->marco)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->missing('notices')
            ->reloadOnly('notices', fn ($reload) => $reload
                ->where('notices.unread.0.id', $new->id)
                ->has('notices.unread', 1)
                ->where('notices.seen.0.id', $seen->id)
                ->has('notices.seen', 1)));
});

test('read all goes back to the page it was asked from', function (): void {
    noticeFor($this->marco);

    $this->actingAs($this->marco)->from('/dashboard')->post('/notices/read')->assertRedirect('/dashboard');
});

test('read all marks only the notices of the account', function (): void {
    $mine = noticeFor($this->marco);
    $hers = noticeFor($this->laura);

    $this->actingAs($this->marco)->from('/notices')->post('/notices/read')->assertRedirect('/notices');

    insideBianchi(function () use ($mine, $hers): void {
        expect(ReminderNotice::query()->findOrFail($mine->id)->read_at)->not->toBeNull()
            ->and(ReminderNotice::query()->findOrFail($hers->id)->read_at)->toBeNull();
    });
});

test('the notice of another account of the family is a 404, to read and to mark', function (): void {
    $hers = noticeFor($this->laura);

    $this->actingAs($this->marco)->post("/notices/{$hers->id}/read")->assertNotFound();

    expect(insideBianchi(fn (): ?CarbonImmutable => ReminderNotice::query()->findOrFail($hers->id)->read_at))->toBeNull();
});

test('the notice of another family is a 404', function (): void {
    $other = Family::factory()->create();
    $stranger = User::factory()->create(['family_id' => $other->id, 'email_verified_at' => now()]);
    $notice = CurrentFamily::using($other->id, fn (): ReminderNotice => ReminderNotice::factory()->create(['family_id' => $other->id, 'user_id' => $stranger->id]));

    $this->actingAs($this->marco)->post("/notices/{$notice->id}/read")->assertNotFound();
});

test('the notices need a signed-in account', function (): void {
    $this->get('/notices')->assertRedirect('/login');
    $this->post('/notices/read')->assertRedirect('/login');
});

test('unread_notices counts the unread notices of the account only', function (): void {
    noticeFor($this->marco);
    noticeFor($this->marco);
    noticeFor($this->marco, ['read_at' => now()]);
    noticeFor($this->laura);

    $this->actingAs($this->marco)->get('/dashboard')->assertInertia(fn ($page) => $page->where('unread_notices', 2));
    $this->actingAs($this->laura)->get('/dashboard')->assertInertia(fn ($page) => $page->where('unread_notices', 1));
});

test('unread_notices is 0 when logged out', function (): void {
    $this->get('/login')->assertInertia(fn ($page) => $page->where('unread_notices', 0));
});

test('a notice of an automatic reminder whose thing is gone leads to the list, which answers 200, not to the edit page', function (): void {
    $notice = noticeFor($this->marco, [], ['is_automatic' => true, 'status' => ReminderStatus::Done]);

    $row = collect(noticesPage($this->marco)['unread'])->firstWhere('id', $notice->id);

    expect($row['href'])->toBe('/reminders');
});
