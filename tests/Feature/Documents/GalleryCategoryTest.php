<?php

declare(strict_types=1);

use App\Models\DocumentCategory;
use App\Models\User;
use App\Services\CategoryTree;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\App;

beforeEach(function (): void {
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $this->school = DocumentCategory::query()->where('name', 'School and activities')->sole();
    $this->drawings = DocumentCategory::query()->where('name', 'Drawings and crafts')->sole();
});

it('marks a category as the gallery', function (): void {
    $this->put(route('categories.update', $this->school), ['name' => 'School and activities', 'gallery' => true])
        ->assertSessionHasNoErrors();

    expect($this->school->fresh()?->gallery)->toBeTrue();
});

it('keeps a single gallery per family', function (): void {
    $other = User::factory()->create();
    $otherGallery = CurrentFamily::using($other->family_id, fn (): DocumentCategory => DocumentCategory::query()->create(['name' => 'Other', 'gallery' => true]));

    $this->put(route('categories.update', $this->school), ['name' => 'School and activities', 'gallery' => true])->assertSessionHasNoErrors();
    $this->put(route('categories.update', $this->drawings), ['name' => 'Drawings and crafts', 'gallery' => true])->assertSessionHasNoErrors();

    expect($this->school->fresh()?->gallery)->toBeFalse()
        ->and($this->drawings->fresh()?->gallery)->toBeTrue()
        ->and(DocumentCategory::withoutGlobalScopes()->find($otherGallery->id)?->gallery)->toBeTrue();
});

it('leaves gallery as it is when the field is missing', function (): void {
    $this->put(route('categories.update', $this->drawings), ['name' => 'Drawings and crafts', 'parent_id' => $this->school->id])
        ->assertSessionHasNoErrors();

    expect($this->drawings->fresh()?->gallery)->toBeTrue();
});

it('lets only an admin change it', function (): void {
    $this->actingAs(User::factory()->for($this->marco->family)->create());

    $this->put(route('categories.update', $this->school), ['name' => 'School and activities', 'gallery' => true])->assertForbidden();

    expect($this->school->fresh()?->gallery)->toBeFalse();
});

it('galleryIds returns the category and its subcategories, and none without a gallery', function (): void {
    $child = DocumentCategory::query()->create(['name' => 'Paintings', 'parent_id' => $this->drawings->id]);
    $tree = new CategoryTree;

    expect($tree->galleryIds())->toEqualCanonicalizing([$this->drawings->id, $child->id])
        ->and($tree->galleryCategory()?->id)->toBe($this->drawings->id);

    DocumentCategory::query()->update(['gallery' => false]);

    expect($tree->galleryIds())->toBe([])
        ->and($tree->galleryCategory())->toBeNull();
});

it('gives a new family Drawings and crafts under School and activities, marked as the gallery', function (): void {
    expect($this->drawings->parent_id)->toBe($this->school->id)
        ->and($this->drawings->gallery)->toBeTrue()
        ->and($this->school->gallery)->toBeFalse();

    App::setLocale('it');
    $laura = registerFamily('Rossi', 'laura@example.com');

    $italian = CurrentFamily::using($laura->family_id, fn (): DocumentCategory => DocumentCategory::query()->where('gallery', true)->sole());
    expect($italian->name)->toBe('Disegni e lavoretti');
});
