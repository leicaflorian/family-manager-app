<?php

declare(strict_types=1);

use App\Mail\MorningNoticesMail;
use App\Models\Family;
use App\Models\User;
use App\Support\Reminders\MorningItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * A notice for the mail.
 *
 * @param  string  $title  the title
 * @param  string  $dueOn  the due date
 */
function item(string $title, string $dueOn = '2026-11-25'): MorningItem
{
    return new MorningItem($title, true, false, 'Panda', $dueOn, 'https://app.test/cards/vehicle/1');
}

test('one mail with three notices', function (): void {
    $user = User::factory()->for(Family::factory())->create();
    $mail = new MorningNoticesMail($user, [item('Renew the policy'), item('Pay the road tax', '2026-12-01'), item('Get the inspection done', '2026-11-20')], '2026-11-25');

    $mail->assertHasSubject('3 reminders coming up');
    $mail->assertSeeInHtml('Renew the policy');
    $mail->assertSeeInHtml('Due today');
    $mail->assertSeeInHtml('Due in 6 days, on 01/12/2026');
    $mail->assertSeeInHtml('Was due 5 days ago, on 20/11/2026');
    $mail->assertSeeInHtml('https://app.test/cards/vehicle/1');
    $mail->assertSeeInHtml('Mark it as done when it is sorted.');
});

test('the subject of a single notice', function (): void {
    $user = User::factory()->for(Family::factory())->create();

    new MorningNoticesMail($user, [item('Renew the policy')], '2026-11-25')->assertHasSubject('Due today: Renew the policy · Panda');
    new MorningNoticesMail($user, [item('Renew the policy', '2026-12-02')], '2026-11-25')->assertHasSubject('Reminder: Renew the policy · Panda');
});

test('a task written by hand is called a task in the subject', function (): void {
    $user = User::factory()->for(Family::factory())->create();
    $task = static fn (string $title, string $dueOn): MorningItem => new MorningItem($title, false, true, null, $dueOn, 'https://app.test/reminders');

    new MorningNoticesMail($user, [$task('Call the plumber', '2026-12-02')], '2026-11-25')->assertHasSubject('Task: Call the plumber');
    new MorningNoticesMail($user, [$task('Call the plumber', '2026-11-25')], '2026-11-25')->assertHasSubject('Due today: Call the plumber');
    new MorningNoticesMail($user, [$task('Call the plumber', '2026-12-02'), $task('Book the dentist', '2026-12-03')], '2026-11-25')->assertHasSubject('2 tasks coming up');
    new MorningNoticesMail($user, [$task('Call the plumber', '2026-12-02'), item('Renew the policy')], '2026-11-25')->assertHasSubject('2 reminders coming up');
});

test('the language of the account', function (): void {
    $user = User::factory()->for(Family::factory())->create(['locale' => 'en']);
    $italian = User::factory()->for(Family::factory())->create(['locale' => 'it']);

    $en = (new MorningNoticesMail($user, [item('Renew the policy')], '2026-11-25'));
    $it = (new MorningNoticesMail($italian, [item('Renew the policy')], '2026-11-25'));
    $it->locale('it');

    $en->assertSeeInHtml('Due today');
    $it->assertSeeInHtml('Scade oggi');
    $it->assertSeeInHtml('Rinnovare la polizza');
});

test('a deleted account does not break the queue', function (): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->for(Family::factory())->create();
    Mail::to($user)->queue(new MorningNoticesMail($user, [item('Renew the policy')], '2026-11-25'));
    $user->delete();

    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
});
