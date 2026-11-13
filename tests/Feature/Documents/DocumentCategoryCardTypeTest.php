<?php

declare(strict_types=1);

use App\Models\DocumentCategory;
use App\Models\Family;
use App\Services\CategoryTree;
use App\Support\CurrentFamily;

it('gives the card of the category, else the one of the main category', function (): void {
    insideFamilyOf(registerFamily(), function (): void {
        $main = DocumentCategory::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Cars', 'card_type' => 'vehicle']);
        $plain = DocumentCategory::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Tyres', 'parent_id' => $main->id]);
        $own = DocumentCategory::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Policies', 'parent_id' => $main->id, 'card_type' => 'insurance_policy']);
        $none = DocumentCategory::factory()->create(['family_id' => CurrentFamily::id(), 'name' => 'Misc']);

        expect(DocumentCategory::cardTypeOf($main->id))->toBe('vehicle')
            ->and(DocumentCategory::cardTypeOf($plain->id))->toBe('vehicle')
            ->and(DocumentCategory::cardTypeOf($own->id))->toBe('insurance_policy')
            ->and(DocumentCategory::cardTypeOf($none->id))->toBeNull()
            ->and(DocumentCategory::cardTypeOf(null))->toBeNull()
            ->and(DocumentCategory::cardTypeOf(999999))->toBeNull();
    });
});

it('gives null for the category of another family', function (): void {
    $other = Family::factory()->create();
    $foreign = DocumentCategory::factory()->create(['family_id' => $other->id, 'card_type' => 'vehicle']);

    insideFamilyOf(registerFamily(), function () use ($foreign): void {
        expect(DocumentCategory::cardTypeOf($foreign->id))->toBeNull();
    });
});

it('starts a family with the card of each starter category', function (): void {
    $types = insideFamilyOf(registerFamily(), fn (): array => DocumentCategory::query()->pluck('card_type', 'name')->all());

    expect($types['Home and utilities'])->toBe('utility')
        ->and($types['Vehicles'])->toBe('vehicle')
        ->and($types['Warranties and receipts'])->toBe('belonging')
        ->and($types['Subscriptions'])->toBe('subscription')
        ->and($types['Insurance and funds'])->toBe('insurance_policy')
        ->and($types['Payslips'])->toBe('employment')
        ->and($types['Health'])->toBe('health_event')
        ->and($types['School and activities'])->toBe('enrollment')
        ->and($types['Banks and loans'])->toBeNull()
        ->and($types['Personal records and paperwork'])->toBeNull()
        ->and($types['Taxes and social security'])->toBeNull()
        ->and($types['Electricity'])->toBeNull();
});

it('sends the own card and the inherited one with the tree', function (): void {
    $tree = insideFamilyOf(registerFamily(), fn (): array => resolve(CategoryTree::class)->tree());
    $purchases = collect($tree)->firstWhere('name', 'Purchases, warranties and subscriptions');
    $home = collect($tree)->firstWhere('name', 'Home and utilities');
    $warranties = collect($purchases->children)->firstWhere('name', 'Warranties and receipts');
    $gas = collect($home->children)->firstWhere('name', 'Gas');

    expect($home->card_type)->toBe('utility')
        ->and($home->inherited_card_type)->toBeNull()
        ->and($gas->card_type)->toBeNull()
        ->and($gas->inherited_card_type)->toBe('utility')
        ->and($purchases->card_type)->toBeNull()
        ->and($warranties->card_type)->toBe('belonging')
        ->and($warranties->inherited_card_type)->toBeNull();
});
