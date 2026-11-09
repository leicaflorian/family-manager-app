<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Data\Search\SearchResultData;
use App\Enums\DocumentStatus;
use App\Enums\SearchGroup;
use App\Models\Document;
use App\Support\SearchTerms;
use Illuminate\Database\Eloquent\Builder;

/**
 * The search over the documents of the family: by title, notes, tags, the summary the AI read from the file,
 * the organization and the person. A text that is a day (`15/10/2026`) also finds the documents of that day.
 */
final readonly class DocumentResults implements GroupResults
{
    /** The columns every word is looked up in; `tags` is a JSON column read as text. */
    private const array COLUMNS = ['title', 'notes', 'tags', 'organization.name', 'person.first_name', 'person.last_name'];

    /**
     * The summary of the reading as text: a JSON value has a binary collation, so it is converted to the
     * collation of the text columns, which ignores case and accents.
     */
    private const string SUMMARY_LIKE = "convert(json_unquote(json_extract(reading, '$.summary')) using utf8mb4) collate utf8mb4_unicode_ci like ?";

    /**
     * {@inheritDoc}
     */
    public function group(): SearchGroup
    {
        return SearchGroup::Documents;
    }

    /**
     * {@inheritDoc}
     */
    public function count(SearchTerms $terms): int
    {
        return $this->query($terms)->count();
    }

    /**
     * {@inheritDoc}
     *
     * Not archived documents come first, then the most recent, those without a date last.
     */
    public function results(SearchTerms $terms, int $limit): array
    {
        return $this->query($terms)
            ->with('organization')
            ->orderByRaw('status = ?', [DocumentStatus::Archived->value])
            ->orderByRaw('date is null')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(max(0, $limit))
            ->get()
            ->map(fn (Document $document): SearchResultData => $this->result($document))
            ->all();
    }

    /**
     * The result row of one document.
     *
     * @param  Document  $document  the document, with its organization loaded
     */
    private function result(Document $document): SearchResultData
    {
        return new SearchResultData(
            key: 'document-'.$document->id,
            group: SearchGroup::Documents,
            title: $document->title,
            subtitle: $document->organization?->name,
            date: $document->date?->toDateString(),
            amount: null,
            href: route('documents.show', $document->id),
            area: null,
            icon: 'file-text',
            tone: 'neutral',
            flag: $document->status === DocumentStatus::Archived ? (string) __('Archived') : null,
            due_in: null,
        );
    }

    /**
     * The documents where every word is in a column or in the summary of the file, or, when the text is a
     * day, that have that date. The date is an alternative for the whole text (a day typed alone is not
     * a word of any title), while the words keep working as before.
     *
     * @param  SearchTerms  $terms  what the person typed
     * @return Builder<Document>
     */
    private function query(SearchTerms $terms): Builder
    {
        return Document::query()->where(function (Builder $query) use ($terms): void {
            $query->where(function (Builder $words) use ($terms): void {
                foreach ($terms->words as $word) {
                    $words->where(function (Builder $group) use ($word): void {
                        $group->orWhere(fn (Builder $inColumns): Builder => SearchTerms::fromQuery($word)->whereWords($inColumns, self::COLUMNS))
                            ->orWhereHas('file', fn (Builder $file): Builder => $file->whereRaw(self::SUMMARY_LIKE, [SearchTerms::like($word)]));
                    });
                }
            });

            if ($terms->date !== null) {
                $query->orWhereDate('date', $terms->date);
            }
        });
    }
}
