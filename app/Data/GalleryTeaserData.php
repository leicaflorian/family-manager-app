<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The card of the drawings gallery on *Documents*: how many drawings, whose, the last upload and the newest pictures.
 */
#[TypeScript]
final class GalleryTeaserData extends Data
{
    /**
     * @param  int  $count  the drawings in the gallery
     * @param  list<string>  $people  the first names of the children with a drawing, by name
     * @param  string|null  $last_uploaded_on  the day of the last upload in Rome, `yyyy-mm-dd`
     * @param  list<string>  $thumbnails  the addresses of the pictures of the four newest drawings
     * @param  string  $url  the address of the gallery
     */
    public function __construct(
        public readonly int $count,
        public readonly array $people,
        public readonly ?string $last_uploaded_on,
        public readonly array $thumbnails,
        public readonly string $url,
    ) {}
}
