<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\Organization;

/**
 * Whether a mail comes from a sender the family already has among its organizations.
 */
final class KnownSender
{
    /** Domains shared by many people: they say nothing about who writes. */
    public const array GENERIC_DOMAINS = [
        'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'hotmail.it', 'live.com', 'live.it', 'icloud.com', 'me.com',
        'yahoo.com', 'yahoo.it', 'libero.it', 'virgilio.it', 'tiscali.it', 'alice.it', 'tim.it', 'fastwebnet.it', 'pec.it',
    ];

    /**
     * "https://www.edison.it/casa" gives "edison.it": lower case, without scheme, user, path, port and `www.`,
     * the last two labels.
     *
     * @param  string  $hostOrUrl  a host or a URL
     * @return string|null the base domain, or null when there is no domain
     */
    public static function baseDomain(string $hostOrUrl): ?string
    {
        $host = strtolower(trim($hostOrUrl));
        $host = (string) preg_replace('/^[a-z][a-z0-9+.-]*:\/\//', '', $host);
        $host = (string) preg_replace('/^[^\/?#@]*@/', '', $host);
        $host = strtok($host, '/?#') ?: '';
        $host = (string) preg_replace(['/:\d*$/', '/^www\./', '/\.$/'], '', $host);

        $labels = explode('.', $host);

        if (count($labels) < 2 || in_array('', $labels, true)) {
            return null;
        }

        return implode('.', array_slice($labels, -2));
    }

    /**
     * The first organization whose email or website has the domain of the sender; a generic domain never counts.
     *
     * @param  string  $senderAddress  the address of the sender
     * @param  iterable<Organization>  $organizations  the organizations of the family
     */
    public static function organizationOf(string $senderAddress, iterable $organizations): ?Organization
    {
        $at = strrpos($senderAddress, '@');
        $domain = $at === false ? null : self::baseDomain(substr($senderAddress, $at + 1));

        if ($domain === null || in_array($domain, self::GENERIC_DOMAINS, true)) {
            return null;
        }

        foreach ($organizations as $organization) {
            $email = $organization->email;
            $emailHost = $email !== null && str_contains($email, '@') ? substr($email, (int) strrpos($email, '@') + 1) : null;

            foreach ([$emailHost, $organization->website] as $value) {
                if ($value !== null && $value !== '' && self::baseDomain($value) === $domain) {
                    return $organization;
                }
            }
        }

        return null;
    }
}
