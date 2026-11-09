<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Data\Search\SearchResultData;
use App\Enums\DocumentStatus;
use App\Enums\SearchGroup;
use App\Models\Document;
use App\Support\SearchTerms;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The search over the documents of the family: by title, notes, tags, the summary the AI read from the file,
 * the organization and the person. A text that is a day (`15/10/2026`) also finds the documents of that day.
 */
final readonly class DocumentResults implements GroupResults
{
    /** The columns every word is looked up in; tags and the summary are JSON, see below. */
    private const array COLUMNS = ['title', 'notes', 'organization.name', 'person.first_name', 'person.last_name'];

    /** The tags (a JSON list) as text with the collation of the text columns, which ignores case and accents. */
    private const string TAGS_LIKE = 'convert(json_unquote(cast(tags as char)) using utf8mb4) collate utf8mb4_unicode_ci like ?';

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
        $documents = $this->query($terms)
            ->orderByRaw('status = ?', [DocumentStatus::Archived->value])
            ->orderByRaw('date is null')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(max(0, $limit))
            ->get();

        return array_values($documents->map(fn (Document $document): SearchResultData => $this->result($document))->all());
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
     * The documents where every word is in a column, in the tags, in the summary of the file or, when the word is
     * a day (`15/10/2026`), is the date of the document. The date is an alternative of its own word only: the
     * other words are still looked up as text, so «bollo 23/09/2026» finds the bollo documents of that day.
     *
     * @param  SearchTerms  $terms  what the person typed
     * @return Builder<Document>
     */
    private function query(SearchTerms $terms): Builder
    {
        return $terms->whereWords(Document::with('organization'), self::COLUMNS, function (Builder $group, string $word): void {
            $group->orWhereRaw(self::TAGS_LIKE, [SearchTerms::like($word)])
                ->orWhereHas('file', fn (Builder $file): Builder => $file->whereRaw(self::SUMMARY_LIKE, [SearchTerms::like($word)]));

            $day = SearchTerms::fromQuery($word)->date;

            if ($day instanceof CarbonImmutable) {
                $group->orWhereDate('date', $day);
            }
        });
    }
}
