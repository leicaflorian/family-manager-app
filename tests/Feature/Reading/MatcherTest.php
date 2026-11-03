<?php

declare(strict_types=1);

use App\Enums\EmploymentStatus;
use App\Enums\FinancialAccountStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UtilityStatus;
use App\Models\Employment;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Subscription;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Reading\Matcher;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->bianchi = Family::factory()->create(['name' => 'Bianchi']);
    $this->rossi = Family::factory()->create(['name' => 'Rossi']);
    CurrentFamily::set($this->bianchi->id);
    $this->matcher = new Matcher;
});

afterEach(fn () => CurrentFamily::clear());

describe('find by code', function (): void {
    test('findUtility: null codes give null without queries', function (): void {
        Utility::factory()->for(test()->bianchi)->create(['supply_point_code' => 'IT001E1']);

        DB::enableQueryLog();
        expect($this->matcher->utility(null, null))->toBeNull()
            ->and($this->matcher->utility('', ' '))->toBeNull()
            ->and(DB::getQueryLog())->toBe([]);
    });

    test('findUtility: finds the row by the POD written with spaces and dashes', function (): void {
        Utility::factory()->for(test()->bianchi)->create(['supply_point_code' => 'IT999E99999999']);
        $row = Utility::factory()->for(test()->bianchi)->create(['supply_point_code' => 'IT001E00000001']);

        expect($this->matcher->utility('it 001-e 00000001', null)?->id)->toBe($row->id);
    });

    test('findUtility: the code stored with spaces is found by the clean one', function (): void {
        $row = Utility::factory()->for(test()->bianchi)->create(['supply_point_code' => 'IT 001 E 00000001']);

        expect($this->matcher->utility('IT001E00000001', null)?->id)->toBe($row->id);
    });

    test('findUtility: falls back to the customer code', function (): void {
        $row = Utility::factory()->for(test()->bianchi)->create(['customer_code' => 'CL-12345', 'supply_point_code' => null]);

        expect($this->matcher->utility(null, 'cl 12345')?->id)->toBe($row->id)
            ->and($this->matcher->utility('IT000E00000000', 'cl 12345')?->id)->toBe($row->id);
    });

    test('findUtility: the POD comes before the customer code', function (): void {
        Utility::factory()->for(test()->bianchi)->create(['customer_code' => 'CL-1', 'supply_point_code' => null]);
        $byPod = Utility::factory()->for(test()->bianchi)->create(['customer_code' => 'OTHER', 'supply_point_code' => 'IT001E1']);

        expect($this->matcher->utility('IT001E1', 'CL1')?->id)->toBe($byPod->id);
    });

    test('findUtility: a terminated utility is not found', function (): void {
        Utility::factory()->for(test()->bianchi)->create(['supply_point_code' => 'IT001E1', 'status' => UtilityStatus::Terminated]);

        expect($this->matcher->utility('IT001E1', null))->toBeNull();
    });

    test('findVehicle: null gives null without queries', function (): void {
        Vehicle::factory()->for(test()->bianchi)->create(['plate_number' => 'AA000AA']);

        DB::enableQueryLog();
        expect($this->matcher->vehicle(null))->toBeNull()
            ->and(DB::getQueryLog())->toBe([]);
    });

    test('findVehicle: finds the row by the plate written with spaces and dashes', function (): void {
        Vehicle::factory()->for(test()->bianchi)->create(['plate_number' => 'AA000AA']);
        $row = Vehicle::factory()->for(test()->bianchi)->create(['plate_number' => 'ZZ123ZZ']);

        expect($this->matcher->vehicle('zz-123 zz')?->id)->toBe($row->id);
    });

    test('findPolicy: null gives null without queries', function (): void {
        InsurancePolicy::factory()->for(test()->bianchi)->create(['policy_number' => 'P1']);

        DB::enableQueryLog();
        expect($this->matcher->policy(null))->toBeNull()
            ->and(DB::getQueryLog())->toBe([]);
    });

    test('findPolicy: finds the row by the number written with spaces and dashes', function (): void {
        $row = InsurancePolicy::factory()->for(test()->bianchi)->create(['policy_number' => 'POL12345']);

        expect($this->matcher->policy('pol-123 45')?->id)->toBe($row->id);
    });

    test('findPersonByTaxCode: null gives null without queries', function (): void {
        Person::factory()->for(test()->bianchi)->create(['tax_code' => 'RSSMRA80A01H501U']);

        DB::enableQueryLog();
        expect($this->matcher->personByTaxCode(null))->toBeNull()
            ->and(DB::getQueryLog())->toBe([]);
    });

    test('findPersonByTaxCode: finds the row by the tax code written with spaces', function (): void {
        $row = Person::factory()->for(test()->bianchi)->create(['tax_code' => 'RSSMRA80A01H501U']);

        expect($this->matcher->personByTaxCode('rssmra80 a01 h501u')?->id)->toBe($row->id);
    });

    test('another family\'s POD links nothing', function (): void {
        $mine = Utility::factory()->for(test()->bianchi)->create(['supply_point_code' => 'IT 001 E 12345678']);
        CurrentFamily::using($this->rossi->id, fn () => Utility::factory()->for(test()->rossi)->create(['supply_point_code' => 'IT001E99999999']));

        expect($this->matcher->utility('IT001E12345678', null)?->id)->toBe($mine->id)
            ->and($this->matcher->utility('IT001E99999999', null))->toBeNull();

        CurrentFamily::set($this->rossi->id);
        expect($this->matcher->utility('IT001E12345678', null))->toBeNull();
    });
});

