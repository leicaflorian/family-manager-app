<?php

declare(strict_types=1);

use App\Data\OrganizationOptionData;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Services\DocumentFormOptions;
use App\Support\CurrentFamily;

it('gives each organization its types and its logo, for the mark in the field Who it is from', function (): void {
    [$edison, $organizations] = insideFamilyOf(registerFamily(), function (): array {
        $edison = Organization::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Edison', 'types' => [OrganizationType::UtilityProvider]]);

        return [$edison, resolve(DocumentFormOptions::class)->filters()['organizations']];
    });

    expect($organizations)->toHaveCount(1)
        ->and($organizations[0])->toBeInstanceOf(OrganizationOptionData::class)
        ->and($organizations[0]->toArray())->toBe([
            'value' => (string) $edison->id,
            'label' => 'Edison',
            'help' => null,
            'types' => ['utility_provider'],
            'logo' => null,
        ]);
});
