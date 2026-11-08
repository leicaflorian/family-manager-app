<?php

declare(strict_types=1);

use App\Actions\Families\CreateFamily;
use App\Actions\Mail\AssignMailAddress;
use App\Models\Family;
use App\Models\FamilySetting;
use App\Models\User;
use App\Support\CurrentFamily;

/**
 * The settings row of a family, read without the family scope.
 */
function settingsOf(Family $family): ?FamilySetting
{
    return FamilySetting::query()->withoutGlobalScopes()->where('family_id', $family->id)->first();
}

test('a new family gets its address at sign-up', function (): void {
    config(['services.mailgun.inbox_domain' => 'bollette.familymanager.test']);

    $admin = resolve(CreateFamily::class)->handle('De Luca', 'Anna', 'De Luca', 'anna@example.test', 'password-1234');

    expect(settingsOf($admin->family)?->mail_inbox_recipient)
        ->toMatch('/^deluca\.[a-hjkmnp-z2-9]{6}@bollette\.familymanager\.test$/');
});

test('without a domain no address is made', function (): void {
    config(['services.mailgun.inbox_domain' => null]);

    $admin = resolve(CreateFamily::class)->handle('De Luca', 'Anna', 'De Luca', 'anna@example.test', 'password-1234');

    expect(settingsOf($admin->family)?->mail_inbox_recipient)->toBeNull()
        ->and(resolve(AssignMailAddress::class)->handle($admin->family))->toBeNull();
});

test('a collision gets another address', function (): void {
    config(['services.mailgun.inbox_domain' => 'bollette.familymanager.test']);
    $taken = Family::factory()->create(['name' => 'Rossi']);
    FamilySetting::factory()->for($taken)->create(['mail_inbox_recipient' => 'rossi.aaaaaa@bollette.familymanager.test']);
    $family = Family::factory()->create(['name' => 'Rossi']);
    $made = ['rossi.aaaaaa@bollette.familymanager.test', 'rossi.aaaaaa@bollette.familymanager.test', 'rossi.bbbbbb@bollette.familymanager.test'];

    $address = new AssignMailAddress(function () use (&$made): string {
        return array_shift($made);
    })->handle($family);

    expect($address)->toBe('rossi.bbbbbb@bollette.familymanager.test')
        ->and(settingsOf($family)?->mail_inbox_recipient)->toBe($address);
});

test('it gives up after five collisions', function (): void {
    config(['services.mailgun.inbox_domain' => 'bollette.familymanager.test']);
    $taken = Family::factory()->create();
    FamilySetting::factory()->for($taken)->create(['mail_inbox_recipient' => 'rossi.aaaaaa@bollette.familymanager.test']);
    $family = Family::factory()->create(['name' => 'Rossi']);
    $tries = 0;

    $address = new AssignMailAddress(function () use (&$tries): string {
        $tries++;

        return 'rossi.aaaaaa@bollette.familymanager.test';
    })->handle($family);

    expect($address)->toBeNull()->and($tries)->toBe(5);
});

test('the address is written in lower case', function (): void {
    config(['services.mailgun.inbox_domain' => 'Bollette.FamilyManager.test']);
    $family = Family::factory()->create(['name' => 'Rossi']);

    $address = resolve(AssignMailAddress::class)->handle($family);

    expect($address)->toBe(strtolower((string) $address));
});

describe('mail-inbox:addresses', function (): void {
    test('gives the address only to the families that have none', function (): void {
        config(['services.mailgun.inbox_domain' => 'bollette.familymanager.test']);
        $without = Family::factory()->create(['name' => 'Verdi']);
        $noRow = Family::factory()->create(['name' => 'Neri']);
        $with = Family::factory()->create(['name' => 'Rossi']);
        FamilySetting::factory()->for($without)->create();
        FamilySetting::factory()->for($with)->create(['mail_inbox_recipient' => 'rossi.k7m2x9@bollette.familymanager.test']);

        $this->artisan('mail-inbox:addresses')->expectsOutput('Addresses made: 2')->assertSuccessful();

        expect(settingsOf($without)?->mail_inbox_recipient)->toStartWith('verdi.')
            ->and(settingsOf($noRow)?->mail_inbox_recipient)->toStartWith('neri.')
            ->and(settingsOf($with)?->mail_inbox_recipient)->toBe('rossi.k7m2x9@bollette.familymanager.test');
    });

    test('says so when there is no domain', function (): void {
        config(['services.mailgun.inbox_domain' => null]);
        Family::factory()->create();

        $this->artisan('mail-inbox:addresses')->expectsOutput('MAIL_INBOX_DOMAIN is empty: no address made.')->assertSuccessful();
    });
});

describe('findByMailRecipient', function (): void {
    beforeEach(function (): void {
        $this->family = Family::factory()->create();
        FamilySetting::factory()->for($this->family)->create(['mail_inbox_recipient' => 'bianchi.k7m2x9@bollette.familymanager.test']);
    });

    test('finds the address whatever the case and the spaces', function (): void {
        expect(FamilySetting::findByMailRecipient('BIANCHI.K7M2X9@bollette.familymanager.test')?->family_id)->toBe($this->family->id)
            ->and(FamilySetting::findByMailRecipient(' bianchi.k7m2x9@bollette.familymanager.test ')?->family_id)->toBe($this->family->id);
    });

    test('finds it without a current family', function (): void {
        CurrentFamily::using(Family::factory()->create()->id, function (): void {
            expect(FamilySetting::findByMailRecipient('bianchi.k7m2x9@bollette.familymanager.test'))->not->toBeNull();
        });
    });

    test('does not find a list of addresses or an old address', function (): void {
        expect(FamilySetting::findByMailRecipient('a@x.test, bianchi.k7m2x9@bollette.familymanager.test'))->toBeNull()
            ->and(FamilySetting::findByMailRecipient('bianchi.k7m2x9@bollette.familymanager.test, b@x.test'))->toBeNull()
            ->and(FamilySetting::findByMailRecipient('bianchi.old123@bollette.familymanager.test'))->toBeNull()
            ->and(FamilySetting::findByMailRecipient(''))->toBeNull();
    });
});

describe('notifyUsers', function (): void {
    test('with null gives all the accounts of the family', function (): void {
        $family = Family::factory()->create();
        User::factory()->count(2)->for($family)->create();
        User::factory()->for(Family::factory()->create())->create();
        $settings = FamilySetting::factory()->for($family)->create(['mail_inbox_notify' => null]);

        expect($settings->notifyUsers())->toHaveCount(2);
    });

    test('with ids gives only those accounts', function (): void {
        $family = Family::factory()->create();
        [$one, $two] = User::factory()->count(2)->for($family)->create();
        $other = User::factory()->for(Family::factory()->create())->create();
        $settings = FamilySetting::factory()->for($family)->create(['mail_inbox_notify' => [$one->id, $other->id]]);

        expect($settings->notifyUsers()->pluck('id')->all())->toBe([$one->id]);
    });
});