describe('findAccount: the rows after the query', function (): void {
    test('two accounts with the same last four digits: null', function (): void {
        FinancialAccount::factory()->for(test()->bianchi)->count(2)->create(['last_four_digits' => '1234']);

        expect($this->matcher->account('1234'))->toBeNull();
    });

    test('no account: null; fewer than four digits: null without a query', function (): void {
        expect($this->matcher->account('1234'))->toBeNull();

        DB::enableQueryLog();
        expect($this->matcher->account('123'))->toBeNull()
            ->and($this->matcher->account(null))->toBeNull()
            ->and(DB::getQueryLog())->toBe([]);
    });

    test('only the last four digits of a longer string are used', function (): void {
        $row = FinancialAccount::factory()->for(test()->bianchi)->create(['last_four_digits' => '5678']);

        expect($this->matcher->account('IT00 0000 5678')?->id)->toBe($row->id);
    });

    test('digits cleaned of spaces and dashes', function (): void {
        $row = FinancialAccount::factory()->for(test()->bianchi)->create(['last_four_digits' => '1234']);

        expect($this->matcher->account('**** 12-34')?->id)->toBe($row->id);
    });

    test('a closed account is not found', function (): void {
        FinancialAccount::factory()->for(test()->bianchi)->create(['last_four_digits' => '1234', 'status' => FinancialAccountStatus::Closed]);

        expect($this->matcher->account('1234'))->toBeNull();
    });

    test('the default account is the active one marked as default', function (): void {
        FinancialAccount::factory()->for(test()->bianchi)->create(['is_default' => false]);
        FinancialAccount::factory()->for(test()->bianchi)->create(['is_default' => true, 'status' => FinancialAccountStatus::Closed]);
        expect($this->matcher->defaultAccount())->toBeNull();

        $row = FinancialAccount::factory()->for(test()->bianchi)->create(['is_default' => true]);
        expect($this->matcher->defaultAccount()?->id)->toBe($row->id);
    });
});

