<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Mailgun\MailgunRouteException;
use App\Services\Mailgun\MailgunRoutes;
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
     * @param  MailgunRoutes  $routes  Talks to Mailgun.
     */
    public function handle(MailgunRoutes $routes): int
    {
        $domain = config('services.mailgun.inbox_domain');
        $key = config('services.mailgun.api_key');
        $appUrl = config()->string('app.url');

        if (! is_string($key) || trim($key) === '') {
            $this->error('MAILGUN_API_KEY is empty: no route made.');

            return self::FAILURE;
        }

        if (! is_string($domain) || trim($domain) === '') {
            $this->error('MAIL_INBOX_DOMAIN is empty: no route made.');

            return self::FAILURE;
        }

        if (MailgunRoutes::target($appUrl) === null) {
            $this->error('APP_URL must be a public https address: Mailgun cannot reach it. No route made.');

            return self::FAILURE;
        }

        try {
            $result = $routes->sync(trim($domain), $appUrl);
        } catch (MailgunRouteException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Route {$result} for {$domain}.");

        return self::SUCCESS;
    }
}
