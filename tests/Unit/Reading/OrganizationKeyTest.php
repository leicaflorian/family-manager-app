<?php

declare(strict_types=1);

use App\Support\Reading\OrganizationKey;

describe('organizationKey', function (): void {
    test('the legal form, the dots and the case do not count', function (): void {
        expect(OrganizationKey::of('Edison Energia S.p.A.'))->toBe(OrganizationKey::of('edison energia'))
            ->and(OrganizationKey::of('EDISON ENERGIA SPA'))->toBe(OrganizationKey::of('Edison  Energia'))
            ->and(OrganizationKey::of('Rossi Impianti s.r.l.'))->toBe(OrganizationKey::of('Rossi Impianti'));
    });

    test('a different name stays different', function (): void {
        expect(OrganizationKey::of('Idraulico Rossi'))->not->toBe(OrganizationKey::of('Rossi'));
    });

    test('a name that is only a legal form is kept', function (): void {
        expect(OrganizationKey::of('S.p.A.'))->toBe('spa')
            ->and(OrganizationKey::of(null))->toBe('');
    });
});
