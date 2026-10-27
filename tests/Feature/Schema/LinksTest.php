<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\Organization;
use App\Models\User;
use App\Models\Utility;
use App\Rules\OfFamily;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    $this->family = Family::factory()->create();
    $this->actingAs(User::factory()->for($this->family)->create());
});

test('a document linked to a utility and to an expense is among the documents of both', function (): void {
    $document = Document::factory()->for($this->family)->create();
    $utility = Utility::factory()->for($this->family)->create();
    $expense = Expense::factory()->for($this->family)->create();

    $document->utilities()->attach($utility);
    $document->expenses()->attach($expense);

    expect($utility->documents->pluck('id')->all())->toBe([$document->id])
        ->and($expense->documents->pluck('id')->all())->toBe([$document->id])
        ->and(DB::table('documentables')->orderBy('documentable_type')->pluck('documentable_type')->all())
        ->toBe(['expense', 'utility'])
        ->and($document->linkables()->map(fn ($record) => $record::class)->sort()->values()->all())
        ->toBe([Expense::class, Utility::class]);
});

test('deleting the document removes its links', function (): void {
    $document = Document::factory()->for($this->family)->create();
    $document->utilities()->attach(Utility::factory()->for($this->family)->create());

    $document->delete();

    expect(DB::table('documentables')->count())->toBe(0);
});

test('OfFamily rejects the organization of another family and accepts its own', function (): void {
    $ours = Organization::factory()->for($this->family)->create();
    $theirs = Organization::factory()->create();

    $passes = fn (int $id): bool => Validator::make(
        ['organization_id' => $id],
        ['organization_id' => [new OfFamily('organizations')]],
    )->passes();

    expect($passes($ours->id))->toBeTrue()
        ->and($passes($theirs->id))->toBeFalse()
        ->and($passes(999999))->toBeFalse();
});

test('who creates and who changes a row stays recorded', function (): void {
    $author = auth()->user();
    $organization = Organization::create(['name' => 'Hera']);

    $other = User::factory()->for($this->family)->create();
    $this->actingAs($other);
    $organization->update(['name' => 'Hera Comm']);

    expect($organization->created_by)->toBe($author?->getAuthIdentifier())
        ->and($organization->updated_by)->toBe($other->id);
});
