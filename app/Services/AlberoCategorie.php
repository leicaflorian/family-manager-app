<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CategoriaData;
use App\Models\DocumentCategory;
use Illuminate\Support\Collection;

/**
 * L'albero delle categorie dei documenti della famiglia corrente.
 *
 * Le categorie di una famiglia sono poche decine: si leggono tutte con una query
 * e l'albero si costruisce in memoria.
 */
final class AlberoCategorie
{
    /**
     * L'albero, dalle principali, con il numero di documenti di ognuna.
     *
     * @return list<CategoriaData>
     */
    public function albero(): array
    {
        $categorie = DocumentCategory::query()->withCount('documents')->orderBy('sort')->orderBy('name')->get();

        return $this->rami($categorie->groupBy('parent_id'), null, null);
    }

    /**
     * Gli id di una categoria e di tutte quelle che stanno sotto, a ogni livello.
     *
     * @param  int  $id  la categoria da cui partire
     * @return list<int>
     */
    public function conDiscendenti(int $id): array
    {
        $perPadre = DocumentCategory::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $trovate = [$id];

        // Una visita in ampiezza: ogni categoria trovata aggiunge le sue figlie in coda.
        for ($i = 0; $i < count($trovate); $i++) {
            foreach ($perPadre->get($trovate[$i], collect()) as $figlia) {
                $trovate[] = $figlia->id;
            }
        }

        return $trovate;
    }

    /**
     * Le categorie che stanno sotto `$padre`, ognuna con le sue figlie.
     *
     * @param  Collection<int|string, Collection<int, DocumentCategory>>  $perPadre  le categorie divise per `parent_id`
     * @param  int|null  $padre  la categoria di cui leggere le figlie, null per le principali
     * @param  string|null  $colore  il colore della principale, per le sottocategorie
     * @return list<CategoriaData>
     */
    private function rami(Collection $perPadre, ?int $padre, ?string $colore): array
    {
        return array_values($perPadre->get($padre ?? '', collect())->map(fn (DocumentCategory $categoria): CategoriaData => new CategoriaData(
            id: $categoria->id,
            name: $categoria->name,
            parent_id: $categoria->parent_id,
            icon: $categoria->icon,
            color: $categoria->color ?? $colore,
            documenti: (int) $categoria->documents_count,
            figlie: $this->rami($perPadre, $categoria->id, $categoria->color ?? $colore),
        ))->all());
    }
}
