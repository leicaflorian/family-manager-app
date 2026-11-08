<?php

declare(strict_types=1);

namespace App\Actions\Mail;

use App\Enums\Role;
use App\Models\ReminderNotice;
use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\Mail\NoticeText;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Tells the `admin` accounts of the current family, once a day (day of Rome), that more mails arrived than the
 * limit: a notice of the bell with no reminder and no mail, whose line proposes to change the address.
 */
final readonly class NotifyMailLimit
{
    /**
     * Writes the notice for every `admin`, unless one went out today already. Two mails over the limit at the same
     * moment wait for each other, so only one of them writes it.
     *
     * @param  int  $limit  the mails a day the family can receive
     *
     * @throws RuntimeException without a current family
     */
    public function handle(int $limit): void
    {
        $family = CurrentFamily::id() ?? throw new RuntimeException('No family to tell about the limit.');

        Cache::lock("mail-limit-notice:{$family}", 10)->block(5, function () use ($family, $limit): void {
            $sentToday = ReminderNotice::query()
                ->whereNull('reminder_id')
                ->whereNull('received_mail_id')
                ->where('sent_at', '>=', today())
                ->exists();

            if ($sentToday) {
                return;
            }

            $line = NoticeText::translate('More than :count mails arrived today: the others are not saved. If they are spam, change the address.', ['count' => $limit]);

            User::query()->where('family_id', $family)->where('role', Role::Admin)->orderBy('id')->get()
                ->each(fn (User $admin): ReminderNotice => ReminderNotice::query()->create([
                    'user_id' => $admin->id,
                    'sent_at' => now(),
                    'lines' => [$line],
                ]));
        });
    }
}
