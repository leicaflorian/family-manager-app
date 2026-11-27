<?php

declare(strict_types=1);

namespace App\Support\Reading;

/**
 * What the AI proposes for a bank filter: the words it takes and leaves, and, when it is asked to fill the filter, a
 * title, a category and an organization, with a sentence that says what it takes. The reader gives the names as the
 * model wrote them; `SuggestBankFilter` checks them against the family and adds the ids of the ones it has.
 */
final readonly class BankFilterSuggestion
{
    /**
     * @param  list<string>  $contains  the words the text must all have
     * @param  list<string>  $excludes  the words the text must not have
     * @param  string|null  $title  the title of the expense or income the filter creates
     * @param  string|null  $category  the name of the category
     * @param  string|null  $organization  the name of the organization, of the family or new
     * @param  string  $explanation  what the filter takes, in a simple sentence
     * @param  int|null  $categoryId  the category of the family with that name, once checked
     * @param  int|null  $organizationId  the organization of the family with that name, once checked
     */
    public function __construct(
        public array $contains,
        public array $excludes,
        public ?string $title,
        public ?string $category,
        public ?string $organization,
        public string $explanation,
        public ?int $categoryId = null,
        public ?int $organizationId = null,
    ) {}

    /**
     * Whether the organization is a name the family does not have: a new one, to confirm before it is created.
     */
    public function organizationIsNew(): bool
    {
        return $this->organization !== null && $this->organizationId === null;
    }
}
