<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\LinkGroupData;
use App\Data\OptionData;
use App\Models\Organization;
use App\Models\Person;
use App\Support\Linkables;

/**
 * The records that name a person (`person_id`) or an organization
 * (`organization_id`), for the *Links* of *A person* and *An organization*.
 * Expenses and income stay out: they come with the budget, in stage 3.
 */
final readonly class LinkedRecords
{
    /** The types of record with a `person_id`, in the order of `Linkables::WITH_DOCUMENTS`. */
    public const array OF_PERSON = ['utility', 'vehicle', 'insurance_policy', 'financial_account', 'loan', 'filing', 'employment', 'health_event', 'enrollment', 'subscription'];

    /** The types of record with an `organization_id`, in the order of `Linkables::WITH_DOCUMENTS`. */
    public const array OF_ORGANIZATION = ['utility', 'belonging', 'maintenance_record', 'insurance_policy', 'financial_account', 'loan', 'filing', 'employment', 'health_event', 'enrollment', 'subscription'];

    /**
     * The records of a person, grouped by type. Types without records are left out.
     *
     * @param  Person  $person  the person
     * @return list<LinkGroupData>
     */
    public function ofPerson(Person $person): array
    {
        return $this->groups(self::OF_PERSON, 'person_id', $person->id);
    }

    /**
     * The records of an organization, grouped by type. Types without records are left out.
     *
     * @param  Organization  $organization  the organization
     * @return list<LinkGroupData>
     */
    public function ofOrganization(Organization $organization): array
    {
        return $this->groups(self::OF_ORGANIZATION, 'organization_id', $organization->id);
    }

    /**
     * How many records each person of the family has.
     *
     * @return array<int, int> the count by id of the person; people without records are missing
     */
    public function countByPerson(): array
    {
        $counts = [];

        foreach (self::OF_PERSON as $type) {
            foreach (Linkables::TYPES[$type]::query()->whereNotNull('person_id')->pluck('person_id') as $id) {
                if (is_int($id)) {
                    $counts[$id] = ($counts[$id] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    /**
     * The names of the records of every organization of the family, for the list.
     *
     * @return array<int, list<string>> the names by id of the organization, in the order of the types and then by name
     */
    public function labelsByOrganization(): array
    {
        $labels = [];

        foreach (self::OF_ORGANIZATION as $type) {
            foreach ($this->records($type, 'organization_id') as $record) {
                if ($record['owner'] !== null) {
                    $labels[$record['owner']][] = $record['label'];
                }
            }
        }

        return $labels;
    }

    /**
     * The records with the given id in a column, grouped by type and sorted by name.
     *
     * @param  list<string>  $types  the types of record to read
     * @param  string  $column  `person_id` or `organization_id`
     * @param  int  $id  the id of the person or of the organization
     * @return list<LinkGroupData>
     */
    private function groups(array $types, string $column, int $id): array
    {
        $groups = [];

        foreach ($types as $type) {
            $options = array_map(static fn (array $record): OptionData => new OptionData((string) $record['id'], $record['label']), $this->records($type, $column, $id));

            if ($options !== []) {
                $groups[] = new LinkGroupData($type, DocumentLinks::typeLabel($type), $options);
            }
        }

        return $groups;
    }

    /**
     * The records of a type that name someone in a column, sorted by name.
     *
     * @param  string  $type  a type of `Linkables::WITH_DOCUMENTS`
     * @param  string  $column  `person_id` or `organization_id`
     * @param  int|null  $id  only those of this person or organization; without, all those with the column filled
     * @return list<array{id: int, label: string, owner: int|null}> the id, the name and who the column names
     */
    private function records(string $type, string $column, ?int $id = null): array
    {
        $class = Linkables::TYPES[$type];
        $query = $class::query()->with($class::labelRelations());
        $id === null ? $query->whereNotNull($column) : $query->where($column, $id);
        $records = [];

        foreach ($query->get() as $record) {
            $key = $record->getKey();
            $owner = $record->getAttribute($column);

            if (is_int($key)) {
                $records[] = ['id' => $key, 'label' => $record->label(), 'owner' => is_int($owner) ? $owner : null];
            }
        }

        usort($records, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return $records;
    }
}
