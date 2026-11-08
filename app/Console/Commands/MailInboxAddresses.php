<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Mail\AssignMailAddress;
use App\Models\Family;
use App\Models\FamilySetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Gives the address for the bills to the families that have none: the ones registered before the mail inbox, and
 * the ones copied from Directus. It is safe to run again.
 */
#[Description('Give the address for the bills to the families that have none')]
#[Signature('mail-inbox:addresses')]
final class MailInboxAddresses extends Command
{
    /**
     * Goes through the families without an address and makes one for each.
     *
     * @param  AssignMailAddress  $assign  Makes and writes the address.
     */
    public function handle(AssignMailAddress $assign): int
    {
        if (! is_string(config('services.mailgun.inbox_domain')) || trim(config('services.mailgun.inbox_domain')) === '') {
            $this->warn('MAIL_INBOX_DOMAIN is empty: no address made.');

            return self::SUCCESS;
        }

        $made = 0;

        foreach (Family::query()->orderBy('id')->get() as $family) {
            $has = FamilySetting::query()->withoutGlobalScopes()
                ->where('family_id', $family->id)->whereNotNull('mail_inbox_recipient')->exists();

            if (! $has && $assign->handle($family) !== null) {
                $made++;
            }
        }

        $this->info("Addresses made: {$made}");

        return self::SUCCESS;
    }
}
