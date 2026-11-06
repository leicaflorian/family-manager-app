<?php

declare(strict_types=1);

use App\Data\Reading\PolicyGiverData;
use App\Enums\InsurancePolicyStatus;
use App\Models\Family;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Models\Person;
use App\Services\Reading\PolicyFromOtherFiles;
use App\Support\CurrentFamily;

beforeEach(function (): void {
    $this->bianchi = Family::factory()->create(['name' => 'Bianchi']);
    $this->rossi = Family::factory()->create(['name' => 'Rossi']);
    CurrentFamily::set($this->bianchi->id);
    $this->prima = Organization::factory()->for($this->bianchi)->create(['name' => 'Prima Assicurazioni']);
    $this->person = Person::factory()->for($this->bianchi)->create();
    $this->from = new PolicyFromOtherFiles;
    $this->need = [
        'organizationId' => $this->prima->id,
        'organizationName' => 'Prima Assicurazioni',
        'personId' => $this->person->id,
        'type' => 'car',
    ];
});

afterEach(fn () => CurrentFamily::clear());

/**
 * A policy of the Bianchi with Prima Assicurazioni.
 *
 * @param  array<string, mixed>  $attributes
 */
function registeredPolicy(array $attributes = []): InsurancePolicy
{
    return InsurancePolicy::factory()->for(test()->bianchi)->create($attributes + [
        'organization_id' => test()->prima->id,
        'person_id' => test()->person->id,
        'policy_number' => 'PRP730628468',
    ]);
}

describe('registered policies as givers', function (): void {
    test('a registered policy of the same company is taken when it is the only one', function (): void {
        $policy = registeredPolicy();
        registeredPolicy(['status' => InsurancePolicyStatus::Expired, 'policy_number' => 'OLD1']);
        registeredPolicy(['policy_number' => null]);

        $registered = $this->from->registered();
        $chosen = $this->from->chosen($this->need, $registered, 'auto');

        expect($registered)->toHaveCount(1)
            ->and($chosen?->key)->toBe('policy:'.$policy->id)
            ->and($chosen?->number)->toBe('PRP730628468')
            ->and($chosen?->creates)->toBeFalse()
            ->and($chosen?->fileName)->toBeNull()
            ->and($chosen?->organizationName)->toBe('Prima Assicurazioni')
            ->and($chosen?->type)->toBe('car');
    });

    test('two registered policies that fit: none, and the proposal says so', function (): void {
        registeredPolicy();
        registeredPolicy(['policy_number' => 'PRP2']);

        $registered = $this->from->registered();

        expect($this->from->matching($this->need, $registered))->toHaveCount(2)
            ->and($this->from->chosen($this->need, $registered, 'auto'))->toBeNull();
    });

    test('a file of the same round wins over a registered policy', function (): void {
        registeredPolicy();
        $file = new PolicyGiverData(
            key: 'file:1',
            fileName: 'Contratto.pdf',
            number: 'PRP1',
            creates: true,
            organizationId: $this->prima->id,
            organizationName: 'Prima Assicurazioni',
            personId: $this->person->id,
            type: 'car',
        );

        expect($this->from->resolve($this->need, [$file], 'auto')?->key)->toBe('file:1')
            ->and($this->from->resolve($this->need, [], 'auto')?->key)->toStartWith('policy:')
            ->and($this->from->resolve($this->need, [$file], 'none'))->toBeNull();
    });

    test('a policy of another family is never a giver', function (): void {
        InsurancePolicy::factory()->for($this->rossi)->create([
            'organization_id' => Organization::factory()->for($this->rossi)->create(['name' => 'Prima Assicurazioni'])->id,
            'policy_number' => 'RSS1',
        ]);

        expect($this->from->registered())->toBe([]);
    });
});
