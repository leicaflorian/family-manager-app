<?php

declare(strict_types=1);

namespace App\Services\Reading;

use App\Models\File;
use App\Models\Organization;
use App\Models\Person;
use App\Services\CategoryTree;
use App\Services\ExpenseCategoryPaths;
use App\Support\CurrentFamily;

/**
 * What the model is told about the family: the closed lists it picks ids from (categories with their path,
 * organizations, people) and what was written about the file when it was uploaded.
 */
final readonly class ReadingContext
{
    /**
     * @param  list<array{id: int, label: string}>  $expenseCategories  the paths of the expense categories
     * @param  list<array{id: int, label: string}>  $documentCategories  the paths of the document categories
     * @param  list<array{id: int, label: string}>  $organizations
     * @param  list<array{id: int, label: string}>  $people
     * @param  string|null  $fileName  the name the file had when it was uploaded
     * @param  string|null  $fileNote  the note written about the file (who sent it, what it is)
     * @param  int|null  $familyId  the family of the file: the searches that link the cards run inside it, whoever is
     *                              signed in; null runs them in the current family
     */
    public function __construct(
        public array $expenseCategories,
        public array $documentCategories,
        public array $organizations,
        public array $people,
        public ?string $fileName,
        public ?string $fileNote,
        public ?int $familyId = null,
    ) {}

    /**
     * The context of a file: the lists are those of the family of the file, whoever is signed in.
     *
     * @param  File  $file  the file to read
     */
    public static function for(File $file): self
    {
        return CurrentFamily::using($file->family_id, static function () use ($file): self {
            $expensePaths = ExpenseCategoryPaths::all();
            $documentPaths = (new CategoryTree)->paths();

            return new self(
                expenseCategories: self::choices(array_keys($expensePaths), array_values($expensePaths)),
                documentCategories: self::choices(array_keys($documentPaths), array_map(
                    static fn (array $names): string => implode(' › ', $names),
                    array_values($documentPaths),
                )),
                organizations: array_values(Organization::query()->orderBy('name')->get(['id', 'name'])
                    ->map(static fn (Organization $organization): array => ['id' => $organization->id, 'label' => $organization->name])
                    ->all()),
                people: array_values(Person::query()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name'])
                    ->map(static fn (Person $person): array => ['id' => $person->id, 'label' => trim("{$person->first_name} {$person->last_name}")])
                    ->all()),
                fileName: $file->original_name,
                fileNote: $file->note,
                familyId: $file->family_id,
            );
        });
    }

    /**
     * Pairs ids with labels.
     *
     * @param  list<int>  $ids
     * @param  list<string>  $labels  in the same order as the ids
     * @return list<array{id: int, label: string}>
     */
    private static function choices(array $ids, array $labels): array
    {
        return array_map(static fn (int $id, string $label): array => ['id' => $id, 'label' => $label], $ids, $labels);
    }
}
