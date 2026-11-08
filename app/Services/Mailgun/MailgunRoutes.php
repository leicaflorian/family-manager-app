<?php

declare(strict_types=1);

namespace App\Services\Mailgun;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The route of Mailgun that forwards the mail of the whole domain to this installation. The routes belong to the
 * account (not to a domain), and only an account key reads them.
 */
final class MailgunRoutes
{
    /** The description of the route we create. */
    public const string DESCRIPTION = 'Family Manager - posta in arrivo';

    /** The path of the door of Mailgun, after the public address of the app. */
    private const string PATH = '/mail/mailgun';

    /** What Mailgun gives for each call of the list. */
    private const int PAGE = 100;

    /** More routes than these are not ours to look through: the list is not what it seems. */
    private const int MAX_PAGES = 50;

    /** Seconds before a call to Mailgun is given up. */
    private const int TIMEOUT = 10;

    /** The suffixes that are never public (RFC 2606, 6761, 6762). */
    private const array LOCAL_SUFFIXES = ['localhost', 'test', 'local', 'internal', 'invalid', 'example'];

    /**
     * Where the route forwards to, or null when Mailgun cannot reach this installation: no https, or a local host.
     *
     * @param  string  $appUrl  The public address of the app.
     */
    public static function target(string $appUrl): ?string
    {
        $url = self::plain(trim($appUrl));

        if ($url === null || ! str_starts_with($url, 'https://')) {
            return null;
        }

        return self::isLocalHost((string) parse_url($url, PHP_URL_HOST)) ? null : $url.self::PATH;
    }

    /**
     * Makes the routes that forward to our door match the whole domain: with none, one is created; the others get
     * the new filter and nothing else of them changes. No route is deleted, and those that forward elsewhere are
     * never touched.
     *
     * @param  string  $domain  The domain of the addresses for the bills.
     * @param  string  $appUrl  The public address of the app.
     * @return 'created'|'updated'|'unchanged'
     *
     * @throws MailgunRouteException When the address is not reachable, or Mailgun refuses or does not answer.
     */
    public function sync(string $domain, string $appUrl): string
    {
        $target = self::target($appUrl) ?? throw new MailgunRouteException('Mailgun cannot reach this address: it needs https and a public host.');
        $wanted = $this->expression($domain);
        $ours = array_filter(
            $this->routes(),
            fn (array $route): bool => array_any($route['actions'], fn (string $action): bool => $this->forwardsTo($action, $target)),
        );

        if ($ours === []) {
            $this->call('post', '/v3/routes', 'create the route', [
                'priority' => '0',
                'description' => self::DESCRIPTION,
                'expression' => $wanted,
                'action' => 'forward("'.$target.'")',
            ]);

            return 'created';
        }

        $stale = array_filter($ours, fn (array $route): bool => $route['expression'] !== $wanted);

        foreach ($stale as $route) {
            $this->call('put', '/v3/routes/'.$route['id'], 'change the route', ['expression' => $wanted]);
        }

        return $stale === [] ? 'unchanged' : 'updated';
    }

    /**
     * The filter of the route: every address of the domain. It is a regular expression, so the dots are escaped.
     */
    private function expression(string $domain): string
    {
        return 'match_recipient(".*@'.str_replace('.', '\.', $domain).'")';
    }

    /**
     * An address as it is compared: scheme and host in lower case, no final slash, nothing after the path.
     */
    private static function plain(string $address): ?string
    {
        $parts = parse_url($address);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return strtolower($parts['scheme']).'://'.strtolower($parts['host']).$port.rtrim($parts['path'] ?? '', '/');
    }

