<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Document;
use App\Models\Employment;
use App\Models\Person;
use App\Models\User;

beforeEach(function (): void {
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $this->pinFamily = $this->marco->family;
    $this->pinPerson = Person::factory()->for($this->pinFamily)->create();
    $this->pinDocument = Document::factory()->for($this->pinFamily)->create(['person_id' => $this->pinPerson->id]);
});

it('pins a personal document and remembers who and when', function (): void {
    $this->from('/back')->put(route('documents.pin', $this->pinDocument))->assertRedirect('/back');

    $document = $this->pinDocument->fresh();
    expect($document->pinned_at)->not->toBeNull()
        ->and($document->pinned_by)->toBe($this->marco->id);
});

it('keeps the first pinned_at when pinned again', function (): void {
    $this->travelTo(now()->startOfSecond());
    $this->put(route('documents.pin', $this->pinDocument));
    $first = $this->pinDocument->fresh()->pinned_at;

    $this->travel(2)->hours();
    $this->put(route('documents.pin', $this->pinDocument));

    expect($this->pinDocument->fresh()->pinned_at->equalTo($first))->toBeTrue();
});

it('unpins', function (): void {
    $this->put(route('documents.pin', $this->pinDocument));
    $this->delete(route('documents.unpin', $this->pinDocument))->assertRedirect();

    $document = $this->pinDocument->fresh();
    expect($document->pinned_at)->toBeNull()->and($document->pinned_by)->toBeNull();
});

it('lets a member pin and unpin', function (): void {
    $rosa = User::factory()->for($this->pinFamily)->create(['role' => Role::Member]);
    $this->actingAs($rosa);

    $this->put(route('documents.pin', $this->pinDocument))->assertRedirect();
    expect($this->pinDocument->fresh()->pinned_by)->toBe($rosa->id);
    $this->delete(route('documents.unpin', $this->pinDocument))->assertRedirect();
    expect($this->pinDocument->fresh()->pinned_at)->toBeNull();
});

it('refuses a document linked to a record or without a person', function (): void {
    $linked = Document::factory()->for($this->pinFamily)->create(['person_id' => $this->pinPerson->id]);
    $linked->employments()->attach(Employment::factory()->for($this->pinFamily)->create());
    $nobody = Document::factory()->for($this->pinFamily)->create(['person_id' => null]);

    foreach ([$linked, $nobody] as $document) {
        $this->put(route('documents.pin', $document))->assertSessionHasErrors('document');
        expect($document->fresh()->pinned_at)->toBeNull();
    }
});

it('answers 404 for a document of another family', function (): void {
    $other = registerFamily('Rossi', 'anna@example.com');
    $document = Document::factory()->for($other->family)->create(['person_id' => Person::factory()->for($other->family)->create()->id]);

    $this->put(route('documents.pin', $document))->assertNotFound();
    $this->delete(route('documents.unpin', $document))->assertNotFound();
});

it('clears the pin when the document changes person', function (): void {
    $this->put(route('documents.pin', $this->pinDocument));
    $other = Person::factory()->for($this->pinFamily)->create();

    $this->pinDocument->fresh()->update(['person_id' => $other->id]);

    $document = $this->pinDocument->fresh();
    expect($document->pinned_at)->toBeNull()->and($document->pinned_by)->toBeNull();
});
