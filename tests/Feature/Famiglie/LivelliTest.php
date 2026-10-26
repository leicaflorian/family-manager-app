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
    $this->famiglia = Family::factory()->create();
    $this->admin = User::factory()->for($this->famiglia)->admin()->create();
    $this->member = User::factory()->for($this->famiglia)->create();
    $this->actingAs($this->admin);
    $this->documento = Document::factory()->for($this->famiglia)->create();
    $this->persona = Person::factory()->for($this->famiglia)->create();
});

test('un member vede tutto, ma non elimina un documento e non modifica una persona', function (): void {
    expect($this->member->can('viewAny', Document::class))->toBeTrue()
        ->and($this->member->can('view', $this->documento))->toBeTrue()
        ->and($this->member->can('view', $this->persona))->toBeTrue()
        ->and($this->member->can('delete', $this->documento))->toBeFalse()
        ->and($this->member->can('update', $this->persona))->toBeFalse()
        ->and($this->member->can('delete', $this->persona))->toBeFalse();
});

test('un member carica un documento e lo modifica', function (): void {
    expect($this->member->can('create', Document::class))->toBeTrue()
        ->and($this->member->can('update', $this->documento))->toBeTrue();
});

test('un member non tocca enti, categorie, schede e inviti', function (string $modello): void {
    expect($this->member->can('create', $modello))->toBeFalse();
})->with([Organization::class, DocumentCategory::class, Vehicle::class, Person::class, Invitation::class]);

test('un admin può tutto', function (): void {
    expect($this->admin->can('delete', $this->documento))->toBeTrue()
        ->and($this->admin->can('update', $this->persona))->toBeTrue()
        ->and($this->admin->can('create', Invitation::class))->toBeTrue()
        ->and($this->admin->can('delete', $this->persona))->toBeTrue();
});

test('un member non apre la pagina degli inviti', function (): void {
    $this->actingAs($this->member)->get(route('famiglia.invita'))->assertForbidden();
});