    /**
     * A host Mailgun cannot reach: a local domain, or an IP (IPv6 in brackets), which has no certificate of its own.
     */
    private static function isLocalHost(string $host): bool
    {
        if (str_starts_with($host, '[') || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        $parts = explode('.', $host);

        return in_array(end($parts), self::LOCAL_SUFFIXES, true);
    }

    /**
     * Tells if an action of a route is a forward to our door; a route written by hand can have other quotes, a
     * final slash, or capitals in the host.
     */
    private function forwardsTo(string $action, string $target): bool
    {
        if (preg_match('/^\s*forward\(\s*["\'](.*)["\']\s*\)\s*$/', $action, $found) !== 1) {
            return false;
        }

        return self::plain($found[1]) === $target;
    }

    /**
     * All the routes of the account, by id, so a list that gives a route twice has it changed once.
     *
     * @return array<string, array{id: string, expression: string, actions: list<string>}>
     *
     * @throws MailgunRouteException When Mailgun refuses, does not answer or gives something that is not a list.
     */
    private function routes(): array
    {
        $found = [];

        for ($pages = 0; ; $pages++) {
            if ($pages === self::MAX_PAGES) {
                throw new MailgunRouteException('Mailgun did not give the routes: there are too many.');
            }

            $page = $this->call('get', '/v3/routes?limit='.self::PAGE.'&skip='.($pages * self::PAGE), 'give the routes');

            // with no route Mailgun gives {"total_count": 0, "items": null}
            if (is_array($page) && array_key_exists('items', $page) && $page['items'] === null && ($page['total_count'] ?? null) === 0) {
                break;
            }

            // read as "no route", an answer that is not the list would make a second route
            if (! is_array($page) || ! is_array($page['items'] ?? null)) {
                throw new MailgunRouteException('Mailgun did not give the routes: answer not recognised.');
            }

            $before = count($found);

            foreach ($page['items'] as $item) {
                if (is_array($item) && is_string($item['id'] ?? null)) {
                    $actions = array_values(array_filter(is_array($item['actions'] ?? null) ? $item['actions'] : [], is_string(...)));
                    $found[$item['id']] = ['id' => $item['id'], 'expression' => is_string($item['expression'] ?? null) ? $item['expression'] : '', 'actions' => $actions];
                }
            }

            // the last page is not full; the others end the list when all the routes are here, or none is new
            if (count($page['items']) < self::PAGE || count($found) === $before) {
                break;
            }

            if (is_int($page['total_count'] ?? null) && count($found) >= $page['total_count']) {
                break;
            }
        }

        return $found;
    }

    /**
     * One call to Mailgun, with the key as Basic auth.
     *
     * @param  'get'|'post'|'put'  $method
     * @param  array<string, string>  $fields  The fields of a write, sent as a form.
     * @param  string  $did  What the call was for, for the message of a failure ("create the route").
     * @return mixed The decoded answer.
     *
     * @throws MailgunRouteException When Mailgun refuses or does not answer; the message never has the key.
     */
    private function call(string $method, string $path, string $did, array $fields = []): mixed
    {
        $key = config()->string('services.mailgun.api_key', '');
        $request = Http::withBasicAuth('api', $key)->acceptJson()->timeout(self::TIMEOUT);

        try {
            $response = $this->send($request, $method, rtrim(config()->string('services.mailgun.endpoint'), '/').$path, $fields);
        } catch (ConnectionException) {
            // the original message could carry the address with the credentials
            throw new MailgunRouteException('Mailgun does not answer: try again in a moment.');
        }

        $body = $response->json();
        $message = is_array($body) && is_string($body['message'] ?? null) && $body['message'] !== ''
            ? str_replace($key, '…', $body['message']) : null;

        if (in_array($response->status(), [401, 403], true)) {
            // the usual reason, and Mailgun's own words for the others
            throw new MailgunRouteException(
                'Mailgun refuses the API key: it needs an account key, not a sending key.'.($message !== null ? ' Mailgun says: '.$message : ''),
            );
        }

        if (! $response->successful()) {
            throw new MailgunRouteException("Mailgun did not {$did}: ".($message ?? 'answer '.$response->status()));
        }

        return $body;
    }

    /**
     * Sends the request; a write goes as a form.
     *
     * @param  array<string, string>  $fields
     *
     * @throws ConnectionException When Mailgun does not answer.
     */
    private function send(PendingRequest $request, string $method, string $url, array $fields): Response
    {
        return match ($method) {
            'post' => $request->asForm()->post($url, $fields),
            'put' => $request->asForm()->put($url, $fields),
            default => $request->get($url),
        };
    }
}
