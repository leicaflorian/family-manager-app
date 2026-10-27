<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\User;
use App\Support\CurrentFamily;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->marco = iscriviFamiglia();
    $this->actingAs($this->marco);
    $this->casa = DocumentCategory::query()->where('name', 'Casa e utenze')->sole();
    $this->luce = DocumentCategory::query()->where('name', 'Luce')->sole();
});

test('la pagina mostra l\'albero, con il numero di documenti di ogni categoria', function (): void {
    Document::factory()->for($this->marco->family)->count(2)->create(['category_id' => $this->luce->id]);

    $this->get(route('categorie.index'))->assertInertia(fn (AssertableInertia $pagina) => $pagina
        ->component('impostazioni/Categorie')
        ->has('categorie', 10)
        ->where('categorie.1.name', 'Casa e utenze')
        ->where('categorie.1.color', 'casa')
        ->where('categorie.1.icon', 'house')
        ->where('categorie.1.figlie.0.name', 'Luce')
        ->where('categorie.1.figlie.0.documenti', 2)
        ->where('puoModificare', true));
});

test('si aggiunge una categoria, si rinomina e si sposta tra le principali', function (): void {
    $this->post(route('categorie.store'), ['name' => 'Condominio', 'parent_id' => $this->casa->id])
        ->assertSessionHasNoErrors()->assertRedirect(route('categorie.index'));
    $condominio = DocumentCategory::query()->where('name', 'Condominio')->sole();
    expect($condominio->parent_id)->toBe($this->casa->id);

    $this->put(route('categorie.update', $condominio), ['name' => 'Spese condominiali', 'parent_id' => null])
        ->assertSessionHasNoErrors();

    expect($condominio->fresh())->name->toBe('Spese condominiali')->parent_id->toBeNull();
});

test('una categoria con documenti non si elimina, una vuota sì', function (): void {
    Document::factory()->for($this->marco->family)->create(['category_id' => $this->luce->id]);
    $tari = DocumentCategory::query()->where('name', 'TARI')->sole();

    $this->delete(route('categorie.destroy', $this->luce))->assertSessionHasErrors('categoria');
    $this->delete(route('categorie.destroy', $tari))->assertSessionHasNoErrors();

    expect($this->luce->fresh())->not->toBeNull()
        ->and($tari->fresh())->toBeNull();
});

test('una categoria con sottocategorie non si elimina', function (): void {
    $this->delete(route('categorie.destroy', $this->casa))->assertSessionHasErrors('categoria');

    expect($this->casa->fresh())->not->toBeNull();
});

test('una categoria non può stare sotto sé stessa o sotto una sua figlia', function (): void {
    $bolletta = DocumentCategory::query()->create(['name' => 'Bollette vecchie', 'parent_id' => $this->luce->id]);

    $this->put(route('categorie.update', $this->casa), ['name' => 'Casa e utenze', 'parent_id' => $this->casa->id])
        ->assertSessionHasErrors('parent_id');
    $this->put(route('categorie.update', $this->casa), ['name' => 'Casa e utenze', 'parent_id' => $bolletta->id])
        ->assertSessionHasErrors('parent_id');

    expect($this->casa->fresh()?->parent_id)->toBeNull();
});

test('una categoria di un\'altra famiglia non fa da principale', function (): void {
    $rossi = iscriviFamiglia('Rossi', 'anna@example.com');
    $altra = CurrentFamily::per($rossi->family_id, fn () => DocumentCategory::query()->where('name', 'Lavoro')->sole());

    $this->post(route('categorie.store'), ['name' => 'Prova', 'parent_id' => $altra->id])
        ->assertSessionHasErrors('parent_id');
});

test('un member vede le categorie ma non le modifica', function (): void {
    $laura = User::factory()->for($this->marco->family)->create();
    $this->actingAs($laura);

    $this->get(route('categorie.index'))->assertInertia(fn (AssertableInertia $pagina) => $pagina
        ->where('puoModificare', false));
    $this->post(route('categorie.store'), ['name' => 'Prova'])->assertForbidden();
    $this->put(route('categorie.update', $this->luce), ['name' => 'Corrente'])->assertForbidden();
    $this->delete(route('categorie.destroy', DocumentCategory::query()->where('name', 'TARI')->sole()))->assertForbidden();

    expect($this->luce->fresh()?->name)->toBe('Luce')
        ->and(DocumentCategory::query()->where('name', 'TARI')->exists())->toBeTrue();
});
