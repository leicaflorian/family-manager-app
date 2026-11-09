<?php

declare(strict_types=1);

namespace App\Services\Mailgun;

/**
 * Checks the settings and makes the one route of Mailgun that forwards the mail of the whole domain to this
 * installation. The command `mail-inbox:route` and the Mail page of the owner panel both go through it.
 */
final readonly class MailgunRouteSetup
{
    /**
     * @param  MailgunRoutes  $routes  talks to Mailgun
     */
    public function __construct(private MailgunRoutes $routes) {}

    /**
     * The domain of the addresses for the bills, or null when it is not set.
     */
    public function domain(): ?string
    {
        $domain = config('services.mailgun.inbox_domain');

        return is_string($domain) && trim($domain) !== '' ? trim($domain) : null;
    }

    /**
     * Whether the API key is set. The key itself is never given out.
     */
    public function hasApiKey(): bool
    {
        $key = config('services.mailgun.api_key');

        return is_string($key) && trim($key) !== '';
    }

    /**
     * Whether the signing key is set. The key itself is never given out.
     */
    public function hasSigningKey(): bool
    {
        $key = config('services.mailgun.signing_key');

        return is_string($key) && trim($key) !== '';
    }

    /**
     * Makes the route; one that is already right is left as it is.
     *
     * @return array{domain: string, result: 'created'|'updated'|'unchanged'}
     *
     * @throws MailgunRouteException when a setting is missing or Mailgun does not accept the call; the message never has the key
     */
    public function run(): array
    {
        $appUrl = config()->string('app.url');
        $domain = $this->domain();

        if (! $this->hasApiKey()) {
            throw new MailgunRouteException('MAILGUN_API_KEY is empty: no route made.');
        }

        if ($domain === null) {
            throw new MailgunRouteException('MAIL_INBOX_DOMAIN is empty: no route made.');
        }

        if (MailgunRoutes::target($appUrl) === null) {
            throw new MailgunRouteException('APP_URL must be a public https address: Mailgun cannot reach it. No route made.');
        }

        return ['domain' => $domain, 'result' => $this->routes->sync($domain, $appUrl)];
    }
}
