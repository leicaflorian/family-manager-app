<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\Family;
use App\Models\Organization;
use App\Models\User;
use App\Support\SearchTerms;

test('every word must match a column or a column of a relation', function (): void {
    $family = Family::factory()->create();
    $this->actingAs(User::factory()->for($family)->create());
    $enel = Organization::factory()->for($family)->create(['name' => 'Enel Energia']);
    $other = Organization::factory()->for($family)->create(['name' => 'Comune']);
    $match = Document::factory()->for($family)->create(['title' => 'Bolletta luce', 'organization_id' => $enel->id]);
    Document::factory()->for($family)->create(['title' => 'Bolletta gas', 'organization_id' => $other->id]);
    Document::factory()->for($family)->create(['title' => 'Contratto', 'organization_id' => $enel->id]);

    $found = SearchTerms::fromQuery('bolletta enel')->whereWords(Document::query(), ['title', 'organization.name'])->pluck('id');

    expect($found->all())->toBe([$match->id]);
});

test('organizations are matched by name with the wildcards escaped', function (): void {
    $family = Family::factory()->create();
    $this->actingAs(User::factory()->for($family)->create());
    $a = Organization::factory()->for($family)->create(['name' => 'Sconto 50% casa']);
    Organization::factory()->for($family)->create(['name' => 'Sconto 500 casa']);

    $found = SearchTerms::fromQuery('50%')->whereWords(Organization::query(), ['name'])->pluck('id');

    expect($found->all())->toBe([$a->id]);
});

test('a search can add its own alternatives for each word', function (): void {
    $family = Family::factory()->create();
    $this->actingAs(User::factory()->for($family)->create());
    $a = Document::factory()->for($family)->create(['title' => 'Bolletta luce', 'notes' => 'zeta']);
    Document::factory()->for($family)->create(['title' => 'Bolletta gas', 'notes' => 'beta']);

    $found = SearchTerms::fromQuery('bolletta alfa')
        ->whereWords(Document::query(), ['title'], fn ($group, string $word) => $word === 'alfa' ? $group->orWhere('notes', 'zeta') : null)
        ->pluck('id');

    expect($found->all())->toBe([$a->id]);
});
