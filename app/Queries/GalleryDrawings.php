<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;

/**
 * The drawings of the gallery, newest upload first: the one place that knows the gallery order.
 */
final readonly class GalleryDrawings
{
    public function __construct(private FilterDocuments $filter) {}

    /**
     * The documents of the gallery category with their file (a drawing without a file is not one), the most
     * recently uploaded file first, then the most recent document.
     *
     * @param  int|null  $person  only the drawings of this child
     * @param  list<string>  $with  more relations to load besides `file`
     * @return Builder<Document>
     */
    public function query(?int $person = null, array $with = []): Builder
    {
        return $this->filter->apply(Document::query()->select('documents.*'), ['gallery' => true, 'person' => $person])
            ->join('files', 'files.id', '=', 'documents.file_id')
            ->with(['file', ...$with])
            ->reorder()
            ->orderByDesc('files.created_at')
            ->orderByDesc('documents.id');
    }
}
