<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\CategoryTree;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The filters of *Documents*, and the order of the list: the most recent date
 * first, then the most recently added.
 */
final readonly class FilterDocuments
{
    /** How many days ahead a document counts as expiring. */
    public const int EXPIRING_DAYS = 60;

    public function __construct(private CategoryTree $categories) {}

    /**
     * Narrows the query to the documents that pass every filter given.
     *
     * - `category`: the category and all those below it;
     * - `person`, `organization`: the id;
     * - `status`: `active`, `archived` or `expired`;
     * - `expiring`: the expiry is within 60 days, or has passed, and the
     *   document is not archived;
     * - `linked`: linked to at least one record or, with `false`, to none
     *   (the personal documents of *A person*);
     * - `record`: linked to that record, `['type' => 'vehicle', 'id' => 1]`;
     * - `not_record`: not linked to that record, in the same form;
     * - `search`: in the title and in the notes, ignoring capitals and accents
     *   (the collation of the database does it).
     *
     * @param  Builder<Document>  $query  the query of the documents
     * @param  array<string, mixed>  $filters  the filters, as validated by the list
     * @return Builder<Document> the same query, filtered and ordered
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $category = $filters['category'] ?? null;
        $person = $filters['person'] ?? null;
        $organization = $filters['organization'] ?? null;
        $status = $filters['status'] ?? null;
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        if (is_int($category)) {
            $query->whereIn('category_id', $this->categories->withDescendants($category));
        }

        if (is_int($person)) {
            $query->where('person_id', $person);
        }

        if (is_int($organization)) {
            $query->where('organization_id', $organization);
        }

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        if (($filters['expiring'] ?? false) === true) {
            $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', today()->addDays(self::EXPIRING_DAYS))
                ->where('status', '!=', DocumentStatus::Archived);
        }

        if (is_bool($filters['linked'] ?? null)) {
            $links = static fn (QueryBuilder $links): QueryBuilder => $links->from('documentables')->whereColumn('documentables.document_id', 'documents.id');
            $filters['linked'] ? $query->whereExists($links) : $query->whereNotExists($links);
        }

        $record = $this->record($filters['record'] ?? null);
        if ($record instanceof Closure) {
            $query->whereExists($record);
        }

        $notRecord = $this->record($filters['not_record'] ?? null);
        if ($notRecord instanceof Closure) {
            $query->whereNotExists($notRecord);
        }

        if ($search !== '') {
            // % and _ are letters here, not the wildcards of LIKE.
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn (Builder $query) => $query->whereLike('title', $like)->orWhereLike('notes', $like));
        }

        return $query->orderByDesc('date')->latest()->orderByDesc('id');
    }

    /**
     * The subquery of the links to a record, for `record` and `not_record`.
     *
     * @param  mixed  $record  the record, `['type' => 'vehicle', 'id' => 1]`
     * @return (Closure(QueryBuilder): QueryBuilder)|null null when it is not a record
     */
    private function record(mixed $record): ?Closure
    {
        if (! is_array($record) || ! is_string($record['type'] ?? null) || ! is_int($record['id'] ?? null)) {
            return null;
        }

        return static fn (QueryBuilder $links): QueryBuilder => $links->from('documentables')
            ->whereColumn('documentables.document_id', 'documents.id')
            ->where('documentables.documentable_type', $record['type'])
            ->where('documentables.documentable_id', $record['id']);
    }
}
