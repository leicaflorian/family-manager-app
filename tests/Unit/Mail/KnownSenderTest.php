<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Support\Mail\KnownSender;

/**
 * An organization with the two fields the sender is matched on.
 */
function senderOrganization(?string $email, ?string $website, string $name = 'Org'): Organization
{
    return (new Organization)->forceFill(['name' => $name, 'email' => $email, 'website' => $website]);
}

describe('baseDomain', function (): void {
    test('takes the last two labels of a URL, without scheme, www, path and port', function (): void {
        expect(KnownSender::baseDomain('https://www.edison.it/casa'))->toBe('edison.it')
            ->and(KnownSender::baseDomain('HTTP://Clienti.Enel.it:8080/x?y=1'))->toBe('enel.it');
    });

    test('takes the last two labels of a host', function (): void {
        expect(KnownSender::baseDomain('mail.enel.it'))->toBe('enel.it');
    });

    test('is null when there is no domain', function (): void {
        expect(KnownSender::baseDomain(''))->toBeNull()
            ->and(KnownSender::baseDomain('localhost'))->toBeNull();
    });
});

describe('organizationOf', function (): void {
    test('matches the domain of the website', function (): void {
        $edison = senderOrganization(null, 'https://www.edison.it/casa');

        expect(KnownSender::organizationOf('noreply@edison.it', [$edison]))->toBe($edison);
    });

    test('matches the domain of the email, also from a subdomain', function (): void {
        $enel = senderOrganization('clienti@enel.it', null);

        expect(KnownSender::organizationOf('fatture@mail.enel.it', [$enel]))->toBe($enel);
    });

    test('takes the first organization that matches', function (): void {
        $first = senderOrganization('a@enel.it', null, 'First');
        $second = senderOrganization(null, 'enel.it', 'Second');

        expect(KnownSender::organizationOf('x@enel.it', [senderOrganization('a@edison.it', null), $first, $second]))->toBe($first);
    });

    test('a generic domain never matches', function (): void {
        expect(KnownSender::GENERIC_DOMAINS)->toHaveCount(18)->toContain('gmail.com')
            ->and(KnownSender::organizationOf('mario.rossi@gmail.com', [senderOrganization('mario.rossi@gmail.com', null)]))->toBeNull();
    });

    test('an unknown sender does not match', function (): void {
        expect(KnownSender::organizationOf('news@zalando.it', []))->toBeNull()
            ->and(KnownSender::organizationOf('news@zalando.it', [senderOrganization('a@enel.it', null)]))->toBeNull();
    });

    test('a sender without @ does not match', function (): void {
        expect(KnownSender::organizationOf('enel', [senderOrganization('a@enel.it', 'enel.it')]))->toBeNull();
    });
});
