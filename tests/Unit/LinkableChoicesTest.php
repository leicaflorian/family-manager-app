<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Enums\HomeStatus;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Utility;
use App\Support\Linkables;

test('the fixed choices have the labels of Directus, in their order', function (): void {
    expect(DocumentStatus::options())->toBe([
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'archived', 'label' => 'Archived'],
        ['value' => 'expired', 'label' => 'Expired'],
    ])
        ->and(OrganizationType::Healthcare->label())->toBe('Healthcare')
        ->and(Role::Member->label())->toBe('Sees everything and uploads documents')
        ->and(Role::Admin->title())->toBe('Administrator');
});

test('in Italian the labels agree with the gender of the record', function (): void {
    app()->setLocale('it');

    expect(DocumentStatus::Active->label())->toBe('Attivo')
        ->and(HomeStatus::Active->label())->toBe('Attiva')
        ->and(OrganizationType::Healthcare->label())->toBe('Sanità')
        ->and(Role::Member->description())->toBe('Paga, registra e carica, ma non cambia le impostazioni');
});

test('the polymorphic types are the singular name of the table', function (): void {
    expect(Linkables::typeOf(Utility::class))->toBe('utility')
        ->and(Linkables::typeOfCollection('utilities'))->toBe('utility')
        ->and(Linkables::typeOfCollection('maintenance_records'))->toBe('maintenance_record')
        ->and(Linkables::typeOfCollection('directus_users'))->toBeNull()
        ->and(array_keys(Linkables::WITH_DOCUMENTS))->toHaveCount(15);
});
