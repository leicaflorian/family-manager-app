<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DocumentLinkData;
use App\Data\LinkGroupData;
use App\Data\OptionData;
use App\Models\Document;
use App\Support\Linkables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use RuntimeException;

/**
 * The links of a document to the records of the family (homes, employments,
 * policies…): the choices for the menu, and saving the chosen ones.
 */
final class DocumentLinks
{
    /**
     * The records of the family a document can link to, grouped by type, in
     * the order of `Linkables::WITH_DOCUMENTS`. Types without records are left out.
     *
     * @return list<LinkGroupData>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (array_keys(Linkables::WITH_DOCUMENTS) as $type) {
            $class = Linkables::TYPES[$type];
            $options = [];

            foreach ($class::query()->with($class::labelRelations())->get() as $record) {
                $id = $record->getKey();

                if (is_int($id)) {
                    $options[] = new OptionData((string) $id, $record->label());
                }
            }

            if ($options !== []) {
                usort($options, static fn (OptionData $a, OptionData $b): int => strnatcasecmp($a->label, $b->label));
                $groups[] = new LinkGroupData($type, self::typeLabel($type), $options);
            }
        }

        return $groups;
    }

    /**
     * The records a document links to, in the order of the types and then by name.
     *
     * @param  Document  $document  the document
     * @return list<DocumentLinkData>
     */
    public function of(Document $document): array
    {
        $links = [];

        foreach (Linkables::WITH_DOCUMENTS as $type => $relation) {
            $class = Linkables::TYPES[$type];
            $records = [];

            foreach ($this->relation($document, $relation)->with($class::labelRelations())->get() as $record) {
                $id = $record->getKey();

                if (is_int($id) && method_exists($record, 'label')) {
                    $records[] = new DocumentLinkData($type, $id, self::typeLabel($type), $record->label());
                }
            }

            usort($records, static fn (DocumentLinkData $a, DocumentLinkData $b): int => strnatcasecmp($a->label, $b->label));
            array_push($links, ...$records);
        }

        return $links;
    }

    /**
     * Replaces the links of a document with the given ones.
     *
     * @param  Document  $document  the document
     * @param  list<array{type: string, id: int}>  $links  the chosen records, already validated
     */
    public function sync(Document $document, array $links): void
    {
        foreach (Linkables::WITH_DOCUMENTS as $type => $relation) {
            $ids = array_values(array_unique(array_column(array_filter($links, static fn (array $link): bool => $link['type'] === $type), 'id')));
            $this->relation($document, $relation)->sync($ids);
        }
    }

    /**
     * The name of a type of record, as the menu shows it above its records.
     *
     * @param  string  $type  a type of `Linkables::WITH_DOCUMENTS`
     */
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'home' => __('Homes'),
            'utility' => __('Utilities'),
            'belonging' => __('Belongings'),
            'maintenance_record' => __('Maintenance'),
            'vehicle' => __('Vehicles'),
            'insurance_policy' => __('Insurance policies'),
            'financial_account' => __('Bank accounts and cards'),
            'loan' => __('Loans'),
            'filing' => __('Tax filings'),
            'employment' => __('Employments'),
            'health_event' => __('Health'),
            'enrollment' => __('School and activities'),
            'subscription' => __('Subscriptions'),
            'expense' => __('Expenses'),
            'income' => __('Income'),
            default => $type,
        };
    }

    /**
     * One of the link relations of the document.
     *
     * @param  Document  $document  the document
     * @param  string  $relation  the name of the relation, a value of `Linkables::WITH_DOCUMENTS`
     * @return MorphToMany<Model, Document>
     *
     * @throws RuntimeException if the document has no such relation
     */
    private function relation(Document $document, string $relation): MorphToMany
    {
        $link = $document->{$relation}();

        return $link instanceof MorphToMany ? $link : throw new RuntimeException("The document has no link relation {$relation}.");
    }
}
