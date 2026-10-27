<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\User;
use App\Support\CurrentFamily;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $this->home = DocumentCategory::query()->where('name', 'Home and utilities')->sole();
    $this->electricity = DocumentCategory::query()->where('name', 'Electricity')->sole();
});

test('the page shows the tree, with the number of documents of each category', function (): void {
    Document::factory()->for($this->marco->family)->count(2)->create(['category_id' => $this->electricity->id]);

    $this->get(route('categories.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/Categories')
        ->has('categories', 10)
        ->where('categories.1.name', 'Home and utilities')
        ->where('categories.1.color', 'home')
        ->where('categories.1.icon', 'house')
        ->where('categories.1.children.0.name', 'Electricity')
        ->where('categories.1.children.0.documents_count', 2)
        ->where('canEdit', true));
});

test('a category is added, renamed and moved among the main ones', function (): void {
    $this->post(route('categories.store'), ['name' => 'Condominium', 'parent_id' => $this->home->id])
        ->assertSessionHasNoErrors()->assertRedirect(route('categories.index'));
    $condominium = DocumentCategory::query()->where('name', 'Condominium')->sole();
    expect($condominium->parent_id)->toBe($this->home->id);

    $this->put(route('categories.update', $condominium), ['name' => 'Condominium fees', 'parent_id' => null])
        ->assertSessionHasNoErrors();

    expect($condominium->fresh())->name->toBe('Condominium fees')->parent_id->toBeNull();
});

test('a category with documents is not deleted, an empty one is', function (): void {
    Document::factory()->for($this->marco->family)->create(['category_id' => $this->electricity->id]);
    $wasteTax = DocumentCategory::query()->where('name', 'Waste tax (TARI)')->sole();

    $this->delete(route('categories.destroy', $this->electricity))
        ->assertSessionHasErrors(['category' => 'There is 1 document in this category: move it to another one first.']);
    $this->delete(route('categories.destroy', $wasteTax))->assertSessionHasNoErrors();

    expect($this->electricity->fresh())->not->toBeNull()
        ->and($wasteTax->fresh())->toBeNull();
});

test('a category with subcategories is not deleted', function (): void {
    $this->delete(route('categories.destroy', $this->home))->assertSessionHasErrors('category');

    expect($this->home->fresh())->not->toBeNull();
});

test('a category cannot sit under itself or under one of its children', function (): void {
    $oldBills = DocumentCategory::query()->create(['name' => 'Old bills', 'parent_id' => $this->electricity->id]);

    $this->put(route('categories.update', $this->home), ['name' => 'Home and utilities', 'parent_id' => $this->home->id])
        ->assertSessionHasErrors('parent_id');
    $this->put(route('categories.update', $this->home), ['name' => 'Home and utilities', 'parent_id' => $oldBills->id])
        ->assertSessionHasErrors('parent_id');

    expect($this->home->fresh()?->parent_id)->toBeNull();
});

test('a category of another family is not a parent', function (): void {
    $rossi = registerFamily('Rossi', 'anna@example.com');
    $other = CurrentFamily::using($rossi->family_id, fn () => DocumentCategory::query()->where('name', 'Work')->sole());

    $this->post(route('categories.store'), ['name' => 'Test', 'parent_id' => $other->id])
        ->assertSessionHasErrors('parent_id');
});

test('a member sees the categories but does not change them', function (): void {
    $laura = User::factory()->for($this->marco->family)->create();
    $this->actingAs($laura);
    $wasteTax = DocumentCategory::query()->where('name', 'Waste tax (TARI)')->sole();

    $this->get(route('categories.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('canEdit', false));
    $this->post(route('categories.store'), ['name' => 'Test'])->assertForbidden();
    $this->put(route('categories.update', $this->electricity), ['name' => 'Power'])->assertForbidden();
    $this->delete(route('categories.destroy', $wasteTax))->assertForbidden();

    expect($this->electricity->fresh()?->name)->toBe('Electricity')
        ->and($wasteTax->fresh())->not->toBeNull();
});
