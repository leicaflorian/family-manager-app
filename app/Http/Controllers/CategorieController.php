<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Impostazioni\SalvaCategoriaRequest;
use App\Models\DocumentCategory;
use App\Services\AlberoCategorie;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\User;

/**
 * *Categorie dei documenti*: l'albero, e per un `admin` aggiungere, rinominare,
 * spostare ed eliminare.
 */
final class CategorieController extends Controller
{
    /**
     * L'albero con il numero di documenti di ogni categoria.
     */
    public function index(AlberoCategorie $albero, #[CurrentUser] User $utente): Response
    {
        return Inertia::render('impostazioni/Categorie', [
            'categorie' => $albero->albero(),
            'puoModificare' => $utente->can('create', DocumentCategory::class),
        ]);
    }

    /**
     * Una categoria nuova, in fondo a quelle che stanno nello stesso posto.
     */
    public function store(SalvaCategoriaRequest $request): RedirectResponse
    {
        $padre = $request->integer('parent_id') ?: null;

        DocumentCategory::query()->create([
            'name' => $request->string('name')->toString(),
            'parent_id' => $padre,
            'sort' => (int) DocumentCategory::query()->where('parent_id', $padre)->max('sort') + 1,
        ]);

        return to_route('categorie.index');
    }

    /**
     * Il nome nuovo, o il posto nuovo nell'albero.
     */
    public function update(SalvaCategoriaRequest $request, DocumentCategory $categoria): RedirectResponse
    {
        $categoria->update([
            'name' => $request->string('name')->toString(),
            'parent_id' => $request->integer('parent_id') ?: null,
        ]);

        return to_route('categorie.index');
    }

    /**
     * Elimina una categoria vuota: senza documenti e senza sottocategorie.
     *
     * @throws ValidationException se la categoria ha documenti o sottocategorie
     */
    public function destroy(DocumentCategory $categoria): RedirectResponse
    {
        Gate::authorize('delete', $categoria);
        $categoria->loadCount(['documents', 'children']);

        if ($categoria->documents_count > 0) {
            throw ValidationException::withMessages(['categoria' => trans_choice(
                'C\'è :count documento in questa categoria: spostalo prima in un\'altra.|Ci sono :count documenti in questa categoria: spostali prima in un\'altra.',
                (int) $categoria->documents_count,
            )]);
        }

        if ($categoria->children_count > 0) {
            throw ValidationException::withMessages(['categoria' => 'Questa categoria ha delle sottocategorie: spostale o eliminale prima.']);
        }

        $categoria->delete();

        return to_route('categorie.index');
    }
}
