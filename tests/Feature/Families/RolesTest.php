<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Family;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Models\Vehicle;

beforeEach(function (): void {
    $this->family = Family::factory()->create();
    $this->admin = User::factory()->for($this->family)->admin()->create();
    $this->member = User::factory()->for($this->family)->create();
    $this->actingAs($this->admin);
    $this->document = Document::factory()->for($this->family)->create();
    $this->person = Person::factory()->for($this->family)->create();
});

test('a member sees everything, but does not delete a document nor edit a person', function (): void {
    expect($this->member->can('viewAny', Document::class))->toBeTrue()
        ->and($this->member->can('view', $this->document))->toBeTrue()
        ->and($this->member->can('view', $this->person))->toBeTrue()
        ->and($this->member->can('delete', $this->document))->toBeFalse()
        ->and($this->member->can('update', $this->person))->toBeFalse()
        ->and($this->member->can('delete', $this->person))->toBeFalse();
});

test('a member uploads a document and edits it', function (): void {
    expect($this->member->can('create', Document::class))->toBeTrue()
        ->and($this->member->can('update', $this->document))->toBeTrue();
});

test('a member does not touch organizations, categories, records and invitations', function (string $model): void {
    expect($this->member->can('create', $model))->toBeFalse();
})->with([Organization::class, DocumentCategory::class, Vehicle::class, Person::class, Invitation::class]);

test('an admin can do everything', function (): void {
    expect($this->admin->can('delete', $this->document))->toBeTrue()
        ->and($this->admin->can('update', $this->person))->toBeTrue()
        ->and($this->admin->can('create', Invitation::class))->toBeTrue()
        ->and($this->admin->can('delete', $this->person))->toBeTrue();
});

test('a member does not open the invitations page', function (): void {
    $this->actingAs($this->member)->get(route('family.invitations.create'))->assertForbidden();
});
