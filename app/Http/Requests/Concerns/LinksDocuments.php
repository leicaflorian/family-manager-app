<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * A form that links documents of the family to its record and unlinks them: the ids come in `link_documents` and
 * `unlink_documents`. A document of another family answers 404, before anything is saved.
 */
trait LinksDocuments
{
    /**
     * The rules of the two lists.
     *
     * @return array<string, list<string>>
     */
    protected function linkDocumentsRules(): array
    {
        return [
            'link_documents' => ['array'],
            'link_documents.*' => ['integer'],
            'unlink_documents' => ['array'],
            'unlink_documents.*' => ['integer'],
        ];
    }

    /**
     * The documents to link to the record.
     *
     * @return Collection<int, Document>
     *
     * @throws ModelNotFoundException if one is not of the family (it answers 404)
     */
    public function documentsToLink(): Collection
    {
        return $this->documents('link_documents');
    }

    /**
     * The documents to unlink from the record.
     *
     * @return Collection<int, Document>
     *
     * @throws ModelNotFoundException if one is not of the family (it answers 404)
     */
    public function documentsToUnlink(): Collection
    {
        return $this->documents('unlink_documents');
    }

    /**
     * The ids of a list of the form, as numbers.
     *
     * @param  string  $key  the list
     * @return list<int>
     */
    private function ids(string $key): array
    {
        return array_values($this->collect($key)->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->unique()->all());
    }

    /**
     * The documents of the family whose ids are in a list of the form.
     *
     * @param  string  $key  the list, `link_documents` or `unlink_documents`
     * @return Collection<int, Document>
     *
     * @throws ModelNotFoundException if one is not of the family (it answers 404)
     */
    private function documents(string $key): Collection
    {
        // The rule lets "5" through as well as 5
        return Document::query()->findOrFail($this->ids($key));
    }
}
