<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Una categoria dei documenti nell'albero, con le sue sottocategorie.
 */
#[TypeScript]
final class CategoriaData extends Data
{
    /**
     * @param  int  $id  l'id
     * @param  string  $name  il nome
     * @param  int|null  $parent_id  la categoria in cui sta, null per una principale
     * @param  string|null  $icon  l'icona, un nome di Lucide
     * @param  string|null  $color  il colore, un'area; una sottocategoria prende quello della principale
     * @param  int  $documenti  quanti documenti ci sono, senza quelli delle sottocategorie
     * @param  list<CategoriaData>  $figlie  le sottocategorie, nell'ordine
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?int $parent_id,
        public readonly ?string $icon,
        public readonly ?string $color,
        public readonly int $documenti,
        public readonly array $figlie,
    ) {}
}
