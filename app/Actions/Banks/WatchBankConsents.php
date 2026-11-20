<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Enums\BankConnectionStatus;
use App\Enums\Role;
use App\Mail\BankConsentExpiringMail;
use App\Models\BankConnection;
use App\Models\ReminderNotice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Watches the consent of the banks of a family: a week before it ends, and when the bank takes it back, every `admin`
 * gets a notice in the bell and an email asking to connect the bank again, once. Runs inside the family.
 */
final readonly class WatchBankConsents
{
    /**
     * Turns the connections whose consent is over into «to connect again», and warns about the ones that end within
     * `warn_days` days. A connection already warned (`expiry_notified_at`) is not warned again until it is connected
     * again.
     *
     * @param  CarbonImmutable  $now  the moment of the check
     */
    public function handle(CarbonImmutable $now): void
    {
        // Also a connection in `Error` (the bank answers with errors): its consent is over all the same
        BankConnection::query()
            ->where('status', '!=', BankConnectionStatus::NeedsReconnect)
            ->whereNotNull('valid_until')->where('valid_until', '<=', $now)
            ->update(['status' => BankConnectionStatus::NeedsReconnect]);

        BankConnection::query()
            ->where('status', BankConnectionStatus::Active)->whereNull('expiry_notified_at')
            ->where('valid_until', '>', $now)->where('valid_until', '<=', $now->addDays(config()->integer('bank_feed.warn_days')))
            ->orderBy('id')->get()
            ->each(function (BankConnection $connection): void {
                try {
                    $this->tell($connection, false);
                } catch (Throwable $failure) {
                    // One connection that fails does not keep the others from being told
                    report($failure);
                }
            });
    }

    /**
     * Tells that the bank took the consent back, unless the person was already told about this connection.
     *
     * @param  BankConnection  $connection  the connection, inside its family
     */
    public function ended(BankConnection $connection): void
    {
        if ($connection->expiry_notified_at !== null) {
            return;
        }

        $this->tell($connection, true);
    }

    /**
     * Writes the notice of every `admin` and remembers it was done, all in one transaction, and only then queues the
     * emails: a failure while writing leaves nothing behind (the next check tells again), and a failure of the queue
     * cannot make the notices be written twice. An email that cannot be queued is reported and the others go on.
     *
     * @param  BankConnection  $connection  the connection
     * @param  bool  $ended  true when the bank took the consent back, false when it is about to expire
     */
    private function tell(BankConnection $connection, bool $ended): void
    {
        $bank = $connection->institution_name;
        // Only the warning has a date to say: a consent the bank took back has none
        $date = $ended ? '' : ($connection->valid_until?->setTimezone('Europe/Rome')->format('d/m/Y') ?? '');
        $now = CarbonImmutable::now();
        $admins = User::query()->where('family_id', $connection->family_id)->where('role', Role::Admin)->orderBy('id')->get();

        $mails = DB::transaction(function () use ($connection, $admins, $bank, $date, $ended, $now): array {
            $mails = [];

            foreach ($admins as $admin) {
                $locale = $admin->locale ?? config()->string('app.locale');
                $mail = new BankConsentExpiringMail($admin, $bank, $date, $ended);
                ReminderNotice::query()->create(['user_id' => $admin->id, 'bank_connection_id' => $connection->id, 'sent_at' => $now, 'lines' => [$mail->sentence($locale)]]);
                $mails[] = [$admin, $locale, $mail];
            }

            $connection->forceFill(['expiry_notified_at' => $now])->save();

            return $mails;
        });

        foreach ($mails as [$admin, $locale, $mail]) {
            try {
                Mail::to($admin)->locale($locale)->queue($mail);
            } catch (Throwable $failure) {
                report($failure);
            }
        }
    }
}
