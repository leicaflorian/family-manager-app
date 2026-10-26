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
    $this->famiglia = Family::factory()->create();
    $this->actingAs(User::factory()->for($this->famiglia)->create());
});

test('un documento collegato a un\'utenza e a una spesa compare tra i documenti di tutte e due', function (): void {
    $documento = Document::factory()->for($this->famiglia)->create();
    $utenza = Utility::factory()->for($this->famiglia)->create();
    $spesa = Expense::factory()->for($this->famiglia)->create();

    $documento->utilities()->attach($utenza);
    $documento->expenses()->attach($spesa);

    expect($utenza->documents->pluck('id')->all())->toBe([$documento->id])
        ->and($spesa->documents->pluck('id')->all())->toBe([$documento->id])
        ->and(DB::table('documentables')->orderBy('documentable_type')->pluck('documentable_type')->all())
        ->toBe(['expense', 'utility'])
        ->and($documento->linkables()->map(fn ($scheda) => $scheda::class)->sort()->values()->all())
        ->toBe([Expense::class, Utility::class]);
});

test('eliminare il documento toglie i suoi collegamenti', function (): void {
    $documento = Document::factory()->for($this->famiglia)->create();
    $documento->utilities()->attach(Utility::factory()->for($this->famiglia)->create());

    $documento->delete();

    expect(DB::table('documentables')->count())->toBe(0);
});

test('OfFamily rifiuta l\'ente di un\'altra famiglia e accetta il proprio', function (): void {
    $nostro = Organization::factory()->for($this->famiglia)->create();
    $altrui = Organization::factory()->create();

    $valida = fn (int $id): bool => Validator::make(
        ['organization_id' => $id],
        ['organization_id' => [new OfFamily('organizations')]],
    )->passes();

    expect($valida($nostro->id))->toBeTrue()
        ->and($valida($altrui->id))->toBeFalse()
        ->and($valida(999999))->toBeFalse();
});

test('chi crea e chi modifica un dato resta scritto', function (): void {
    $autore = auth()->user();
    $ente = Organization::create(['name' => 'Hera']);

    $altro = User::factory()->for($this->famiglia)->create();
    $this->actingAs($altro);
    $ente->update(['name' => 'Hera Comm']);

    expect($ente->created_by)->toBe($autore?->getAuthIdentifier())
        ->and($ente->updated_by)->toBe($altro->id);
});