describe('findEmployment', function (): void {
    /** Employment of the current family with the given dates. */
    function job(string $status, ?string $start, ?string $end, ?Person $person = null, ?Organization $organization = null): Employment
    {
        return Employment::factory()->for(test()->bianchi)->create([
            'person_id' => ($person ?? test()->person)->id,
            'organization_id' => $organization?->id,
            'status' => $status === 'active' ? EmploymentStatus::Active : EmploymentStatus::Ended,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    beforeEach(function (): void {
        $this->person = Person::factory()->for(test()->bianchi)->create();
        $this->employer = Organization::factory()->for(test()->bianchi)->create();
    });

    test('neither person nor organization: null without a query', function (): void {
        job('active', null, null);

        DB::enableQueryLog();
        expect($this->matcher->employment(null, null, CarbonImmutable::parse('2026-03-15')))->toBeNull()
            ->and(DB::getQueryLog())->toBe([]);
    });

    test('the filter holds only what is known', function (): void {
        $other = Person::factory()->for(test()->bianchi)->create();
        $a = job('active', null, null, $this->person, $this->employer);
        $b = job('active', null, null, $other, Organization::factory()->for(test()->bianchi)->create());

        expect($this->matcher->employment($this->person->id, null, null)?->id)->toBe($a->id)
            ->and($this->matcher->employment(null, $this->employer->id, null)?->id)->toBe($a->id)
            ->and($this->matcher->employment($other->id, $b->organization_id, null)?->id)->toBe($b->id)
            ->and($this->matcher->employment($this->person->id, $b->organization_id, null))->toBeNull();
    });

    test('no rows: null', function (): void {
        expect($this->matcher->employment($this->person->id, $this->employer->id, CarbonImmutable::parse('2026-03-15')))->toBeNull();
    });

    test('a single employment is returned even if ended before the date: a paper can arrive after the job', function (): void {
        $row = job('ended', '2010-01-01', '2015-01-01');

        expect($this->matcher->employment($this->person->id, null, CarbonImmutable::parse('2026-03-15'))?->id)->toBe($row->id);
    });

    test('several: the one in progress on the date', function (): void {
        $first = job('ended', '2020-01-01', '2022-12-31');
        $second = job('active', '2023-01-01', null);

        expect($this->matcher->employment($this->person->id, null, CarbonImmutable::parse('2021-06-01'))?->id)->toBe($first->id)
            ->and($this->matcher->employment($this->person->id, null, CarbonImmutable::parse('2024-05-01'))?->id)->toBe($second->id);
    });

    test('the start and end days are inclusive and a missing date is open', function (): void {
        $first = job('ended', null, '2022-12-31');
        $second = job('ended', '2023-01-01', null);

        expect($this->matcher->employment($this->person->id, null, CarbonImmutable::parse('2022-12-31'))?->id)->toBe($first->id)
            ->and($this->matcher->employment($this->person->id, null, CarbonImmutable::parse('2023-01-01'))?->id)->toBe($second->id);
    });

    test('several in progress: the active one, a not active one is not chosen', function (): void {
        job('ended', '2020-01-01', null);
        $active = job('active', '2021-01-01', null);

        expect($this->matcher->employment($this->person->id, null, CarbonImmutable::parse('2024-01-01'))?->id)->toBe($active->id);
    });

    test('several in progress and both active, or none active: null', function (): void {
        job('active', '2020-01-01', null);
        job('active', '2021-01-01', null);
        expect($this->matcher->employment($this->person->id, null, CarbonImmutable::parse('2024-01-01')))->toBeNull();

        $other = Person::factory()->for(test()->bianchi)->create();
        job('ended', '2020-01-01', null, $other);
        job('ended', '2021-01-01', null, $other);
        expect($this->matcher->employment($other->id, null, CarbonImmutable::parse('2024-01-01')))->toBeNull();
    });

    test('none in progress on the date, or no date: among all of them the active one', function (): void {
        job('ended', '2020-01-01', '2022-12-31');
        $active = job('active', '2023-01-01', null);

        expect($this->matcher->employment($this->person->id, null, CarbonImmutable::parse('2010-01-01'))?->id)->toBe($active->id)
            ->and($this->matcher->employment($this->person->id, null, null)?->id)->toBe($active->id);
    });
});

describe('findOrganizationByName', function (): void {
    test('finds the organization written another way', function (): void {
        Organization::factory()->for(test()->bianchi)->create(['name' => 'Enel']);
        $edison = Organization::factory()->for(test()->bianchi)->create(['name' => 'Edison Energia']);

        expect($this->matcher->organizationByName('EDISON ENERGIA S.p.A.')?->id)->toBe($edison->id);
    });

    test('none for a name nobody has, or no name', function (): void {
        Organization::factory()->for(test()->bianchi)->create(['name' => 'Enel']);

        expect($this->matcher->organizationByName('MediaWorld'))->toBeNull()
            ->and($this->matcher->organizationByName(null))->toBeNull()
            ->and($this->matcher->organizationByName('  '))->toBeNull();
    });

    test('an organization of another family is not found', function (): void {
        CurrentFamily::using($this->rossi->id, fn () => Organization::factory()->for(test()->rossi)->create(['name' => 'Enel']));

        expect($this->matcher->organizationByName('Enel'))->toBeNull();
    });
});

describe('subscription', function (): void {
    test('the only active subscription of the organization', function (): void {
        $netflix = Organization::factory()->for(test()->bianchi)->create();
        $row = Subscription::factory()->for(test()->bianchi)->create(['organization_id' => $netflix->id]);
        Subscription::factory()->for(test()->bianchi)->create(['organization_id' => $netflix->id, 'status' => SubscriptionStatus::Cancelled]);
        Subscription::factory()->for(test()->bianchi)->create(['organization_id' => Organization::factory()->for(test()->bianchi)->create()->id]);

        expect($this->matcher->subscription($netflix->id)?->id)->toBe($row->id);
    });

    test('several active ones, none or no organization: null', function (): void {
        $organization = Organization::factory()->for(test()->bianchi)->create();
        expect($this->matcher->subscription($organization->id))->toBeNull()
            ->and($this->matcher->subscription(null))->toBeNull();

        Subscription::factory()->for(test()->bianchi)->count(2)->create(['organization_id' => $organization->id]);
        expect($this->matcher->subscription($organization->id))->toBeNull();
    });
});
