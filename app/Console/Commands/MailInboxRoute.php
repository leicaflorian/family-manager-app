<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Mailgun\MailgunRouteException;
use App\Services\Mailgun\MailgunRouteSetup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Makes the one route of Mailgun that forwards the mail of the whole domain to this installation. It is safe to
 * run again: a route that is already right is left as it is.
 */
#[Description('Make the Mailgun route that forwards the mail of the whole domain to this app')]
#[Signature('mail-inbox:route')]
final class MailInboxRoute extends Command
{
    /**
     * Checks the settings and makes the route; the API key never goes to the output.
     *
     * @param  MailgunRouteSetup  $setup  Checks the settings and talks to Mailgun.
     */
    public function handle(MailgunRouteSetup $setup): int
    {
        try {
            $done = $setup->run();
        } catch (MailgunRouteException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Route {$done['result']} for {$done['domain']}.");

        return self::SUCCESS;
    }
}
